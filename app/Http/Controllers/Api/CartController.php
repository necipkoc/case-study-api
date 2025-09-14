<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * CartController - Alışveriş Sepeti Yönetim Controller'ı
 *
 * Bu controller e-ticaret platformunun sepet işlemlerini yönetir:
 * - Sepet içeriğini görüntüleme
 * - Sepete ürün ekleme (stok kontrolü ile)
 * - Sepet ürün miktarı güncelleme
 * - Sepetten ürün çıkarma
 * - Sepeti tamamen temizleme
 *
 * Her kullanıcının tek sepeti olur ve işlemler JWT auth ile korunur.
 */
class CartController extends Controller
{
    /**
     * Kullanıcının sepet içeriğini görüntüle
     *
     * Kullanıcının mevcut sepetindeki tüm ürünleri, miktarlarını ve
     * toplam fiyat bilgilerini döndürür. Sepet yoksa boş sepet yanıtı verir.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Giriş yapmış kullanıcıyı al
        $user = JWTAuth::user();

        // Kullanıcının sepetini ürün ve kategori bilgileri ile birlikte al
        // N+1 problemini önlemek için with() kullanılır
        $cart = $user->cart()->with(['items.product.category'])->first();

        // Sepet yoksa boş sepet yanıtı döndür
        if (!$cart) {
            return response()->json([
                'success' => true,
                'message' => 'Sepet boş',
                'data' => [
                    'items' => [],
                    'total_items' => 0,
                    'total_price' => 0
                ]
            ]);
        }

        // Sepet içeriğini formatla ve döndür
        return response()->json([
            'success' => true,
            'message' => 'Sepet başarıyla alındı',
            'data' => [
                'items' => $cart->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product' => $item->product,                    // Ürün detayları
                        'quantity' => $item->quantity,                  // Sepetteki miktar
                        'subtotal' => $item->getSubtotal()             // Ürün x miktar toplam fiyat
                    ];
                }),
                'total_items' => $cart->getTotalItems(),              // Toplam ürün adedi
                'total_price' => $cart->getTotalPrice()               // Sepet toplam tutarı
            ]
        ]);
    }

    /**
     * Sepete yeni ürün ekle
     *
     * Belirtilen ürünü sepete ekler. Eğer ürün zaten sepette varsa
     * miktarını artırır. Stok kontrolü yaparak yetersiz stok durumunda
     * hata döndürür.
     *
     * @param Request $request - Eklenecek ürün ID'si ve miktarını içeren HTTP isteği
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(Request $request)
    {
        // Sepete ekleme validasyon kuralları
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',  // Ürün mutlaka var olmalı
            'quantity' => 'required|integer|min:1'                  // Miktar pozitif olmalı
        ]);

        // Validasyon hatası varsa hata mesajı döndür
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasyon Hatası',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = JWTAuth::user();
        $product = Product::find($request->product_id);

        // İlk stok kontrolü - İstenen miktar mevcut stoktan fazla mı?
        if ($product->stock_quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Yetersiz stok! Mevcut stok: ' . $product->stock_quantity
            ], 400);
        }

        // Kullanıcının sepetini al veya yeni sepet oluştur
        $cart = $user->cart()->firstOrCreate(['user_id' => $user->id]);

        // Bu ürün sepette var mı kontrol et
        $cartItem = $cart->items()->where('product_id', $request->product_id)->first();

        if ($cartItem) {
            // Ürün zaten sepette var - miktarını artır
            $newQuantity = $cartItem->quantity + $request->quantity;

            // Arttırılmış miktar stok miktarını aşıyor mu kontrol et
            if ($product->stock_quantity < $newQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Yetersiz stok! Sepetinizdeki miktar: ' . $cartItem->quantity . ', Mevcut stok: ' . $product->stock_quantity
                ], 400);
            }

            // Sepetteki miktarı güncelle
            $cartItem->update(['quantity' => $newQuantity]);
        } else {
            // Yeni ürün - sepete ekle
            $cartItem = $cart->items()->create([
                'product_id' => $request->product_id,
                'quantity' => $request->quantity
            ]);
        }

        // Güncellenmiş sepet bilgilerini yükle
        $cart->load(['items.product.category']);

        // Başarılı ekleme yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Ürün sepete başarıyla eklendi',
            'data' => [
                'items' => $cart->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product' => $item->product,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->getSubtotal()
                    ];
                }),
                'total_items' => $cart->getTotalItems(),
                'total_price' => $cart->getTotalPrice()
            ]
        ], 201);
    }

    /**
     * Sepetteki ürün miktarını güncelle
     *
     * Sepette bulunan bir ürünün miktarını yeni bir değere günceller.
     * Stok kontrolü yaparak güvenli güncelleme sağlar.
     *
     * @param Request $request - Güncellenecek ürün ID'si ve yeni miktarını içeren HTTP isteği
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        // Sepet güncelleme validasyon kuralları
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1'                  // Miktar 0 olamaz (silme için ayrı endpoint var)
        ]);

        // Validasyon hatası kontrolü
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasyon Hatası',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = JWTAuth::user();
        $cart = $user->cart;

        // Kullanıcının sepeti var mı kontrol et
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Sepet bulunamadı'
            ], 404);
        }

        // Güncellenecek ürün sepette var mı kontrol et
        $cartItem = $cart->items()->where('product_id', $request->product_id)->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Ürün sepette bulunamadı'
            ], 404);
        }

        $product = Product::find($request->product_id);

        // Yeni miktar için stok kontrolü
        if ($product->stock_quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Yetersiz stok! Mevcut stok: ' . $product->stock_quantity
            ], 400);
        }

        // Sepet ürün miktarını güncelle
        $cartItem->update(['quantity' => $request->quantity]);

        // Güncellenmiş sepet bilgilerini yükle
        $cart->load(['items.product.category']);

        // Başarılı güncelleme yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Sepet başarıyla güncellendi',
            'data' => [
                'items' => $cart->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product' => $item->product,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->getSubtotal()
                    ];
                }),
                'total_items' => $cart->getTotalItems(),
                'total_price' => $cart->getTotalPrice()
            ]
        ]);
    }

    /**
     * Sepetten belirli bir ürünü çıkar
     *
     * Belirtilen ürünü sepetten tamamen kaldırır (miktar ne olursa olsun).
     * Ürün sepette yoksa hata döndürür.
     *
     * @param int $productId - Sepetten çıkarılacak ürünün ID'si
     * @return \Illuminate\Http\JsonResponse
     */
    public function remove($productId)
    {
        $user = JWTAuth::user();
        $cart = $user->cart;

        // Kullanıcının sepeti var mı kontrol et
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Sepet bulunamadı'
            ], 404);
        }

        // Çıkarılacak ürün sepette var mı kontrol et
        $cartItem = $cart->items()->where('product_id', $productId)->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Ürün sepette bulunamadı'
            ], 404);
        }

        // Ürünü sepetten tamamen kaldır
        $cartItem->delete();

        // Güncellenmiş sepet bilgilerini yükle
        $cart->load(['items.product.category']);

        // Başarılı çıkarma yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Ürün sepetten çıkarıldı',
            'data' => [
                'items' => $cart->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product' => $item->product,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->getSubtotal()
                    ];
                }),
                'total_items' => $cart->getTotalItems(),
                'total_price' => $cart->getTotalPrice()
            ]
        ]);
    }

    /**
     * Sepeti tamamen temizle
     *
     * Kullanıcının sepetindeki tüm ürünleri kaldırır.
     * Sepet boşsa bilgilendirici mesaj döndürür.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clear()
    {
        $user = JWTAuth::user();
        $cart = $user->cart;

        // Kullanıcının sepeti var mı ve dolu mu kontrol et
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Sepet zaten boş'
            ], 404);
        }

        // Sepetteki tüm ürünleri sil
        $cart->items()->delete();

        // Boş sepet yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Sepet başarıyla temizlendi',
            'data' => [
                'items' => [],
                'total_items' => 0,
                'total_price' => 0
            ]
        ]);
    }
}
