<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * ProductController - Ürün Yönetim Controller'ı
 *
 * Bu controller e-ticaret platformunun ürün CRUD işlemlerini yönetir:
 * - Ürün listeleme ve filtreleme (herkese açık)
 * - Ürün detayı görüntüleme (herkese açık)
 * - Ürün oluşturma (sadece admin)
 * - Ürün güncelleme (sadece admin)
 * - Ürün silme (sadece admin)
 */
class ProductController extends Controller
{
    /**
     * Ürünleri listele ve filtrele
     *
     * Bu endpoint herkese açıktır ve gelişmiş filtreleme özelliklerine sahiptir.
     * Müşteriler ürünleri kategori, fiyat aralığı ve isme göre arayabilir.
     * Sayfalama ile büyük ürün katalogları verimli şekilde yönetilir.
     *
     * @param Request $request - Filtreleme ve sayfalama parametrelerini içeren HTTP isteği
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Ürünleri kategori bilgileri ile birlikte al (N+1 problemini önler)
        $query = Product::with('category');

        // Kategori filtresi - Belirli bir kategorideki ürünleri göster
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Minimum fiyat filtresi - Belirtilen fiyatın üzerindeki ürünler
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        // Maksimum fiyat filtresi - Belirtilen fiyatın altındaki ürünler
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Ürün adında arama - PostgreSQL için ILIKE kullanılır (case-insensitive)
        if ($request->has('search')) {
            $query->where('name', 'ILIKE', '%' . $request->search . '%');
        }

        // Sayfalama - Varsayılan sayfa başına 20 ürün, maksimum 100
        $limit = min($request->get('limit', 20), 100);
        $products = $query->paginate($limit);

        // Filtrelenmiş ürün listesi yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Ürünler başarıyla listelendi',
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total()
            ]
        ]);
    }

    /**
     * Ürün detayını görüntüle
     *
     * Belirli bir ürünün tüm detaylarını kategori bilgisi ile birlikte döndürür.
     * Bu endpoint herkese açıktır ve ürün sayfaları için kullanılır.
     *
     * @param int $id - Görüntülenecek ürünün ID'si
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // Ürünü kategori bilgisi ile birlikte bul
        $product = Product::with('category')->find($id);

        // Ürün bulunamazsa 404 hatası döndür
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Ürün bulunamadı'
            ], 404);
        }

        // Ürün detayı başarılı yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Ürün detayı başarıyla alındı',
            'data' => $product
        ]);
    }

    /**
     * Yeni ürün oluştur
     *
     * Sadece admin yetkisine sahip kullanıcılar yeni ürün oluşturabilir.
     * Ürün bilgileri kapsamlı olarak validate edilir ve kategori varlığı kontrol edilir.
     *
     * @param Request $request - Ürün bilgilerini içeren HTTP isteği
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Admin yetki kontrolü - Sadece adminler ürün oluşturabilir
        if (JWTAuth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Bu işlem için admin yetkisi gereklidir'
            ], 401);
        }

        // Ürün oluşturma validasyon kuralları
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:255',              // Ürün adı zorunlu ve 3-255 karakter arası
            'description' => 'nullable|string',                     // Ürün açıklaması opsiyonel
            'price' => 'required|numeric|min:0.01',                 // Fiyat pozitif sayı olmalı
            'stock_quantity' => 'required|integer|min:0',           // Stok miktarı negatif olamaz
            'category_id' => 'required|integer|exists:categories,id' // Kategori mutlaka var olmalı
        ]);

        // Validasyon hatası varsa hata mesajı döndür
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasyon Hatası',
                'errors' => $validator->errors()
            ], 422);
        }

        // Yeni ürünü veritabanında oluştur
        $product = Product::create($request->all());

        // Başarılı oluşturma yanıtı (kategori bilgisi ile birlikte)
        return response()->json([
            'success' => true,
            'message' => 'Ürün başarıyla oluşturuldu',
            'data' => $product->load('category')
        ], 201);
    }

    /**
     * Mevcut ürünü güncelle
     *
     * Sadece admin yetkisine sahip kullanıcılar ürün güncelleyebilir.
     * Partial update desteklenir - sadece gönderilen alanlar güncellenir.
     * Stok miktarı da bu endpoint ile güncellenebilir.
     *
     * @param Request $request - Güncellenecek ürün bilgileri
     * @param int $id - Güncellenecek ürünün ID'si
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Admin yetki kontrolü
        if (JWTAuth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Bu işlem için admin yetkisi gereklidir'
            ], 401);
        }

        // Güncellenecek ürünü bul
        $product = Product::find($id);

        // Ürün bulunamazsa 404 hatası döndür
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Ürün bulunamadı'
            ], 404);
        }

        // Ürün güncelleme validasyon kuralları (sometimes = opsiyonel alanlar)
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|min:3|max:255',              // Opsiyonel ürün adı
            'description' => 'nullable|string',                               // Açıklama her zaman opsiyonel
            'price' => 'sometimes|required|numeric|min:0.01',                 // Opsiyonel fiyat güncelleme
            'stock_quantity' => 'sometimes|required|integer|min:0',           // Opsiyonel stok güncelleme
            'category_id' => 'sometimes|required|integer|exists:categories,id' // Opsiyonel kategori değişimi
        ]);

        // Validasyon hatası kontrolü
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasyon Hatası',
                'errors' => $validator->errors()
            ], 422);
        }

        // Sadece gönderilen alanları güncelle (mass assignment)
        $product->update($request->all());

        // Başarılı güncelleme yanıtı (kategori bilgisi ile birlikte)
        return response()->json([
            'success' => true,
            'message' => 'Ürün başarıyla güncellendi',
            'data' => $product->load('category')
        ]);
    }

    /**
     * Ürünü sil
     *
     * Sadece admin yetkisine sahip kullanıcılar ürün silebilir.
     * Dikkat: Ürün silindiğinde, sepetlerdeki ve geçmiş siparişlerdeki
     * referanslar etkilenebilir. Soft delete kullanımı önerilir.
     *
     * @param int $id - Silinecek ürünün ID'si
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        // Admin yetki kontrolü
        if (JWTAuth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Bu işlem için admin yetkisi gereklidir'
            ], 401);
        }

        // Silinecek ürünü bul
        $product = Product::find($id);

        // Ürün bulunamazsa 404 hatası döndür
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Ürün bulunamadı'
            ], 404);
        }

        // Ürünü veritabanından sil
        // Not: Bu işlem sepetlerdeki ve sipariş geçmişindeki
        // foreign key referanslarını etkileyebilir
        $product->delete();

        // Başarılı silme yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Ürün başarıyla silindi'
        ]);
    }
}
