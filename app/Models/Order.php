<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * Order Model - Sipariş Modeli
 *
 * Bu model e-ticaret platformundaki siparişleri temsil eder.
 * Sipariş, kullanıcının sepetindeki ürünlerin satın alınması sonucu
 * oluşan resmi kayıttır ve tüm iş süreçlerinin merkezindedir.
 *
 * Sipariş Yaşam Döngüsü:
 * 1. pending (Beklemede) - Yeni oluşturulan sipariş
 * 2. confirmed (Onaylandı) - Ödeme ve stok kontrolü tamamlandı
 * 3. shipped (Kargoya Verildi) - Sipariş kargo firmasına teslim edildi
 * 4. delivered (Teslim Edildi) - Müşteriye ulaştı
 * 5. cancelled (İptal Edildi) - Sipariş iptal edildi (stoklar iade edilir)
 *
 * İlişkiler:
 * - User: Her sipariş bir kullanıcıya ait (N:1)
 * - OrderItem: Her sipariş birden fazla ürün kalemi içerir (1:N)
 *
 * @property int $id Sipariş benzersiz kimliği
 * @property int $user_id Sipariş veren kullanıcının ID'si
 * @property float $total_amount Sipariş toplam tutarı (TL)
 * @property string $status Sipariş durumu (pending, confirmed, shipped, delivered, cancelled)
 * @property \Carbon\Carbon $created_at Sipariş oluşturulma tarihi
 * @property \Carbon\Carbon $updated_at Son güncellenme tarihi
 * @property User $user Sipariş veren kullanıcı
 * @property \Illuminate\Database\Eloquent\Collection|OrderItem[] $items Sipariş kalemleri
 */
class Order extends Model
{
    /**
     * Sipariş durumları sabit değerleri
     *
     * Sipariş durumlarını sabit olarak tanımlayarak
     * kod içerisinde magic string kullanımını önler.
     *
     * @var array<string, string>
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Tüm sipariş durumları
     *
     * @var array<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED
    ];

    /**
     * Mass assignment için doldurulabilir alanlar
     *
     * Güvenlik için sadece bu alanlar toplu atama ile değiştirilebilir.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',      // Sipariş veren kullanıcı
        'total_amount', // Sipariş toplam tutarı
        'status'        // Sipariş durumu
    ];

    /**
     * Attribute casting - Veri tiplerini otomatik dönüştür
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_amount' => 'decimal:2'   // Toplam tutar 2 ondalık basamak ile float
    ];

    // ================================
    // İLİŞKİLER (RELATIONSHIPS)
    // ================================

    /**
     * Sipariş veren kullanıcı ile ilişki
     *
     * Her sipariş mutlaka bir kullanıcıya ait olmalıdır.
     * Kullanıcı silindiğinde siparişlerin ne olacağı business logic'e göre belirlenir
     * (genellikle siparişler korunur, kullanıcı anonymize edilir).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sipariş kalemleri ile ilişki
     *
     * Bir sipariş birden fazla ürün kalemi içerebilir.
     * Sipariş silindiğinde tüm kalemleri de silinmelidir (cascade).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    // ================================
    // HESAPLAMA METHODLARİ
    // ================================

    /**
     * Siparişteki toplam ürün adedini hesapla
     *
     * Tüm order item'ların quantity değerlerinin toplamını döndürür.
     * Örneğin: 3 adet telefon + 2 adet kulaklık = 5 toplam ürün
     *
     * @return int Siparişteki toplam ürün adedi
     */
    public function getTotalItems()
    {
        return $this->items->sum('quantity');
    }

    /**
     * Siparişteki benzersiz ürün sayısını al
     *
     * Siparişteki farklı ürün çeşidi sayısını döndürür.
     * Örneğin: 3 adet telefon + 2 adet kulaklık = 2 benzersiz ürün
     *
     * @return int Benzersiz ürün sayısı
     */
    public function getUniqueItemsCount()
    {
        return $this->items->count();
    }

    /**
     * Sipariş tutarını yeniden hesapla
     *
     * Order item'lardan toplam tutarı yeniden hesaplayarak
     * total_amount alanını günceller. Veri tutarlılığı için kullanılır.
     *
     * @return float Yeni toplam tutar
     */
    public function recalculateTotal()
    {
        $newTotal = $this->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        $this->update(['total_amount' => $newTotal]);

        return $newTotal;
    }

    // ================================
    // DURUM YÖNETİMİ
    // ================================

