<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    // ============================================
    // Konstanta Jenis Transaksi
    // ============================================
    const TYPE_SUBSCRIPTION = 'subscription'; // Pembelian unit
    const TYPE_REDEMPTION   = 'redemption';   // Penjualan unit

    // ============================================
    // Konstanta Status Transaksi
    // ============================================
    const STATUS_PENDING    = 'pending';     // Menunggu pembayaran
    const STATUS_PAID       = 'paid';        // Sudah bayar, menunggu settle
    const STATUS_PROCESSING = 'processing';  // Sedang diproses oleh sistem
    const STATUS_SETTLED    = 'settled';     // Selesai, unit teralokasi
    const STATUS_FAILED     = 'failed';      // Gagal atau expired

    protected $fillable = [
        'user_id',
        'fund_id',
        'type',
        'amount',
        'units',
        'nav_price',
        'fee_amount',
        'status',
        'payment_method',
        'payment_code',
        'payment_expired_at',
        'payment_confirmed_at',
        'settled_at',
        'notes',
        'order_number',
    ];

    protected $casts = [
        'amount'               => 'decimal:2',
        'units'                => 'decimal:8',
        'nav_price'            => 'decimal:4',
        'fee_amount'           => 'decimal:2',
        'payment_expired_at'   => 'datetime',
        'payment_confirmed_at' => 'datetime',
        'settled_at'           => 'datetime',
    ];

    // ============================================
    // Boot: Auto-generate order number
    // ============================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->order_number)) {
                // Format: SKR-S-000069 (subscription) atau SKR-R-000069 (redemption)
                // Sequential per tipe transaksi agar user bisa hafal ID-nya
                $typeCode = $transaction->type === self::TYPE_SUBSCRIPTION ? 'S' : 'R';
                $lastOrder = static::where('type', $transaction->type)
                    ->whereNotNull('order_number')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->value('order_number');

                $lastSeq = 0;
                if ($lastOrder && preg_match('/SKR-[SR]-(\d+)/', $lastOrder, $m)) {
                    $lastSeq = (int) $m[1];
                }

                $nextSeq = str_pad($lastSeq + 1, 6, '0', STR_PAD_LEFT);
                $transaction->order_number = "SKR-{$typeCode}-{$nextSeq}";
            }
        });
    }

    // ============================================
    // Relationships
    // ============================================

    /**
     * Nasabah pemilik transaksi.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Produk reksa dana yang ditransaksikan.
     */
    public function fund()
    {
        return $this->belongsTo(MutualFund::class, 'fund_id');
    }

    /**
     * Data pembayaran terkait transaksi ini.
     */
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    // ============================================
    // Scopes
    // ============================================

    /**
     * Filter berdasarkan status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Filter hanya subscription.
     */
    public function scopeSubscriptions($query)
    {
        return $query->where('type', self::TYPE_SUBSCRIPTION);
    }

    /**
     * Filter hanya redemption.
     */
    public function scopeRedemptions($query)
    {
        return $query->where('type', self::TYPE_REDEMPTION);
    }

    // ============================================
    // Helper Methods
    // ============================================

    /**
     * Cek apakah transaksi sudah expired (belum bayar > batas waktu).
     */
    public function isExpired(): bool
    {
        if ($this->status !== self::STATUS_PENDING) return false;
        if (!$this->payment_expired_at) return false;
        return now()->isAfter($this->payment_expired_at);
    }

    /**
     * Hitung total yang harus dibayar nasabah (amount + fee).
     */
    public function getTotalPayableAttribute(): float
    {
        return (float) $this->amount + (float) $this->fee_amount;
    }
}
