<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fund_id',
        'total_units',
        'avg_nav',
        'current_value',
        'unrealized_gain',
        'total_invested',
    ];

    protected $casts = [
        'total_units'     => 'decimal:8',
        'avg_nav'         => 'decimal:4',
        'current_value'   => 'decimal:2',
        'unrealized_gain' => 'decimal:2',
        'total_invested'  => 'decimal:2',
    ];

    // ============================================
    // Relationships
    // ============================================

    /**
     * Pemilik portofolio.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Produk reksa dana dalam portofolio.
     */
    public function fund()
    {
        return $this->belongsTo(MutualFund::class, 'fund_id');
    }

    // ============================================
    // Methods: Kalkulasi portofolio
    // ============================================

    /**
     * Perbarui nilai pasar dan unrealized gain/loss berdasarkan NAV terkini.
     *
     * @param float $currentNav NAV per unit terkini dari produk
     */
    public function updateMarketValue(float $currentNav): void
    {
        $this->current_value   = round($this->total_units * $currentNav, 2);
        $this->unrealized_gain = round($this->current_value - $this->total_invested, 2);
        $this->save();
    }

    /**
     * Tambah unit ke portofolio (setelah subscription settle).
     * Menghitung ulang rata-rata NAV pembelian.
     *
     * @param float $units     Jumlah unit baru
     * @param float $navPrice  NAV saat pembelian
     * @param float $amount    Nominal investasi dalam Rupiah
     */
    public function addUnits(float $units, float $navPrice, float $amount): void
    {
        // Hitung rata-rata NAV tertimbang (weighted average)
        $totalUnitsBefore = (float) $this->total_units;
        $totalUnitsAfter  = $totalUnitsBefore + $units;

        if ($totalUnitsAfter > 0) {
            $this->avg_nav = (
                ($totalUnitsBefore * (float) $this->avg_nav) + ($units * $navPrice)
            ) / $totalUnitsAfter;
        }

        $this->total_units    = round($totalUnitsAfter, 8);
        $this->total_invested = round((float) $this->total_invested + $amount, 2);
        $this->save();
    }

    /**
     * Kurangi unit dari portofolio (setelah redemption settle).
     *
     * @param float $units  Jumlah unit yang dijual
     * @param float $amount Nilai penjualan dalam Rupiah
     */
    public function removeUnits(float $units, float $amount): void
    {
        $unitsBefore = (float) $this->total_units;
        $proportion  = $unitsBefore > 0 ? ($units / $unitsBefore) : 0;

        $this->total_units    = round(max(0, $unitsBefore - $units), 8);
        // Kurangi modal secara proporsional
        $this->total_invested = round(max(0, (float) $this->total_invested - ($proportion * (float) $this->total_invested)), 2);
        $this->save();
    }

    // ============================================
    // Accessors
    // ============================================

    /**
     * Return on Investment dalam persen.
     */
    public function getRoiPercentAttribute(): float
    {
        if ((float) $this->total_invested <= 0) return 0.0;
        return round(((float) $this->unrealized_gain / (float) $this->total_invested) * 100, 2);
    }
}
