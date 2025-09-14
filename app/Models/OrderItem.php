<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OrderItem Model - Sipariş Kalemi Modeli
 *
 * Bu model sipariş içerisindeki her bir ürün kalemini temsil eder.
 * OrderItem, sepetteki CartItem'ların sipariş onaylandığında dönüştüğü
 * kalıcı kayıtlardır ve sipariş geçmişinin korunması için kritiktir.
 *
 * Önemli Özellikler:
 * - Sipariş anındaki ürün fiyatını saklar (price field)
 * - Ürün bilgileri değişse bile sipariş kaydı korunur
 * - Stok düşürme işlemlerinin kaynağıdır
 * - İade ve değişim işlemlerinin temelini oluşturur
 *
 * İlişkiler:
 * - Order: Her sipariş kalemi bir siparişe ait (N:1)
 * - Product: Her sipariş kalemi bir ürünü referans eder (N:1)
 *
 * @property int $id Sipariş kalemi benzersiz kimliği
 * @property int $order_id Ait olduğu sipariş ID'si
 * @property int $product_id Sipariş edilen ürün ID'si
 * @property int $quantity Sipariş edilen ürün miktarı
 * @property float $price Sipariş anındaki ürün birim fiyatı (TL)
 * @property \Carbon\Carbon $created_at Sipariş kalemi oluşturulma tarihi
 * @property \Carbon\Carbon $updated_at Son güncellenme tarihi
 * @property Order $order Ait olduğu sipariş
 * @property Product $product Sipariş edilen ürün
 */
class OrderItem extends Model
{
    /**
     * Mass assignment için doldurulabilir alanlar
     *
     * Güvenlik için sadece bu alanlar toplu atama ile değiştirilebilir.
     * Price alanı sipariş anındaki fiyatı saklar ve sonradan değişmez.
     *
     * @var array<string>
     */
    protected $fillable = [
        'order_id',     // Ait olduğu sipariş
        'product_id',   // Sipariş edilen ürün
        'quantity',     // Sipariş edilen miktar
        'price'         // Sipariş anındaki birim fiyat (değişmez!)
    ];

    /**
     * Attribute casting - Veri tiplerini otomatik dönüştür
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',    // Miktar tam sayı
        'price' => 'decimal:2'      // Fiyat 2 ondalık basamak ile float
    ];

    // ================================
    // İLİŞKİLER (RELATIONSHIPS)
    // ================================

    /**
     * Ait olduğu sipariş ile ilişki
     *
     * Her sipariş kalemi mutlaka bir siparişe ait olmalıdır.
     * Sipariş silindiğinde tüm sipariş kalemleri de silinmelidir (cascade).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Sipariş edilen ürün ile ilişki
     *
     * Sipariş kalemi mevcut bir ürünü referans eder.
     * Ürün silinse bile sipariş kaydı korunmalıdır (soft delete veya null set).
     * Bu sayede sipariş geçmişi korunur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // ================================
    // HESAPLAMA METHODLARİ
    // ================================

    /**
     * Sipariş kaleminin alt toplamını hesapla
     *
     * Bu sipariş kalemi için toplam tutarı hesaplar.
     * Hesaplama: sipariş_anındaki_fiyat × miktar
     *
     * Not: Ürünün güncel fiyatı değil, sipariş anındaki fiyat kullanılır.
     * Bu sayede sipariş sonrası fiyat değişiklikleri sipariş tutarını etkilemez.
     *
     * @return float Bu kalem için toplam tutar (TL cinsinden)
     */
    public function getSubtotal()
    {
        return $this->price * $this->quantity;
    }

    /**
     * Formatlanmış alt toplam döndür
     *
     * @param string $currency Para birimi sembolü
     * @return string Formatlanmış alt toplam (örn: "199,90 TL")
     */
    public function getFormattedSubtotal($currency = 'TL')
    {
        return number_format($this->getSubtotal(), 2, ',', '.') . ' ' . $currency;
    }

    /**
     * Formatlanmış birim fiyat döndür
     *
     * @param string $currency Para birimi sembolü
     * @return string Formatlanmış birim fiyat
     */
    public function getFormattedPrice($currency = 'TL')
    {
        return number_format($this->price, 2, ',', '.') . ' ' . $currency;
    }

    // ================================
    // FİYAT KARŞILAŞTIRMA
    // ================================

    /**
     * Sipariş anındaki fiyat ile güncel fiyat arasındaki farkı hesapla
     *
     * Sipariş sonrası ürün fiyatının ne kadar değiştiğini gösterir.
     * Pozitif değer: ürün zamlandı, Negatif değer: ürün ucuzladı
     *
     * @return float|null Fiyat farkı (ürün silinmişse null)
     */
    public function getPriceDifference()
    {
        if (!$this->product) {
            return null; // Ürün silinmiş
        }

        return $this->product->price - $this->price;
    }

