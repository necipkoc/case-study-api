<?php

/**
 * API Routes - E-Ticaret Platformu API Rotaları
 *
 * Bu dosya e-ticaret platformunun tüm API endpoint'lerini tanımlar.
 * Rotalar mantıksal gruplara ayrılmış ve güvenlik seviyelerine göre organize edilmiştir.
 *
 * Güvenlik Modeli:
 * - Genel erişim: Kayıt, giriş, kategori ve ürün listeleme
 * - Korumalı erişim: Profil, sepet, sipariş işlemleri (JWT token gerekli)
 *
 * API Versiyonu: v1
 * Base URL: /api/
 * Authentication: JWT Bearer Token
 *
 * @package Case Study API
 * @version 1.0
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;

// ================================
// GENEL ERİŞİM ROTALARİ (PUBLIC)
// ================================
// Bu rotalar authentication gerektirmez ve herkese açıktır

/**
 * KULLANICI KİMLİK DOĞRULAMA (AUTHENTICATION)
 *
 * Kullanıcı kaydı ve giriş işlemleri için genel erişimli endpoint'ler.
 * Bu işlemler JWT token üretir ve sonraki isteklerde kullanılır.
 */

// Yeni kullanıcı kaydı
// POST /api/register
// Body: name, email, password, password_confirmation
// Response: user bilgileri + JWT token
Route::post('register', [AuthController::class, 'register']);

// Kullanıcı girişi (login)
// POST /api/login
// Body: email, password
// Response: user bilgileri + JWT token
Route::post('login', [AuthController::class, 'login']);

/**
 * KATEGORİ İŞLEMLERİ (PUBLIC READ)
 *
 * Kategori listeleme işlemi herkese açıktır.
 * E-ticaret sitelerinde kategoriler genellikle anonim kullanıcılara da gösterilir.
 */

// Tüm kategorileri listele (aktif kategoriler)
// GET /api/categories
// Query params: search (opsiyonel)
// Response: kategori listesi + ürün sayıları
Route::get('categories', [CategoryController::class, 'index']);

/**
 * ÜRÜN İŞLEMLERİ (PUBLIC READ)
 *
 * Ürün listeleme ve detay görüntüleme işlemleri herkese açıktır.
 * Müşteriler ürünleri görebilmeli ancak sepete eklemek için giriş yapmalıdır.
 */

// Tüm ürünleri listele (stokta olan ürünler)
// GET /api/products
// Query params: category_id, search, min_price, max_price, page
// Response: sayfalanmış ürün listesi
Route::get('products', [ProductController::class, 'index']);

// Belirli bir ürünün detayını görüntüle
// GET /api/products/{id}
// URL param: id (ürün ID'si)
// Response: ürün detayı + kategori bilgisi + stok durumu
Route::get('products/{id}', [ProductController::class, 'show']);

// ================================
// KORUNMUŞ ERİŞİM ROTALARİ (PROTECTED)
// ================================
// Bu rotalar JWT authentication middleware ile korunur
// Header: Authorization: Bearer {token}