    /**
     * Sipariş durumu Türkçe metni döndür
     *
     * Sistem içerisindeki İngilizce durum kodlarını
     * kullanıcı dostu Türkçe metinlere çevirir.
     *
     * @return string Türkçe durum metni
     */
    public function getStatusText()
    {
        $statuses = [
            self::STATUS_PENDING => 'Beklemede',
            self::STATUS_CONFIRMED => 'Onaylandı',
            self::STATUS_SHIPPED => 'Kargoya Verildi',
            self::STATUS_DELIVERED => 'Teslim Edildi',
            self::STATUS_CANCELLED => 'İptal Edildi'
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    /**
     * Sipariş durumu renk kodu döndür (UI için)
     *
     * @return string CSS class veya renk kodu
     */
    public function getStatusColor()
    {
        $colors = [
            self::STATUS_PENDING => 'warning',      // Sarı
            self::STATUS_CONFIRMED => 'info',       // Mavi
            self::STATUS_SHIPPED => 'primary',      // Lacivert
            self::STATUS_DELIVERED => 'success',    // Yeşil
            self::STATUS_CANCELLED => 'danger'      // Kırmızı
        ];

        return $colors[$this->status] ?? 'secondary';
    }

    /**
     * Sipariş durumu ikon döndür (UI için)
     *
     * @return string Font Awesome ikon class'ı
     */
    public function getStatusIcon()
    {
        $icons = [
            self::STATUS_PENDING => 'fa-clock',
            self::STATUS_CONFIRMED => 'fa-check-circle',
            self::STATUS_SHIPPED => 'fa-shipping-fast',
            self::STATUS_DELIVERED => 'fa-thumbs-up',
            self::STATUS_CANCELLED => 'fa-times-circle'
        ];

        return $icons[$this->status] ?? 'fa-question';
    }

    // ================================
    // DURUM KONTROL METHODLARİ
    // ================================

    /**
     * Sipariş beklemede mi?
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Sipariş onaylandı mı?
     */
    public function isConfirmed()
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Sipariş kargoya verildi mi?
     */
    public function isShipped()
    {
        return $this->status === self::STATUS_SHIPPED;
    }

    /**
     * Sipariş teslim edildi mi?
     */
    public function isDelivered()
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Sipariş iptal edildi mi?
     */
    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Sipariş iptal edilebilir mi?
     *
     * Sadece beklemede olan siparişler iptal edilebilir.
     *
     * @return bool İptal edilebilir ise true
     */
    public function canBeCancelled()
    {
        return $this->isPending();
    }

    /**
     * Sipariş tamamlandı mı? (teslim edildi veya iptal edildi)
     */
    public function isCompleted()
    {
        return in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    // ================================
    // TARİH VE ZAMAN İŞLEMLERİ
    // ================================

    /**
     * Sipariş ne kadar süre önce verildi?
     *
     * @return string İnsan okunabilir zaman farkı
     */
    public function getTimeAgo()
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Formatlanmış sipariş tarihi döndür
     *
     * @param string $format Tarih formatı
     * @return string Formatlanmış tarih
     */
    public function getFormattedDate($format = 'd.m.Y H:i')
    {
        return $this->created_at->format($format);
    }

    /**
     * Tahmini teslimat tarihi hesapla
     *
     * @param int $workingDays İş günü cinsinden teslimat süresi
     * @return Carbon Tahmini teslimat tarihi
     */
    public function getEstimatedDeliveryDate($workingDays = 3)
    {
        return $this->created_at->addWeekdays($workingDays);
    }

    // ================================
    // ARAMA VE FİLTRELEME (SCOPES)
    // ================================

    /**
     * Belirli durumdaki siparişleri getir
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status Sipariş durumu
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Beklemedeki siparişleri getir
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Tamamlanan siparişleri getir (teslim edildi + iptal edildi)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    /**
     * Tarih aralığındaki siparişleri getir
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon $startDate Başlangıç tarihi
     * @param Carbon $endDate Bitiş tarihi
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBetweenDates($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Bugünkü siparişleri getir
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    // ================================
    // YARDIMCI METHODLAR
    // ================================

    /**
     * Sipariş için özet bilgi döndür
     *
     * @return array Formatlanmış sipariş bilgisi
     */
    public function getSummary()
    {
        return [
            'id' => $this->id,
            'order_number' => $this->getOrderNumber(),
            'total_amount' => $this->total_amount,
            'formatted_amount' => $this->getFormattedAmount(),
            'status' => $this->status,
            'status_text' => $this->getStatusText(),
            'status_color' => $this->getStatusColor(),
            'status_icon' => $this->getStatusIcon(),
            'total_items' => $this->getTotalItems(),
            'unique_items' => $this->getUniqueItemsCount(),
            'customer_name' => $this->user->name ?? 'Bilinmeyen',
            'order_date' => $this->getFormattedDate(),
            'time_ago' => $this->getTimeAgo(),
            'can_cancel' => $this->canBeCancelled(),
            'is_completed' => $this->isCompleted()
        ];
    }

    /**
     * Sipariş numarası oluştur
     *
     * @return string Sipariş numarası (örn: "2024-0001")
     */
    public function getOrderNumber()
    {
        return $this->created_at->format('Y') . '-' . str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Formatlanmış sipariş tutarı döndür
     *
     * @param string $currency Para birimi
     * @return string Formatlanmış tutar
     */
    public function getFormattedAmount($currency = 'TL')
    {
        return number_format($this->total_amount, 2, ',', '.') . ' ' . $currency;
    }

    /**
     * String representation
     *
     * @return string Sipariş numarası
     */
    public function __toString()
    {
        return "Sipariş #{$this->getOrderNumber()}";
    }
}
