<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CartItem Model - Sepet Kalemi Modeli
 *
 * Bu model alışveriş sepetindeki her bir ürün kalemini temsil eder.
 * Bir sepet kalemi, belirli bir ürünün sepetteki miktarını ve
 * o ürünle ilgili sepet özel bilgilerini tutar.
 *
 * İlişkiler:
 * - Cart: Her sepet kalemi bir sepete ait (N:1)
 * - Product: Her sepet kalemi bir ürünü referans eder (N:1)
 *
 * İş Kuralları:
 * - Aynı sepette aynı ürün sadece bir kez bulunur (miktar artırılır)
 * - Miktar pozitif bir tam sayı olmalıdır
 * - Ürün stok durumu sepet kalemini etkilemez (stok kontrolü işlem anında yapılır)
 *
 * @property int $id Sepet kalemi benzersiz kimliği
 * @property int $cart_id Ait olduğu sepet ID'si
 * @property int $product_id Referans verilen ürün ID'si
 * @property int $quantity Sepetteki ürün miktarı
 * @property \Carbon\Carbon $created_at Sepete eklenme tarihi
 * @property \Carbon\Carbon $updated_at Son güncellenme tarihi
 * @property Cart $cart Ait olduğu sepet
 * @property Product $product Referans verilen ürün
 */
class CartItem extends Model
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
        'cart_id',      // Ait olduğu sepet
        'product_id',   // Sepetteki ürün
        'quantity'      // Ürün miktarı
    ];

    /**
     * Attribute casting - Veri tiplerini otomatik dönüştür
     *
     * Veritabanından gelen string değerleri uygun PHP tiplerine
     * otomatik olarak dönüştürür. Bu sayede type safety sağlanır.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer'     // Miktar her zaman tam sayı olarak dönsün
    ];

    /**
     * Ait olduğu sepet ile ilişki
     *
     * Her sepet kalemi mutlaka bir sepete ait olmalıdır.
     * Sepet silindiğinde tüm sepet kalemleri de silinmelidir (cascade).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Referans verilen ürün ile ilişki
     *
     * Her sepet kalemi mutlaka mevcut bir ürünü referans etmelidir.
     * Ürün silinirse sepet kalemi ne yapılacağı business logic'e göre
     * belirlenir (cascade delete veya soft delete).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Sepet kaleminin alt toplamını hesapla
     *
     * Bu sepet kalemi için toplam tutarı hesaplar.
     * Hesaplama: ürün_fiyatı × miktar
     *
     * Not: Ürün fiyatı gerçek zamanlı olarak alınır, bu sayede
     * fiyat değişiklikleri sepet toplam hesaplamalarına yansır.
     * Sipariş oluşturulduğunda fiyat sabitlenerek order_items tablosuna kaydedilir.
     *
     * @return float Bu kalem için toplam tutar (TL cinsinden)
     */
    public function getSubtotal()
    {
        return $this->product->price * $this->quantity;
    }

    /**
     * Sepet kaleminin stok durumunu kontrol et
     *
     * Bu sepet kalemindeki miktar, ürünün mevcut stoğu ile
     * karşılaştırılarak stok yeterliliği kontrol edilir.
     *
     * @return bool Stok yeterli ise true, yetersiz ise false
     */
    public function isStockAvailable()
    {
        return $this->product->stock_quantity >= $this->quantity;
    }

    /**
     * Maksimum eklenebilir miktarı hesapla
     *
     * Bu ürün için sepete maksimum kaç adet daha eklenebileceğini
     * mevcut stok durumuna göre hesaplar.
     *
     * @return int Eklenebilir maksimum miktar
     */
    public function getAvailableQuantityToAdd()
    {
        return max(0, $this->product->stock_quantity - $this->quantity);
    }

    /**
     * Sepet kalemi oluşturulmadan önce validasyon
     *
     * Model seviyesinde iş kurallarını kontrol eder.
     * Controller validasyonundan sonra ek güvenlik katmanı sağlar.
     *
     * @return void
     * @throws \Exception Validasyon hatası durumunda
     */
    protected static function booted()
    {
        static::saving(function ($cartItem) {
            // Miktar pozitif olmalı
            if ($cartItem->quantity <= 0) {
                throw new \Exception('Sepet kalemi miktarı pozitif olmalıdır');
            }

            // Aynı sepette aynı ürün sadece bir kez bulunabilir
            $existingItem = static::where('cart_id', $cartItem->cart_id)
                ->where('product_id', $cartItem->product_id)
                ->where('id', '!=', $cartItem->id)
                ->first();

            if ($existingItem) {
                throw new \Exception('Bu ürün zaten sepetinizde bulunmaktadır');
            }
        });
    }

    /**
     * Sepet kalemi için formatlı bilgi döndür
     *
     * API response'larında kullanılmak üzere
     * sepet kalemi bilgilerini formatlar.
     *
     * @return array Formatlanmış sepet kalemi bilgisi
     */
    public function getFormattedInfo()
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product->name,
            'product_price' => $this->product->price,
            'quantity' => $this->quantity,
            'subtotal' => $this->getSubtotal(),
            'is_stock_available' => $this->isStockAvailable(),
            'max_available' => $this->getAvailableQuantityToAdd(),
            'added_at' => $this->created_at->format('d.m.Y H:i')
        ];
    }
}