    /**
     * Sipariş anındaki fiyat ile güncel fiyat arasındaki yüzde farkını hesapla
     *
     * @return float|null Yüzde farkı (ürün silinmişse null)
     */
    public function getPriceDifferencePercentage()
    {
        if (!$this->product || $this->price == 0) {
            return null;
        }

        return (($this->product->price - $this->price) / $this->price) * 100;
    }

    /**
     * Ürün fiyatının artıp artmadığını kontrol et
     *
     * @return bool Fiyat artmışsa true
     */
    public function isPriceIncreased()
    {
        return $this->getPriceDifference() > 0;
    }

    /**
     * Ürün fiyatının azalıp azalmadığını kontrol et
     *
     * @return bool Fiyat azalmışsa true
     */
    public function isPriceDecreased()
    {
        return $this->getPriceDifference() < 0;
    }

    // ================================
    // ÜRÜN DURUMU KONTROLLARI
    // ================================

    /**
     * Sipariş edilen ürünün hala mevcut olup olmadığını kontrol et
     *
     * @return bool Ürün hala sistemde varsa true
     */
    public function isProductStillAvailable()
    {
        return $this->product !== null;
    }

    /**
     * Sipariş edilen ürünün hala stokta olup olmadığını kontrol et
     *
     * @return bool Ürün stokta varsa true
     */
    public function isProductInStock()
    {
        return $this->product && $this->product->isInStock();
    }

    /**
     * Aynı miktarda tekrar sipariş vermeye yetecek stok var mı?
     *
     * @return bool Yeterli stok varsa true
     */
    public function canReorder()
    {
        return $this->product && $this->product->hasEnoughStock($this->quantity);
    }

    // ================================
    // İADE VE DEĞİŞİM
    // ================================

    /**
     * Bu sipariş kalemi için toplam iade edilebilir tutarı hesapla
     *
     * İade işlemlerinde sipariş anındaki fiyat kullanılır.
     *
     * @param int $returnQuantity İade edilecek miktar (varsayılan: tümü)
     * @return float İade tutarı
     */
    public function getReturnAmount($returnQuantity = null)
    {
        $returnQty = $returnQuantity ?? $this->quantity;
        return $this->price * min($returnQty, $this->quantity);
    }

    /**
     * Bu kalem için kısmi iade yapılabilir mi?
     *
     * @return bool Miktar 1'den fazlaysa true
     */
    public function canPartialReturn()
    {
        return $this->quantity > 1;
    }

    // ================================
    // YARDIMCI METHODLAR
    // ================================

    /**
     * Sipariş kalemi için detaylı bilgi döndür
     *
     * API response'larında ve raporlarda kullanılmak üzere
     * sipariş kalemi bilgilerini formatlar.
     *
     * @return array Formatlanmış sipariş kalemi bilgisi
     */
    public function getDetailedInfo()
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product->name ?? 'Silinmiş Ürün',
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->price,
            'formatted_unit_price' => $this->getFormattedPrice(),
            'subtotal' => $this->getSubtotal(),
            'formatted_subtotal' => $this->getFormattedSubtotal(),
            'order_date' => $this->created_at->format('d.m.Y'),

            // Ürün durumu
            'product_available' => $this->isProductStillAvailable(),
            'product_in_stock' => $this->isProductInStock(),
            'can_reorder' => $this->canReorder(),

            // Fiyat karşılaştırması
            'current_price' => $this->product->price ?? null,
            'price_difference' => $this->getPriceDifference(),
            'price_change_percentage' => $this->getPriceDifferencePercentage(),
            'price_increased' => $this->isPriceIncreased(),
            'price_decreased' => $this->isPriceDecreased(),

            // İade bilgileri
            'can_partial_return' => $this->canPartialReturn(),
            'full_return_amount' => $this->getReturnAmount()
        ];
    }

    // ================================
    // SCOPE'LAR
    // ================================

    /**
     * Belirli siparişteki kalemleri getir
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $orderId Sipariş ID'si
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Belirli ürünün sipariş kalemlerini getir
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $productId Ürün ID'si
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Yüksek tutarlı sipariş kalemlerini getir
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $minAmount Minimum tutar
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHighValue($query, $minAmount = 1000)
    {
        return $query->whereRaw('price * quantity >= ?', [$minAmount]);
    }

    // ================================
    // MODEL EVENTLERİ
    // ================================

    /**
     * Model eventi: Sipariş kalemi oluşturulmadan önce validasyon
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($orderItem) {
            // Miktar pozitif olmalı
            if ($orderItem->quantity <= 0) {
                throw new \Exception('Sipariş kalemi miktarı pozitif olmalıdır');
            }

            // Fiyat pozitif olmalı
            if ($orderItem->price < 0) {
                throw new \Exception('Sipariş kalemi fiyatı negatif olamaz');
            }
        });
    }

    /**
     * String representation
     *
     * @return string Sipariş kalemi açıklaması
     */
    public function __toString()
    {
        $productName = $this->product->name ?? 'Silinmiş Ürün';
        return "{$this->quantity}x {$productName} - {$this->getFormattedSubtotal()}";
    }
}