Route::middleware('auth:api')->group(function () {

    /**
     * KULLANICI PROFİL İŞLEMLERİ
     *
     * Giriş yapmış kullanıcıların profil bilgilerini yönetebileceği endpoint'ler.
     * Bu işlemler kullanıcının kendi verilerine erişim sağlar.
     */

    // Kullanıcı profil bilgilerini görüntüle
    // GET /api/profile
    // Response: mevcut kullanıcının profil bilgileri
    Route::get('profile', [AuthController::class, 'profile']);

    // Kullanıcı çıkışı (logout) - Token'ı geçersiz kıl
    // POST /api/logout
    // Response: başarı mesajı
    Route::post('logout', [AuthController::class, 'logout']);

    // Kullanıcı profil bilgilerini güncelle
    // PUT /api/update
    // Body: name, email (opsiyonel alanlar)
    // Response: güncellenmiş profil bilgileri
    Route::put('update', [AuthController::class, 'update']);

    /**
     * KATEGORİ YÖNETİMİ (ADMIN İŞLEMLERİ)
     *
     * Kategori oluşturma, güncelleme ve silme işlemleri.
     * Not: Bu işlemler admin yetkisi gerektirir ancak şu an role kontrolü yok.
     * Gelecekte admin middleware eklenmelidir.
     */

    // Yeni kategori oluştur
    // POST /api/categories
    // Body: name, description
    // Response: oluşturulan kategori bilgileri
    Route::post('categories', [CategoryController::class, 'store']);

    // Mevcut kategoriyi güncelle
    // PUT /api/categories/{id}
    // Body: name, description (opsiyonel alanlar)
    // Response: güncellenmiş kategori bilgileri
    Route::put('categories/{id}', [CategoryController::class, 'update']);

    // Kategoriyi sil
    // DELETE /api/categories/{id}
    // Response: silme başarı mesajı
    // Not: İçerisinde ürün varsa silme engellenir
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

    /**
     * ÜRÜN YÖNETİMİ (ADMIN İŞLEMLERİ)
     *
     * Ürün oluşturma, güncelleme ve silme işlemleri.
     * Not: Bu işlemler admin yetkisi gerektirir.
     */

    // Yeni ürün oluştur
    // POST /api/products
    // Body: name, description, price, stock_quantity, category_id
    // Response: oluşturulan ürün bilgileri
    Route::post('products', [ProductController::class, 'store']);

    // Mevcut ürünü güncelle
    // PUT /api/products/{id}
    // Body: name, description, price, stock_quantity, category_id (opsiyonel)
    // Response: güncellenmiş ürün bilgileri
    Route::put('products/{id}', [ProductController::class, 'update']);

    // Ürünü sil
    // DELETE /api/products/{id}
    // Response: silme başarı mesajı
    // Not: Sepetlerde ve siparişlerde referans kontrolü yapılmalı
    Route::delete('products/{id}', [ProductController::class, 'destroy']);

    /**
     * SEPET YÖNETİMİ (KULLANICI İŞLEMLERİ)
     *
     * Her kullanıcının kendi sepetini yönetebileceği endpoint'ler.
     * Sepet işlemleri kullanıcı bazlıdır ve cross-user erişim yoktur.
     */

    // Kullanıcının sepetini görüntüle
    // GET /api/cart
    // Response: sepet içeriği + toplam tutar + ürün detayları
    Route::get('cart', [CartController::class, 'index']);

    // Sepete ürün ekle
    // POST /api/cart/add
    // Body: product_id, quantity
    // Response: güncellenmiş sepet durumu
    // Not: Aynı ürün varsa miktar artırılır
    Route::post('cart/add', [CartController::class, 'add']);

    // Sepetteki ürün miktarını güncelle
    // PUT /api/cart/update
    // Body: product_id, quantity
    // Response: güncellenmiş sepet durumu
    // Not: Quantity 0 olursa ürün sepetten kaldırılır
    Route::put('cart/update', [CartController::class, 'update']);

    // Sepetten belirli ürünü kaldır
    // DELETE /api/cart/remove/{product_id}
    // URL param: product_id (kaldırılacak ürün ID'si)
    // Response: güncellenmiş sepet durumu
    Route::delete('cart/remove/{product_id}', [CartController::class, 'remove']);

    // Sepeti tamamen temizle
    // DELETE /api/cart/clear
    // Response: boş sepet durumu
    // Note: Tüm sepet kalemleri silinir
    Route::delete('cart/clear', [CartController::class, 'clear']);

    /**
     * SİPARİŞ YÖNETİMİ (KULLANICI İŞLEMLERİ)
     *
     * Kullanıcıların sipariş oluşturabileceği ve geçmişini görüntüleyebileceği endpoint'ler.
     * Tüm sipariş işlemleri kullanıcı bazlıdır ve güvenli şekilde izole edilmiştir.
     */

    // Sepetten yeni sipariş oluştur
    // POST /api/orders
    // Body: boş (sepet içeriği otomatik alınır)
    // Response: oluşturulan sipariş detayları
    // İşlemler: stok kontrolü + stok düşürme + sepet temizleme
    Route::post('orders', [OrderController::class, 'store']);

    // Kullanıcının siparişlerini listele
    // GET /api/orders
    // Query params: status (opsiyonel), page
    // Response: sayfalanmış sipariş listesi
    Route::get('orders', [OrderController::class, 'index']);

    // Belirli siparişin detayını görüntüle
    // GET /api/orders/{id}
    // URL param: id (sipariş ID'si)
    // Response: sipariş detayı + kalemler + ürün bilgileri
    // Note: Sadece kullanıcının kendi siparişleri görüntülenebilir
    Route::get('orders/{id}', [OrderController::class, 'show']);

    // Kullanıcının sipariş istatistiklerini görüntüle
    // GET /api/orders-stats
    // Response: toplam sipariş, toplam harcama, status dağılımı
    Route::get('orders-stats', [OrderController::class, 'stats']);
});

/**
 * ROUTE GRUPLAMA STRATEJİSİ
 *
 * 1. Public Routes (0-2): Authentication ve genel bilgiler
 *    - register, login
 *    - categories (read), products (read)
 *
 * 2. Protected Routes (auth:api middleware):
 *    - Profile management (3-5)
 *    - Admin operations (6-11) - gelecekte role middleware eklenecek
 *    - Cart operations (12-16) - kullanıcı bazlı
 *    - Order operations (17-20) - kullanıcı bazlı
 *
 */
