<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * OrderController - Sipariş Yönetim Controller'ı
 *
 * Bu controller e-ticaret platformunun sipariş işlemlerini yönetir:
 * - Sepetten sipariş oluşturma (otomatik stok düşürme)
 * - Kullanıcının siparişlerini listeleme ve filtreleme
 * - Sipariş detaylarını görüntüleme
 * - Sipariş istatistiklerini hesaplama
 *
 * Tüm işlemler database transaction ile güvence altına alınır.
 */
class OrderController extends Controller
{
    /**
     * Sepetteki ürünlerden yeni sipariş oluştur
     *
     * Kullanıcının sepetindeki tüm ürünleri sipariş haline getirir.
     * İşlem sırasında:
     * - Stok kontrolü yapılır
     * - Ürün stokları otomatik düşürülür
     * - Sepet temizlenir
     * - Database transaction ile veri tutarlılığı sağlanır
     *
     * @param Request $request - HTTP isteği (bu endpoint için parametre gerektirmez)
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = JWTAuth::user();

        // Kullanıcının sepetini ürün bilgileri ile birlikte al
        $cart = $user->cart()->with('items.product')->first();

        // Boş sepet kontrolü - Sepet yoksa veya boşsa sipariş oluşturulamaz
        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Sepetiniz boş, sipariş oluşturamazsınız'
            ], 400);
        }

        // Database transaction başlat - Tüm işlemler başarılı olmalı
        DB::beginTransaction();

        try {
            // Önce tüm ürünlerin stok durumunu kontrol et
            foreach ($cart->items as $cartItem) {
                if ($cartItem->product->stock_quantity < $cartItem->quantity) {
                    // Yetersiz stok varsa transaction'ı geri al
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "'{$cartItem->product->name}' ürünü için yetersiz stok! Mevcut: {$cartItem->product->stock_quantity}, Talep: {$cartItem->quantity}"
                    ], 400);
                }
            }

            // Sipariş toplam tutarını hesapla
            $totalAmount = $cart->getTotalPrice();

            // Yeni sipariş kaydı oluştur
            $order = Order::create([
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'status' => 'pending'                    // Sipariş beklemede başlar
            ]);

            // Sepetteki her ürün için sipariş kalemi oluştur
            foreach ($cart->items as $cartItem) {
                // Sipariş kalemini kaydet (o anki fiyat ile)
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->product->price    // Sipariş anındaki fiyatı sakla
                ]);

                // Ürün stokunu sipariş miktarı kadar düşür
                $cartItem->product->decrement('stock_quantity', $cartItem->quantity);
            }

            // Sipariş oluşturulduktan sonra sepeti temizle
            $cart->items()->delete();

            // Tüm işlemler başarılıysa transaction'ı onayla
            DB::commit();

            // Oluşturulan siparişi ilişkili verilerle birlikte yükle
            $order->load(['items.product.category']);

            // Başarılı sipariş oluşturma yanıtı
            return response()->json([
                'success' => true,
                'message' => 'Sipariş başarıyla oluşturuldu',
                'data' => [
                    'order_id' => $order->id,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'status_text' => $order->getStatusText(),           // İnsan okunabilir status
                    'total_items' => $order->getTotalItems(),           // Toplam ürün adedi
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product' => $item->product,
                            'quantity' => $item->quantity,
                            'price' => $item->price,                    // Sipariş anındaki fiyat
                            'subtotal' => $item->getSubtotal()          // Miktar x fiyat
                        ];
                    }),
                    'created_at' => $order->created_at
                ]
            ], 201);

        } catch (\Exception $e) {
            // Herhangi bir hata durumunda transaction'ı geri al
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Sipariş oluşturulurken hata oluştu',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kullanıcının siparişlerini listele
     *
     * Giriş yapmış kullanıcının tüm siparişlerini listeler.
     * Status'a göre filtreleme ve sayfalama desteği sunar.
     * Siparişler en yeniden eskiye doğru sıralanır.
     *
     * @param Request $request - Filtreleme parametrelerini içeren HTTP isteği
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = JWTAuth::user();

        // Kullanıcının siparişlerini ilişkili verilerle birlikte al
        $query = $user->orders()->with(['items.product.category']);

        // Status filtresi - Belirli durumda ki siparişleri getir
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Siparişleri tarihe göre ters sırala ve sayfalandır
        $orders = $query->orderBy('created_at', 'desc')->paginate(10);

        // Sipariş listesini özet bilgilerle transform et
        $orders->getCollection()->transform(function ($order) {
            return [
                'id' => $order->id,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'status_text' => $order->getStatusText(),           // İnsan okunabilir status
                'total_items' => $order->getTotalItems(),           // Toplam ürün adedi
                'created_at' => $order->created_at,
                'items_count' => $order->items->count()             // Kalem sayısı
            ];
        });

        // Sipariş listesi yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Siparişler başarıyla listelendi',
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total()
            ]
        ]);
    }

    /**
     * Sipariş detaylarını görüntüle
     *
     * Belirli bir siparişin tüm detaylarını döndürür.
     * Güvenlik için sadece sipariş sahibi kendi siparişlerini görebilir.
     *
     * @param int $id - Görüntülenecek siparişin ID'si
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = JWTAuth::user();

        // Sadece kullanıcının kendi siparişlerinden ara (güvenlik)
        $order = $user->orders()->with(['items.product.category'])->find($id);

        // Sipariş bulunamazsa veya kullanıcıya ait değilse hata döndür
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Sipariş bulunamadı veya size ait değil'
            ], 404);
        }

        // Detaylı sipariş bilgileri yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Sipariş detayı başarıyla alındı',
            'data' => [
                'id' => $order->id,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'status_text' => $order->getStatusText(),
                'total_items' => $order->getTotalItems(),
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product' => $item->product,                // Ürün detayları
                        'quantity' => $item->quantity,              // Sipariş miktarı
                        'price' => $item->price,                    // Sipariş anındaki fiyat
                        'subtotal' => $item->getSubtotal()          // Kalem toplam tutarı
                    ];
                }),
                'created_at' => $order->created_at,                 // Sipariş tarihi
                'updated_at' => $order->updated_at                  // Son güncelleme
            ]
        ]);
    }

    /**
     * Kullanıcının sipariş istatistiklerini hesapla
     *
     * Kullanıcının genel sipariş verilerini özet halinde sunar:
     * - Toplam sipariş sayısı
     * - Status bazlı sipariş sayıları
     * - Toplam harcama miktarı (iptal edilen siparişler hariç)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $user = JWTAuth::user();

        // Sipariş istatistiklerini hesapla
        $stats = [
            'total_orders' => $user->orders()->count(),                                    // Toplam sipariş
            'pending_orders' => $user->orders()->where('status', 'pending')->count(),     // Bekleyen siparişler
            'completed_orders' => $user->orders()->where('status', 'delivered')->count(), // Tamamlanan siparişler
            'cancelled_orders' => $user->orders()->where('status', 'cancelled')->count(), // İptal edilen siparişler
            'total_spent' => $user->orders()->where('status', '!=', 'cancelled')          // Toplam harcama
            ->sum('total_amount')                          // (iptal edilenler hariç)
        ];

        // İstatistik yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Sipariş istatistikleri',
            'data' => $stats
        ]);
    }
}
