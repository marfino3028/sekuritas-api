<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\AumHistory;
use App\Models\MutualFund;
use App\Models\NavHistory;
use App\Models\Portfolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * CMS ProductController — CRUD produk reksa dana.
 */
class ProductController extends Controller
{
    /**
     * Daftar semua produk reksa dana (aktif maupun tidak aktif).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = MutualFund::query();

        if ($request->filled('type')) {
            $query->where('fund_type', $request->type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->is_active);
        }

        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('fund_code', 'like', "%{$keyword}%")
                  ->orWhere('investment_manager', 'like', "%{$keyword}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $funds   = $query->orderBy('name')->paginate($perPage);

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
     * Detail produk reksa dana.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $fund = MutualFund::with(['navHistories' => fn ($q) => $q->limit(30)])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $fund,
        ]);
    }

    /**
     * Buat produk reksa dana baru.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fund_code'          => 'required|string|max:20|unique:mutual_funds,fund_code',
            'name'               => 'required|string|max:200',
            'investment_manager' => 'required|string|max:200',
            'custodian_bank'     => 'required|string|max:200',
            'fund_type'          => 'required|in:money_market,fixed_income,balanced,equity,sharia',
            'risk_level'         => 'nullable|integer|min:1|max:5',
            'nav_per_unit'       => 'required|numeric|min:0.01',
            'nav_date'           => 'required|date',
            'min_subscription'   => 'required|numeric|min:10000',
            'min_redemption_unit'=> 'required|numeric|min:0',
            'management_fee'     => 'required|numeric|min:0|max:10',
            'subscription_fee'   => 'nullable|numeric|min:0|max:5',
            'redemption_fee'     => 'nullable|numeric|min:0|max:5',
            'total_aum'          => 'nullable|numeric|min:0',
            'performance_1yr'    => 'nullable|numeric',
            'performance_3yr'    => 'nullable|numeric',
            'performance_ytd'    => 'nullable|numeric',
            'is_syariah'         => 'boolean',
            'is_active'          => 'boolean',
            'description'        => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $fund = MutualFund::create($request->validated());

        // Otomatis buat entri NAV history pertama
        NavHistory::create([
            'fund_id'     => $fund->id,
            'nav_date'    => $request->nav_date,
            'nav_per_unit'=> $request->nav_per_unit,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Produk reksa dana '{$fund->name}' berhasil ditambahkan.",
            'data'    => $fund,
        ], 201);
    }

    /**
     * Update data produk reksa dana.
     *
     * @param Request $request
     * @param int     $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $fund = MutualFund::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'fund_code'          => "string|max:20|unique:mutual_funds,fund_code,{$id}",
            'name'               => 'string|max:200',
            'investment_manager' => 'string|max:200',
            'custodian_bank'     => 'string|max:200',
            'fund_type'          => 'in:money_market,fixed_income,balanced,equity,sharia',
            'risk_level'         => 'nullable|integer|min:1|max:5',
            'nav_per_unit'       => 'numeric|min:0.01',
            'nav_date'           => 'date',
            'min_subscription'   => 'numeric|min:10000',
            'min_redemption_unit'=> 'numeric|min:0',
            'management_fee'     => 'numeric|min:0|max:10',
            'subscription_fee'   => 'nullable|numeric|min:0|max:5',
            'redemption_fee'     => 'nullable|numeric|min:0|max:5',
            'total_aum'          => 'nullable|numeric|min:0',
            'performance_1yr'    => 'nullable|numeric',
            'performance_3yr'    => 'nullable|numeric',
            'performance_ytd'    => 'nullable|numeric',
            'is_syariah'         => 'boolean',
            'is_active'          => 'boolean',
            'description'        => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $oldNav = (float) $fund->nav_per_unit;
        $fund->update($request->only([
            'fund_code', 'name', 'investment_manager', 'custodian_bank',
            'fund_type', 'risk_level', 'nav_per_unit', 'nav_date', 'min_subscription',
            'min_redemption_unit', 'management_fee', 'subscription_fee',
            'redemption_fee', 'total_aum', 'performance_1yr', 'performance_3yr',
            'performance_ytd', 'is_syariah', 'is_active', 'description',
        ]));

        // Jika NAV berubah, catat ke histori NAV
        if ($request->filled('nav_per_unit') && $request->nav_per_unit != $oldNav) {
            $navChange    = $request->nav_per_unit - $oldNav;
            $navChangePct = $oldNav > 0 ? round(($navChange / $oldNav) * 100, 4) : 0;

            NavHistory::updateOrCreate(
                [
                    'fund_id'  => $fund->id,
                    'nav_date' => $request->input('nav_date', now()->toDateString()),
                ],
                [
                    'nav_per_unit'  => $request->nav_per_unit,
                    'nav_change'    => $navChange,
                    'nav_change_pct'=> $navChangePct,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Produk '{$fund->name}' berhasil diperbarui.",
            'data'    => $fund->fresh(),
        ]);
    }

    /**
     * Hapus produk reksa dana (hanya jika tidak ada transaksi terkait).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $fund = MutualFund::findOrFail($id);

        // Cek apakah ada transaksi aktif
        $activeTransactions = $fund->transactions()
            ->whereIn('status', ['pending', 'paid', 'processing'])
            ->count();

        if ($activeTransactions > 0) {
            return response()->json([
                'success' => false,
                'message' => "Produk tidak bisa dihapus. Ada {$activeTransactions} transaksi aktif terkait produk ini.",
            ], 400);
        }

        // Nonaktifkan produk daripada hapus (soft delete semantics)
        $fund->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => "Produk '{$fund->name}' berhasil dinonaktifkan.",
        ]);
    }

    /**
     * Update NAV harian produk reksa dana.
     * Biasanya dipanggil batch setiap hari setelah cut-off jam 13.00 WIB.
     *
     * @param Request $request
     * @param int     $id
     * @return JsonResponse
     */
    public function updateNav(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nav_per_unit' => 'required|numeric|min:0.01',
            'nav_date'     => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $fund      = MutualFund::findOrFail($id);
        $oldNav    = (float) $fund->nav_per_unit;
        $newNav    = (float) $request->nav_per_unit;
        $navDate   = $request->nav_date;
        $navChange = $newNav - $oldNav;
        $pct       = $oldNav > 0 ? round(($navChange / $oldNav) * 100, 4) : 0;

        DB::transaction(function () use ($fund, $newNav, $navDate, $navChange, $pct) {
            // 1. Update NAV di tabel mutual_funds
            $fund->update([
                'nav_per_unit' => $newNav,
                'nav_date'     => $navDate,
            ]);

            // 2. Catat ke histori NAV
            NavHistory::updateOrCreate(
                ['fund_id' => $fund->id, 'nav_date' => $navDate],
                [
                    'nav_per_unit'   => $newNav,
                    'nav_change'     => round($navChange, 4),
                    'nav_change_pct' => $pct,
                ]
            );

            // 3. Hitung ulang AUM: total unit outstanding × NAV baru
            $totalUnits     = (float) Portfolio::where('fund_id', $fund->id)->sum('total_units');
            $totalAum       = round($totalUnits * $newNav, 2);
            $investorCount  = Portfolio::where('fund_id', $fund->id)
                                ->where('total_units', '>', 0)
                                ->count();

            $fund->update(['total_aum' => $totalAum]);

            // 4. Catat ke riwayat AUM harian
            AumHistory::updateOrCreate(
                ['fund_id' => $fund->id, 'aum_date' => $navDate],
                [
                    'nav_per_unit'  => $newNav,
                    'total_units'   => $totalUnits,
                    'total_aum'     => $totalAum,
                    'investor_count'=> $investorCount,
                ]
            );

            // 5. Perbarui nilai pasar & unrealized gain semua portofolio investor
            Portfolio::where('fund_id', $fund->id)
                ->where('total_units', '>', 0)
                ->each(function ($portfolio) use ($newNav) {
                    $portfolio->updateMarketValue($newNav);
                });
        });

        $fund->refresh();

        return response()->json([
            'success' => true,
            'message' => "NAV {$fund->name} berhasil diperbarui: {$oldNav} → {$newNav}. AUM diperbarui ke Rp " . number_format($fund->total_aum, 0, ',', '.'),
            'data'    => [
                'fund_id'        => $fund->id,
                'fund_name'      => $fund->name,
                'nav_date'       => $navDate,
                'old_nav'        => $oldNav,
                'new_nav'        => $newNav,
                'nav_change'     => round($navChange, 4),
                'nav_change_pct' => $pct,
                'total_aum'      => (float) $fund->total_aum,
                'total_units'    => (float) Portfolio::where('fund_id', $fund->id)->sum('total_units'),
            ],
        ]);
    }

