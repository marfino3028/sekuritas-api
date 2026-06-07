<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MutualFund extends Model
{
    use HasFactory;

    protected $table = 'mutual_funds';

    // ============================================
    // Konstanta Jenis Reksa Dana
    // ============================================
    const TYPE_MONEY_MARKET = 'money_market';
    const TYPE_FIXED_INCOME = 'fixed_income';
    const TYPE_BALANCED     = 'balanced';
    const TYPE_EQUITY       = 'equity';
    const TYPE_SHARIA       = 'sharia';

    /**
     * Label tampilan untuk setiap jenis reksa dana.
     */
    const TYPE_LABELS = [
        self::TYPE_MONEY_MARKET => 'Pasar Uang',
        self::TYPE_FIXED_INCOME => 'Pendapatan Tetap',
        self::TYPE_BALANCED     => 'Campuran',
        self::TYPE_EQUITY       => 'Saham',
        self::TYPE_SHARIA       => 'Syariah',
    ];

    /**
     * Label profil risiko produk (skala 1 Rendah s/d 5 Tinggi).
     */
    const RISK_LABELS = [
        1 => 'Rendah',
        2 => 'Menengah - Rendah',
        3 => 'Menengah',
        4 => 'Menengah - Tinggi',
        5 => 'Tinggi',
    ];

    protected $appends = ['fund_type_label', 'risk_label'];

    protected $fillable = [
        'fund_code',
        'name',
        'investment_manager',
        'custodian_bank',
        'fund_type',
        'risk_level',
        'nav_per_unit',
        'nav_date',
        'min_subscription',
        'min_redemption_unit',
        'management_fee',
        'subscription_fee',
        'redemption_fee',
        'total_aum',
        'performance_1yr',
        'performance_3yr',
        'performance_ytd',
        'is_syariah',
        'is_active',
        'description',
    ];

    protected $casts = [
        'nav_date'            => 'date',
        'risk_level'          => 'integer',
        'nav_per_unit'        => 'decimal:4',
        'min_subscription'    => 'decimal:2',
        'min_redemption_unit' => 'decimal:8',
        'management_fee'      => 'decimal:2',
        'subscription_fee'    => 'decimal:2',
        'redemption_fee'      => 'decimal:2',
        'total_aum'           => 'decimal:2',
        'performance_1yr'     => 'decimal:2',
        'performance_3yr'     => 'decimal:2',
        'performance_ytd'     => 'decimal:2',
        'is_syariah'          => 'boolean',
        'is_active'           => 'boolean',
    ];

    // ============================================
    // Relationships
    // ============================================

    /**
     * Histori NAV produk ini.
     */
    public function navHistories()
    {
        return $this->hasMany(NavHistory::class, 'fund_id')->orderBy('nav_date', 'desc');
    }

    /**
     * Portofolio investor yang memiliki produk ini.
     */
    public function portfolios()
    {
        return $this->hasMany(Portfolio::class, 'fund_id');
    }

    /**
     * Transaksi yang terkait dengan produk ini.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'fund_id');
    }

    // ============================================
    // Scopes
    // ============================================

    /**
     * Hanya tampilkan produk yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Filter berdasarkan jenis reksa dana.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('fund_type', $type);
    }

    /**
     * Filter produk syariah.
     */
    public function scopeSyariah($query, bool $isSyariah = true)
    {
        return $query->where('is_syariah', $isSyariah);
    }

    /**
     * Filter berdasarkan profil risiko (1..5).
     */
    public function scopeOfRisk($query, int $level)
    {
        return $query->where('risk_level', $level);
    }

    // ============================================
    // Accessors
    // ============================================

    /**
     * Label jenis reksa dana dalam Bahasa Indonesia.
     */
    public function getFundTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->fund_type] ?? $this->fund_type;
    }

    /**
     * Label profil risiko dalam Bahasa Indonesia.
     */
    public function getRiskLabelAttribute(): string
    {
        return self::RISK_LABELS[$this->risk_level] ?? 'Menengah';
    }

    /**
     * Hitung jumlah unit dari nominal pembelian.
     *
     * @param float $amount Jumlah uang dalam Rupiah
     * @return float
     */
    public function calculateUnits(float $amount): float
    {
        if ($this->nav_per_unit <= 0) return 0;
        return round($amount / $this->nav_per_unit, 8);
    }

    /**
     * Hitung nilai rupiah dari jumlah unit.
     *
     * @param float $units Jumlah unit
     * @return float
     */
    public function calculateValue(float $units): float
    {
        return round($units * $this->nav_per_unit, 2);
    }
}
