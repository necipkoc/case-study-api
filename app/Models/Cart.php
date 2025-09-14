<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cart Model - Kullanıcı Sepeti Modeli
 *
 * Bu model e-ticaret platformunda kullanıcıların alışveriş sepetlerini temsil eder.
 * Her kullanıcının tek bir aktif sepeti bulunur ve sepet içerisinde birden fazla
 * ürün (CartItem) yer alabilir.
 *
 * İlişkiler:
 * - User: Her sepet bir kullanıcıya ait (1:1)
 * - CartItem: Her sepet birden fazla sepet kalemi içerebilir (1:N)
 *
 * @property int $id Sepet benzersiz kimliği
 * @property int $user_id Sepet sahibi kullanıcının ID'si
 * @property \Carbon\Carbon $created_at Sepet oluşturulma tarihi
 * @property \Carbon\Carbon $updated_at Son güncellenme tarihi
 * @property User $user Sepet sahibi kullanıcı
 * @property \Illuminate\Database\Eloquent\Collection|CartItem[] $items Sepetteki ürün kalemleri
 */
class Cart extends Model
{
    /**
     * Mass assignment için doldurulabilir alanlar
     *
     * Güvenlik için sadece bu alanlar toplu atama ile değiştirilebilir.
     * user_id alanı sepet oluşturulurken otomatik olarak atanır.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id'
    ];

    /**
     * Sepet sahibi kullanıcı ile ilişki
     *
     * Her sepet mutlaka bir kullanıcıya ait olmalıdır.
     * Kullanıcı silindiğinde sepet de silinmelidir (cascade).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sepetteki ürün kalemleri ile ilişki
     *
     * Bir sepet birden fazla ürün kalemi (CartItem) içerebilir.
     * Her CartItem bir ürünü ve miktarını temsil eder.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Sepettin toplam fiyatını hesapla
     *
     * Sepetteki tüm ürünlerin (fiyat x miktar) toplamını hesaplar.
     * Bu method her çağrıldığında gerçek zamanlı hesaplama yapar.
     * Performans için cache'lenebilir ancak stok/fiyat değişikliklerinde
     * tutarlılık sağlamak adına dinamik hesaplama tercih edilir.
     *
     * Hesaplama formülü: (ürün_fiyatı × miktar)
     *
     * @return float Sepettin toplam tutarı (TL cinsinden)
     */
    public function getTotalPrice()
    {
        return $this->items->sum(function ($item) {
            // Her sepet kalemi için: ürün fiyatı × miktar
            return $item->product->price * $item->quantity;
        });
    }

    /**
     * Sepetteki toplam ürün adedini hesapla
     *
     * Sepetteki tüm ürünlerin toplam miktarını döndürür.
     * Örneğin: 3 adet telefon + 2 adet kulaklık = 5 toplam ürün
     *
     * Bu değer sepet badge'inde veya sipariş özet bilgilerinde
     * kullanılabilir.
     *
     * @return int Sepetteki toplam ürün adedi
     */
    public function getTotalItems()
    {
        // Tüm sepet kalemlerinin quantity değerlerinin toplamı
        return $this->items->sum('quantity');
    }

    /**
     * Sepettin boş olup olmadığını kontrol et
     *
     * Sepette hiç ürün yoksa true, varsa false döndürür.
     * Sipariş oluşturma öncesi kontrol için kullanılabilir.
     *
     * @return bool Sepet boş ise true
     */
    public function isEmpty()
    {
        return $this->items->isEmpty();
    }

    /**
     * Sepetteki benzersiz ürün sayısını al
     *
     * Sepette kaç farklı ürün olduğunu döndürür.
     * Örneğin: 3 adet telefon + 2 adet kulaklık = 2 benzersiz ürün
     *
     * @return int Benzersiz ürün sayısı
     */
    public function getUniqueItemsCount()
    {
        return $this->items->count();
    }

    /**
     * Belirli bir ürünün sepette olup olmadığını kontrol et
     *
     * @param int $productId Kontrol edilecek ürünün ID'si
     * @return bool Ürün sepette varsa true
     */
    public function hasProduct($productId)
    {
        return $this->items()->where('product_id', $productId)->exists();
    }

    /**
     * Belirli bir ürünün sepetteki miktarını al
     *
     * @param int $productId Miktarı öğrenilecek ürünün ID'si
     * @return int Ürünün sepetteki miktarı (yoksa 0)
     */
    public function getProductQuantity($productId)
    {
        $item = $this->items()->where('product_id', $productId)->first();
        return $item ? $item->quantity : 0;
    }
}
