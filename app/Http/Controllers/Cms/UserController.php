<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CMS UserController — Manajemen akun nasabah dari panel admin.
 */
class UserController extends Controller
{
    /**
     * Daftar semua nasabah dengan filter.
     *
     * Query params:
     * - status: pending|active|suspended
     * - sid_status: not_generated|processing|active
     * - kyc_status: pending|approved|rejected
     * - search: nama/email/phone/SID
     * - per_page: default 20
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', User::ROLE_USER)
            ->with(['kyc:id,user_id,status,submitted_at'])
            ->latest();

        // Filter status akun
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter status SID
        if ($request->filled('sid_status')) {
            $query->where('sid_status', $request->sid_status);
        }

        // Filter status KYC
        if ($request->filled('kyc_status')) {
            $query->whereHas('kyc', fn ($q) => $q->where('status', $request->kyc_status));
        }

        // Pencarian
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('email', 'like', "%{$keyword}%")
                  ->orWhere('phone', 'like', "%{$keyword}%")
                  ->orWhere('sid_number', 'like', "%{$keyword}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $users   = $query->paginate($perPage);

        // Statistik
        $stats = [
            'total'        => User::where('role', User::ROLE_USER)->count(),
            'active'       => User::where('role', User::ROLE_USER)->where('status', User::STATUS_ACTIVE)->count(),
            'pending'      => User::where('role', User::ROLE_USER)->where('status', User::STATUS_PENDING)->count(),
            'suspended'    => User::where('role', User::ROLE_USER)->where('status', User::STATUS_SUSPENDED)->count(),
            'sid_active'   => User::where('role', User::ROLE_USER)->where('sid_status', User::SID_ACTIVE)->count(),
            'kyc_pending'  => User::where('role', User::ROLE_USER)
                ->whereHas('kyc', fn ($q) => $q->where('status', 'pending'))->count(),
        ];

        return response()->json([
            'success' => true,
            'stats'   => $stats,
            'data'    => $users->items(),
            'meta'    => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    /**
     * Detail nasabah beserta data KYC, profil risiko, SID, dan statistik transaksi.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with([
            'kyc',
            'riskProfile',
            'sidData',
        ])->findOrFail($id);

        // Statistik transaksi nasabah
        $transaksiStats = [
            'total'    => $user->transactions()->count(),
            'settled'  => $user->transactions()->where('status', 'settled')->count(),
            'pending'  => $user->transactions()->where('status', 'pending')->count(),
            'total_investasi' => $user->portfolios()->sum('total_invested'),
        ];

        return response()->json([
            'success' => true,
            'data'    => array_merge($user->toArray(), [
                'transaksi_stats' => $transaksiStats,
            ]),
        ]);
    }

    /**
     * Update status akun nasabah (aktifkan/suspend).
     *
     * @param Request $request
     * @param int     $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,suspended,pending',
            'reason' => 'required_if:status,suspended|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('role', User::ROLE_USER)->findOrFail($id);
        $user->update(['status' => $request->status]);

        $statusLabel = [
            'active'    => 'diaktifkan',
            'suspended' => 'disuspend',
            'pending'   => 'dikembalikan ke pending',
        ];

        return response()->json([
            'success' => true,
            'message' => "Akun nasabah {$user->name} berhasil {$statusLabel[$request->status]}.",
            'data'    => [
                'user_id' => $user->id,
                'name'    => $user->name,
                'status'  => $user->status,
            ],
        ]);
    }
}
