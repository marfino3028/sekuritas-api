<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MutualFund;
use App\Models\NavHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProductController — Daftar dan detail produk reksa dana.
 * Endpoint publik, tidak membutuhkan autentikasi.
 */
class ProductController extends Controller
{
    /**
     * Daftar semua produk reksa dana aktif dengan filter dan sorting.
     *
     * Query params:
     * - type: money_market|fixed_income|balanced|equity|sharia
     * - is_syariah: 1|0
     * - sort: nav_asc|nav_desc|performance_asc|performance_desc|name_asc
     * - search: keyword nama produk
     * - per_page: items per page (default 15)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = MutualFund::active();

        // Filter berdasarkan jenis reksa dana
        if ($request->filled('type')) {
            $query->ofType($request->type);
        }

        // Filter produk syariah
        if ($request->has('is_syariah') && $request->is_syariah !== '') {
            $query->syariah((bool) $request->is_syariah);
        }

        // Filter berdasarkan profil risiko (1..5)
        if ($request->filled('risk')) {
            $query->ofRisk((int) $request->risk);
        }

        // Filter berdasarkan Manajer Investasi
        if ($request->filled('manager')) {
            $query->where('investment_manager', $request->manager);
        }

        // Filter berdasarkan keyword nama/MI
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('investment_manager', 'like', "%{$keyword}%")
                  ->orWhere('fund_code', 'like', "%{$keyword}%");
            });
        }

        // Sorting
        $sortMap = [
            'nav_asc'          => ['nav_per_unit', 'asc'],
            'nav_desc'         => ['nav_per_unit', 'desc'],
            'performance_asc'  => ['performance_1yr', 'asc'],
            'performance_desc' => ['performance_1yr', 'desc'],
            'aum_desc'         => ['total_aum', 'desc'],
            'name_asc'         => ['name', 'asc'],
        ];

        $sort = $request->input('sort', 'name_asc');
        [$sortColumn, $sortDir] = $sortMap[$sort] ?? ['name', 'asc'];
        $query->orderBy($sortColumn, $sortDir);

        $perPage = min((int) $request->input('per_page', 15), 50);
        $funds   = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $funds->items(),
            'meta'    => [
                'current_page' => $funds->currentPage(),
                'last_page'    => $funds->lastPage(),
                'per_page'     => $funds->perPage(),
                'total'        => $funds->total(),
            ],
        ]);
    }

    /**
     * Daftar Manajer Investasi beserta total AUM yang dikelola saat ini.
     * Publik — untuk halaman / section "Manajer Investasi".
     *
     * @return JsonResponse
     */
    public function managers(): JsonResponse
    {
        $managers = MutualFund::active()
            ->selectRaw('investment_manager,
                COUNT(*) as fund_count,
                SUM(total_aum) as total_aum,
                ROUND(AVG(performance_1yr), 2) as avg_performance_1yr,
                MIN(risk_level) as min_risk_level,
                MAX(risk_level) as max_risk_level')
            ->groupBy('investment_manager')
            ->orderByDesc('total_aum')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $managers,
        ]);
    }

    /**
     * Detail produk reksa dana berdasarkan ID.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $fund = MutualFund::active()->findOrFail($id);

        // Ambil NAV terakhir 30 hari untuk mini chart
        $recentNav = NavHistory::where('fund_id', $fund->id)
            ->orderBy('nav_date', 'desc')
            ->limit(30)
            ->get(['nav_date', 'nav_per_unit', 'nav_change_pct']);

        return response()->json([
            'success' => true,
            'data'    => array_merge($fund->toArray(), [
                'fund_type_label' => $fund->fund_type_label,
                'recent_nav'      => $recentNav->reverse()->values(),
            ]),
        ]);
    }

    /**
     * Histori NAV produk dengan filter rentang waktu.
     *
     * Query params:
     * - period: 1m|3m|6m|1y|3y|all (default: 1y)
     *
     * @param int $id Fund ID
     * @return JsonResponse
     */
    public function navHistory(int $id): JsonResponse
    {
        $fund = MutualFund::active()->findOrFail($id);

        $period = request()->input('period', '1y');
        $startDate = match ($period) {
            '1m'  => now()->subMonth(),
            '3m'  => now()->subMonths(3),
            '6m'  => now()->subMonths(6),
            '1y'  => now()->subYear(),
            '3y'  => now()->subYears(3),
            'all' => null,
            default => now()->subYear(),
        };

        $query = NavHistory::where('fund_id', $fund->id)
            ->orderBy('nav_date', 'asc');

        if ($startDate) {
            $query->where('nav_date', '>=', $startDate->toDateString());
        }

        $histories = $query->get(['nav_date', 'nav_per_unit', 'nav_change', 'nav_change_pct']);

        // Hitung performa dalam rentang yang dipilih
        $performance = null;
        if ($histories->count() >= 2) {
            $firstNav    = (float) $histories->first()->nav_per_unit;
            $lastNav     = (float) $histories->last()->nav_per_unit;
            $performance = $firstNav > 0 ? round((($lastNav - $firstNav) / $firstNav) * 100, 2) : 0;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'fund_id'        => $fund->id,
                'fund_code'      => $fund->fund_code,
                'fund_name'      => $fund->name,
                'period'         => $period,
                'performance_pct'=> $performance,
                'nav_current'    => $fund->nav_per_unit,
                'nav_date'       => $fund->nav_date,
                'histories'      => $histories,
            ],
        ]);
    }
}