    /**
     * Bulk update NAV untuk beberapa produk sekaligus.
     * Endpoint: POST /api/cms/products/nav-bulk
     * Body: { "updates": [{ "fund_id": 1, "nav_per_unit": 1234.56, "nav_date": "2026-06-05" }] }
     */
    public function updateNavBulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'updates'                  => 'required|array|min:1|max:50',
            'updates.*.fund_id'        => 'required|integer|exists:mutual_funds,id',
            'updates.*.nav_per_unit'   => 'required|numeric|min:0.01',
            'updates.*.nav_date'       => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $results = [];
        $errors  = [];

        foreach ($request->updates as $update) {
            try {
                DB::transaction(function () use ($update, &$results) {
                    $fund      = MutualFund::findOrFail($update['fund_id']);
                    $oldNav    = (float) $fund->nav_per_unit;
                    $newNav    = (float) $update['nav_per_unit'];
                    $navDate   = $update['nav_date'];
                    $navChange = $newNav - $oldNav;
                    $pct       = $oldNav > 0 ? round(($navChange / $oldNav) * 100, 4) : 0;

                    $fund->update(['nav_per_unit' => $newNav, 'nav_date' => $navDate]);

                    NavHistory::updateOrCreate(
                        ['fund_id' => $fund->id, 'nav_date' => $navDate],
                        ['nav_per_unit' => $newNav, 'nav_change' => round($navChange, 4), 'nav_change_pct' => $pct]
                    );

                    $totalUnits    = (float) Portfolio::where('fund_id', $fund->id)->sum('total_units');
                    $totalAum      = round($totalUnits * $newNav, 2);
                    $investorCount = Portfolio::where('fund_id', $fund->id)->where('total_units', '>', 0)->count();

                    $fund->update(['total_aum' => $totalAum]);

                    AumHistory::updateOrCreate(
                        ['fund_id' => $fund->id, 'aum_date' => $navDate],
                        ['nav_per_unit' => $newNav, 'total_units' => $totalUnits, 'total_aum' => $totalAum, 'investor_count' => $investorCount]
                    );

                    Portfolio::where('fund_id', $fund->id)->where('total_units', '>', 0)
                        ->each(fn ($p) => $p->updateMarketValue($newNav));

                    $results[] = [
                        'fund_id'   => $fund->id,
                        'fund_name' => $fund->name,
                        'old_nav'   => $oldNav,
                        'new_nav'   => $newNav,
                        'total_aum' => $totalAum,
                        'status'    => 'updated',
                    ];
                });
            } catch (\Throwable $e) {
                $errors[] = ['fund_id' => $update['fund_id'], 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => empty($errors),
            'message' => count($results) . ' produk berhasil diperbarui' . (count($errors) ? ', ' . count($errors) . ' gagal' : ''),
            'results' => $results,
            'errors'  => $errors,
        ], empty($errors) ? 200 : 207);
    }
}
