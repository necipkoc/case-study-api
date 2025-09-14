<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * CategoryController - Ürün Kategori Yönetim Controller'ı
 *
 * Bu controller ürün kategorilerinin CRUD işlemlerini yönetir:
 * - Kategori listeleme (herkese açık)
 * - Kategori oluşturma (sadece admin)
 * - Kategori güncelleme (sadece admin)
 * - Kategori silme (sadece admin)
 */
class CategoryController extends Controller
{
    /**
     * Tüm kategorileri listele
     *
     * Bu endpoint herkese açıktır ve kimlik doğrulama gerektirmez.
     * Müşterilerin ürün filtreleme yaparken kategorileri görebilmesi için
     * public olarak tasarlanmıştır.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Tüm kategorileri veritabanından al
        // Gelecekte pagination ve sıralama özellikleri eklenebilir
        $categories = Category::all();

        return response()->json([
            'success' => true,
            'message' => 'Kategoriler başarıyla listelendi',
            'data' => $categories
        ]);
    }

    /**
     * Yeni kategori oluştur
     *
     * Sadece admin yetkisine sahip kullanıcılar yeni kategori oluşturabilir.
     * Kategori adı benzersiz olmalıdır.
     *
     * @param Request $request - Kategori bilgilerini içeren HTTP isteği
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Admin yetki kontrolü - Sadece adminler kategori oluşturabilir
        if (JWTAuth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Bu işlem için admin yetkisi gereklidir'
            ], 401);
        }

        // Kategori oluşturma validasyon kuralları
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories',  // Benzersiz kategori adı zorunlu
            'description' => 'nullable|string'                      // Açıklama opsiyonel
        ]);

        // Validasyon hatası varsa hata mesajı döndür
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasyon Hatası',
                'errors' => $validator->errors()
            ], 422);
        }

        // Yeni kategoriyi veritabanında oluştur
        $category = Category::create([
            'name' => $request->name,
            'description' => $request->description
        ]);

        // Başarılı oluşturma yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Kategori başarıyla oluşturuldu',
            'data' => $category
        ], 201);
    }

    /**
     * Mevcut kategoriyi güncelle
     *
     * Sadece admin yetkisine sahip kullanıcılar kategori güncelleyebilir.
     * Partial update desteklenir - sadece gönderilen alanlar güncellenir.
     *
     * @param Request $request - Güncellenecek kategori bilgileri
     * @param int $id - Güncellenecek kategorinin ID'si
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

        // Güncellenecek kategoriyi bul
        $category = Category::find($id);

        // Kategori bulunamazsa 404 hatası döndür
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori bulunamadı'
            ], 404);
        }

        // Güncelleme validasyon kuralları
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $id,  // Mevcut kategori hariç benzersiz ad
            'description' => 'nullable|string'                                            // Açıklama opsiyonel
        ]);

        // Validasyon hatası kontrolü
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasyon Hatası',
                'errors' => $validator->errors()
            ], 422);
        }

        // Sadece gönderilen alanları güncelle (partial update)
        if ($request->has('name')) {
            $category->name = $request->name;
        }

        if ($request->has('description')) {
            $category->description = $request->description;
        }

        // Değişiklikleri veritabanına kaydet
        $category->save();

        // Başarılı güncelleme yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Kategori başarıyla güncellendi',
            'data' => $category
        ]);
    }

    /**
     * Kategoriyi sil
     *
     * Sadece admin yetkisine sahip kullanıcılar kategori silebilir.
     * Dikkat: Kategori silindiğinde, bu kategoriye ait ürünler de
     * foreign key constraint nedeniyle etkilenebilir.
     *
     * @param int $id - Silinecek kategorinin ID'si
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

        // Silinecek kategoriyi bul
        $category = Category::find($id);

        // Kategori bulunamazsa 404 hatası döndür
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori bulunamadı'
            ], 404);
        }

        // Kategoriyi veritabanından sil
        // Not: Eğer bu kategoriye ait ürünler varsa,
        // foreign key constraint hatası alınabilir
        $category->delete();

        // Başarılı silme yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Kategori başarıyla silindi'
        ]);
    }
}
