<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Product Model - Ürün Modeli
 *
 * Bu model e-ticaret platformundaki ürünleri temsil eder.
 * Ürünler sistemin ana varlıklarından biridir ve sepet, sipariş
 * gibi diğer modeller ile yoğun ilişki içindedir.
 *
 * Özellikler:
 * - Fiyat ve stok yönetimi
 * - Kategori ile ilişkilendirme
 * - Otomatik stok düşürme (sipariş işlemlerinde)
 * - Stok durumu kontrolü
 * - SEO ve arama optimizasyonu
 *
 * İlişkiler:
 * - Category: Her ürün bir kategoriye ait (N:1)
 * - CartItem: Ürün sepet kalemlerinde kullanılabilir (1:N)
 * - OrderItem: Ürün sipariş kalemlerinde yer alabilir (1:N)
 *
 * @property int $id Ürün benzersiz kimliği
 * @property string $name Ürün adı
 * @property string|null $description Ürün açıklaması
 * @property float $price Ürün fiyatı (TL cinsinden)
 * @property int $stock_quantity Stok miktarı
 * @property int $category_id Ait olduğu kategori ID'si
 * @property \Carbon\Carbon $created_at Ürün oluşturulma tarihi
 * @property \Carbon\Carbon $updated_at Son güncellenme tarihi
 * @property Category $category Ait olduğu kategori
 * @property \Illuminate\Database\Eloquent\Collection|CartItem[] $cartItems Bu ürünün sepet kalemleri
 * @property \Illuminate\Database\Eloquent\Collection|OrderItem[] $orderItems Bu ürünün sipariş kalemleri
 */
class Product extends Model
{
    /**
     * Mass assignment için doldurulabilir alanlar
     *
     * Güvenlik için sadece bu alanlar toplu atama ile değiştirilebilir.
     * ID ve timestamp alanları otomatik yönetilir.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',             // Ürün adı (zorunlu)
        'description',      // Ürün açıklaması (opsiyonel)
        'price',            // Ürün fiyatı (zorunlu, pozitif)
        'stock_quantity',   // Stok miktarı (zorunlu, negatif olamaz)
        'category_id'       // Kategori referansı (zorunlu, mevcut kategori olmalı)
    ];

    /**
     * Attribute casting - Veri tiplerini otomatik dönüştür
     *
     * Veritabanından gelen değerleri uygun PHP tiplerine
     * otomatik olarak dönüştürür. Bu sayede type safety sağlanır.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',         // Fiyat 2 ondalık basamak ile float
        'stock_quantity' => 'integer'   // Stok miktarı tam sayı
    ];

    // ================================
    // İLİŞKİLER (RELATIONSHIPS)
    // ================================

    /**
     * Ait olduğu kategori ile ilişki
     *
     * Her ürün mutlaka bir kategoriye ait olmalıdır.
     * Kategori silindiğinde ürünlerin ne olacağı business logic'e göre belirlenir.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Bu ürünün sepet kalemleri ile ilişki
     *
     * Bir ürün birden fazla kullanıcının sepetinde bulunabilir.
     * Ürün silindiğinde sepet kalemlerinin de silinmesi gerekir.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Bu ürünün sipariş kalemleri ile ilişki
     *
     * Bir ürün birden fazla siparişte yer alabilir.
     * Sipariş geçmişi korunması için ürün silinse bile order_items korunmalı.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // ================================
    // STOK YÖNETİMİ
    // ================================

    /**
     * Ürünün stokta olup olmadığını kontrol et
     *
     * @return bool Stokta varsa true
     */
    public function isInStock()
    {
        return $this->stock_quantity > 0;
    }

    /**
     * Ürünün stokta olmadığını kontrol et
     *
     * @return bool Stokta yoksa true
     */
    public function isOutOfStock()
    {
        return $this->stock_quantity <= 0;
    }

    /**
     * Belirli miktar için stok yeterliliğini kontrol et
     *
     * @param int $quantity İstenen miktar
     * @return bool Stok yeterli ise true
     */
    public function hasEnoughStock($quantity)
    {
        return $this->stock_quantity >= $quantity;
    }

    /**
     * Stoktan belirli miktarı düş
     *
     * Sipariş oluşturulduğunda veya rezervasyon yapıldığında kullanılır.
     * Negatif stok oluşturmamaya dikkat eder.
     *
     * @param int $quantity Düşürülecek miktar
     * @return bool İşlem başarılı ise true
     * @throws \Exception Yetersiz stok durumunda
     */
    public function decreaseStock($quantity)
    {
        if (!$this->hasEnoughStock($quantity)) {
            throw new \Exception(
                "Yetersiz stok! '{$this->name}' ürünü için mevcut: {$this->stock_quantity}, talep: {$quantity}"
            );
        }

        $this->decrement('stock_quantity', $quantity);
        return true;
    }

