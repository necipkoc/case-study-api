<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Category Model - Ürün Kategori Modeli
 *
 * Bu model e-ticaret platformundaki ürün kategorilerini temsil eder.
 * Kategoriler ürünleri gruplamak, filtreleme yapmak ve
 * site navigasyonunu organize etmek için kullanılır.
 *
 * Özellikler:
 * - Basit kategori yapısı (hiyerarşik olmayan)
 * - Her kategori birden fazla ürün içerebilir
 * - Kategori adları benzersiz olmalıdır
 *
 * İlişkiler:
 * - Product: Her kategori birden fazla ürün içerebilir (1:N)
 *
 * @property int $id Kategori benzersiz kimliği
 * @property string $name Kategori adı (benzersiz)
 * @property string|null $description Kategori açıklaması (opsiyonel)
 * @property \Carbon\Carbon $created_at Kategori oluşturulma tarihi
 * @property \Carbon\Carbon $updated_at Son güncellenme tarihi
 * @property \Illuminate\Database\Eloquent\Collection|Product[] $products Bu kategorideki ürünler
 */
class Category extends Model
{
    /**
     * Mass assignment için doldurulabilir alanlar
     *
     * Güvenlik için sadece bu alanlar toplu atama ile değiştirilebilir.
     * Name alanı benzersizlik kontrolü ile korunur (migration + validation).
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',         // Kategori adı (zorunlu, benzersiz)
        'description'   // Kategori açıklaması (opsiyonel)
    ];

    /**
     * API response'larında gizlenecek alanlar
     *
     * Şu an için gizli alan yok, ama gelecekte admin-only
     * alanlar eklenirse buraya eklenir (örn: internal_notes).
     *
     * @var array<string>
     */
    protected $hidden = [];

    /**
     * Bu kategoriye ait ürünler ile ilişki
     *
     * Bir kategori birden fazla ürün içerebilir.
     * Kategori silindiğinde ürünlerin ne olacağı business logic'e göre
     * belirlenir (cascade delete, null set, veya başka kategoriye taşıma).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Kategorideki ürün sayısını al
     *
     * Bu kategori altında kaç ürün olduğunu döndürür.
     * Admin panelinde ve kategori listelerinde kullanılabilir.
     *
     * @return int Bu kategorideki toplam ürün sayısı
     */
    public function getProductsCount()
    {
        return $this->products()->count();
    }

    /**
     * Kategorideki aktif (stokta olan) ürün sayısını al
     *
     * Sadece stoku pozitif olan ürünleri sayar.
     * Müşteri tarafında kategorilerin aktifliğini göstermek için kullanılır.
     *
     * @return int Bu kategorideki stokta olan ürün sayısı
     */
    public function getActiveProductsCount()
    {
        return $this->products()->where('stock_quantity', '>', 0)->count();
    }

    /**
     * Kategorinin boş olup olmadığını kontrol et
     *
     * Bu kategoride hiç ürün yoksa true döndürür.
     * Kategori silme işlemlerinde güvenlik kontrolü için kullanılabilir.
     *
     * @return bool Kategori boş ise true
     */
    public function isEmpty()
    {
        return $this->products()->count() === 0;
    }

    /**
     * Kategorinin URL dostu slug'ını oluştur
     *
     * Kategori adından SEO dostu URL slug'ı oluşturur.
     * Gelecekte slug alanı eklenir ise bu method kullanılabilir.
     *
     * @return string URL dostu slug
     */
    public function generateSlug()
    {
        return \Str::slug($this->name, '-');
    }

    /**
     * Kategori için özet bilgi döndür
     *
     * API response'larında ve admin panelinde kullanılmak üzere
     * kategori özet bilgilerini formatlar.
     *
     * @return array Formatlanmış kategori özet bilgisi
     */
    public function getSummary()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'products_count' => $this->getProductsCount(),
            'active_products_count' => $this->getActiveProductsCount(),
            'slug' => $this->generateSlug(),
            'created_at' => $this->created_at->format('d.m.Y'),
            'is_empty' => $this->isEmpty()
        ];
    }

    /**
     * En popüler kategorileri getir (scope)
     *
     * Ürün sayısına göre kategorileri popülerlikten fazlaya doğru sıralar.
     * Anasayfada popüler kategorileri göstermek için kullanılabilir.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $limit Gösterilecek maksimum kategori sayısı
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePopular($query, $limit = 10)
    {
        return $query->withCount('products')
            ->orderBy('products_count', 'desc')
            ->limit($limit);
    }

    /**
     * Aktif kategorileri getir (scope)
     *
     * İçerisinde en az bir ürün bulunan kategorileri döndürür.
     * Müşteri tarafında boş kategorileri gizlemek için kullanılır.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->has('products');
    }

    /**
     * Kategori adında arama yap (scope)
     *
     * Kategori adında verilen kelimeyi arar (case-insensitive).
     * Admin panelinde kategori aramak için kullanılabilir.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search Aranacak kelime
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'ILIKE', "%{$search}%")
            ->orWhere('description', 'ILIKE', "%{$search}%");
    }

    /**
     * Model eventi: Kategori silinmeden önce kontrol
     *
     * Kategori silinmeden önce iş kurallarını kontrol eder.
     * İçerisinde ürün varsa silmeyi engeller veya uyarı verir.
     *
     * @return void
     */
    protected static function booted()
    {
        static::deleting(function ($category) {
            // Eğer kategoride ürün varsa silme işlemini engelle
            if ($category->products()->count() > 0) {
                throw new \Exception(
                    "'{$category->name}' kategorisi silinemez. " .
                    "Bu kategoride {$category->getProductsCount()} ürün bulunmaktadır. " .
                    "Önce ürünleri başka kategorilere taşıyın."
                );
            }
        });
    }

    /**
     * String representation - Model yazdırıldığında kategori adını göster
     *
     * @return string Kategori adı
     */
    public function __toString()
    {
        return $this->name;
    }
}
