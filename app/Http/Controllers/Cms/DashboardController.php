<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Kyc;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * CMS DashboardController — ringkasan angka untuk halaman Dashboard admin.
 */
class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $nasabah = User::where('role', User::ROLE_USER);

        // AUM platform = nilai unit yang dimiliki nasabah × NAB terkini produk.
        $aum = Portfolio::query()
            ->join('mutual_funds', 'mutual_funds.id', '=', 'portfolios.fund_id')
            ->selectRaw('COALESCE(SUM(portfolios.total_units * mutual_funds.nav_per_unit), 0) AS aum')
            ->value('aum');

        return response()->json([
            'success'          => true,
            'total_users'      => (clone $nasabah)->count(),
            'pending_kyc'      => Kyc::where('status', 'pending')->count(),
            'active_investors' => Portfolio::where('total_units', '>', 0)->distinct('user_id')->count('user_id'),
            'total_aum'        => (float) $aum,
            'transactions_today' => Transaction::whereDate('created_at', today())->count(),
        ]);
    }
}