    /**
     * Stoka belirli miktarı ekle
     *
     * İade işlemlerinde veya stok girişinde kullanılır.
     *
     * @param int $quantity Eklenecek miktar
     * @return bool İşlem başarılı ise true
     */
    public function increaseStock($quantity)
    {
        $this->increment('stock_quantity', $quantity);
        return true;
    }

    /**
     * Stok durumu metni döndür
     *
     * @return string Stok durumu ("Stokta", "Stokta Yok", "Son X Adet")
     */
    public function getStockStatusText()
    {
        if ($this->stock_quantity <= 0) {
            return 'Stokta Yok';
        } elseif ($this->stock_quantity <= 5) {
            return "Son {$this->stock_quantity} Adet";
        } else {
            return 'Stokta';
        }
    }

    /**
     * Stok durumu renk kodu döndür (UI için)
     *
     * @return string CSS class veya renk kodu
     */
    public function getStockStatusColor()
    {
        if ($this->stock_quantity <= 0) {
            return 'danger';        // Kırmızı
        } elseif ($this->stock_quantity <= 5) {
            return 'warning';       // Sarı
        } else {
            return 'success';       // Yeşil
        }
    }

    // ================================
    // FİYAT YÖNETİMİ
    // ================================

    /**
     * Formatlanmış fiyat döndür
     *
     * @param string $currency Para birimi sembolü
     * @return string Formatlanmış fiyat (örn: "25,99 TL")
     */
    public function getFormattedPrice($currency = 'TL')
    {
        return number_format($this->price, 2, ',', '.') . ' ' . $currency;
    }

    /**
     * İndirimli fiyat hesapla
     *
     * @param float $discountPercent İndirim yüzdesi (0-100 arası)
     * @return float İndirimli fiyat
     */
    public function getDiscountedPrice($discountPercent)
    {
        return $this->price * (1 - ($discountPercent / 100));
    }

    /**
     * İndirimli fiyat formatla
     *
     * @param float $discountPercent İndirim yüzdesi
     * @param string $currency Para birimi
     * @return string Formatlanmış indirimli fiyat
     */
    public function getFormattedDiscountedPrice($discountPercent, $currency = 'TL')
    {
        $discountedPrice = $this->getDiscountedPrice($discountPercent);
        return number_format($discountedPrice, 2, ',', '.') . ' ' . $currency;
    }

    // ================================
    // ARAMA VE FİLTRELEME (SCOPES)
    // ================================

    /**
     * Sadece stokta olan ürünleri getir
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /**
     * Stokta olmayan ürünleri getir
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    /**
     * Fiyat aralığına göre filtrele
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float|null $minPrice Minimum fiyat
     * @param float|null $maxPrice Maksimum fiyat
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePriceBetween($query, $minPrice = null, $maxPrice = null)
    {
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        return $query;
    }

    /**
     * Ürün adında veya açıklamasında arama yap
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search Aranacak kelime
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ILIKE', "%{$search}%")
                ->orWhere('description', 'ILIKE', "%{$search}%");
        });
    }

    /**
     * Belirli kategorideki ürünleri getir
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $categoryId Kategori ID'si
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Popüler ürünleri getir (sipariş sayısına göre)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $limit Gösterilecek maksimum ürün sayısı
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePopular($query, $limit = 10)
    {
        return $query->withCount('orderItems')
            ->orderBy('order_items_count', 'desc')
            ->limit($limit);
    }

    // ================================
    // YARDIMCI METHODLAR (HELPERS)
    // ================================

    /**
     * Ürünün toplam satış adedini hesapla
     *
     * @return int Toplam satılan adet
     */
    public function getTotalSoldQuantity()
    {
        return $this->orderItems()
            ->whereHas('order', function ($query) {
                $query->where('status', '!=', 'cancelled');
            })
            ->sum('quantity');
    }

    /**
     * Ürünün kaç sepette bulunduğunu hesapla
     *
     * @return int Sepet sayısı
     */
    public function getCartCount()
    {
        return $this->cartItems()->count();
    }

    /**
     * Ürün için özet bilgi döndür
     *
     * API response'larında kullanılmak üzere ürün bilgilerini formatlar.
     *
     * @return array Formatlanmış ürün bilgisi
     */
    public function getSummary()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => $this->getFormattedPrice(),
            'stock_quantity' => $this->stock_quantity,
            'stock_status' => $this->getStockStatusText(),
            'stock_color' => $this->getStockStatusColor(),
            'is_in_stock' => $this->isInStock(),
            'category' => $this->category->name ?? 'Kategori Yok',
            'total_sold' => $this->getTotalSoldQuantity(),
            'cart_count' => $this->getCartCount(),
            'created_at' => $this->created_at->format('d.m.Y')
        ];
    }

    /**
     * String representation - Model yazdırıldığında ürün adını göster
     *
     * @return string Ürün adı
     */
    public function __toString()
    {
        return $this->name;
    }
}
