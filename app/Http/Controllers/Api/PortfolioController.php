<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * PortfolioController — Portofolio investasi reksa dana nasabah.
 */
class PortfolioController extends Controller
{
    /**
     * Daftar portofolio reksa dana milik user.
     * Setiap baris mewakili satu produk yang dimiliki.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user       = JWTAuth::user();
        $portfolios = Portfolio::where('user_id', $user->id)
            ->where('total_units', '>', 0)
            ->with([
                'fund:id,fund_code,name,fund_type,nav_per_unit,nav_date,is_syariah',
            ])
            ->get();

        // Update nilai pasar berdasarkan NAV terkini
        foreach ($portfolios as $portfolio) {
            if ($portfolio->fund) {
                $portfolio->updateMarketValue((float) $portfolio->fund->nav_per_unit);
                $portfolio->refresh();
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $portfolios->map(fn ($p) => array_merge($p->toArray(), [
                'roi_percent'     => $p->roi_percent,
                'fund_type_label' => $p->fund->fund_type_label ?? '',
            ])),
        ]);
    }

    /**
     * Ringkasan total portofolio nasabah.
     * Menampilkan total nilai investasi, keuntungan/kerugian, dan distribusi per jenis reksa dana.
     *
     * @return JsonResponse
     */
    public function summary(): JsonResponse
    {
        $user       = JWTAuth::user();
        $portfolios = Portfolio::where('user_id', $user->id)
            ->where('total_units', '>', 0)
            ->with('fund')
            ->get();

        // Update semua market value
        foreach ($portfolios as $portfolio) {
            if ($portfolio->fund) {
                $portfolio->updateMarketValue((float) $portfolio->fund->nav_per_unit);
                $portfolio->refresh();
            }
        }

        $totalInvested      = $portfolios->sum('total_invested');
        $totalCurrentValue  = $portfolios->sum('current_value');
        $totalUnrealizedGain= $portfolios->sum('unrealized_gain');
        $overallRoi         = $totalInvested > 0
            ? round(($totalUnrealizedGain / $totalInvested) * 100, 2)
            : 0;

        // Distribusi per jenis reksa dana
        $distribution = $portfolios->groupBy('fund.fund_type')
            ->map(function ($group, $type) use ($totalCurrentValue) {
                $groupValue = $group->sum('current_value');
                return [
                    'fund_type'   => $type,
                    'label'       => $group->first()->fund->fund_type_label ?? $type,
                    'total_value' => round($groupValue, 2),
                    'percentage'  => $totalCurrentValue > 0
                        ? round(($groupValue / $totalCurrentValue) * 100, 2)
                        : 0,
                    'product_count' => $group->count(),
                ];
            })->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_invested'       => round($totalInvested, 2),
                'total_current_value'  => round($totalCurrentValue, 2),
                'total_unrealized_gain'=> round($totalUnrealizedGain, 2),
                'overall_roi_percent'  => $overallRoi,
                'total_products'       => $portfolios->count(),
                'distribution'         => $distribution,
            ],
        ]);
    }
}
