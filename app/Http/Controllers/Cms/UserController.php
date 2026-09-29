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
            // Kolom datar yang dibaca tabel CMS (status KYC & SID ada di relasi / kolom sid_number)
            'data'    => collect($users->items())->map(fn (User $u) => array_merge($u->toArray(), [
                'kyc_status' => $u->kyc?->status ?? 'none',
                'sid'        => $u->sid_number,
                'ifua'       => $u->ifua_number,
            ])),
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

        $kyc = $user->kyc;
        $occupations = [
            'pns' => 'PNS', 'tni_polri' => 'TNI/Polri', 'karyawan_swasta' => 'Karyawan Swasta', 'wiraswasta' => 'Wiraswasta',
            'profesional' => 'Profesional', 'ibu_rumah_tangga' => 'Ibu Rumah Tangga', 'pelajar' => 'Pelajar/Mahasiswa',
            'pensiunan' => 'Pensiunan', 'other' => 'Lainnya',
        ];
        $risk = $user->risk_profile_result ?? $user->riskProfile?->result;

        $recent = $user->transactions()->with('fund:id,name')->latest()->limit(5)->get()
            ->map(fn ($t) => [
                'id'           => $t->id,
                'product_name' => $t->fund?->name,
                'type'         => $t->type,
                'amount'       => $t->amount,
                'status'       => $t->status === 'settled' ? 'completed' : $t->status,
                'created_at'   => $t->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data'    => array_merge($user->toArray(), [
                'transaksi_stats' => $transaksiStats,
                // Kolom datar yang dibaca halaman Detail User CMS
                'kyc_id'          => $kyc?->id,
                'kyc_status'      => $kyc?->status ?? 'none',
                'nik'             => $kyc?->nik,
                'birth_date'      => $kyc?->birth_date ? \Illuminate\Support\Carbon::parse($kyc->birth_date)->format('Y-m-d') : null,
                'gender'          => $kyc?->gender,
                'occupation'      => $occupations[$kyc?->occupation ?? ''] ?? $kyc?->occupation,
                'risk_profile'    => ['conservative' => 'Konservatif', 'moderate' => 'Moderat', 'aggressive' => 'Agresif'][$risk ?? ''] ?? null,
                'sid'             => $user->sid_number,
                'ifua'            => $user->ifua_number,
                'bank_name'       => data_get($kyc?->additional_info, 'bank_name'),
                'account_number'  => data_get($kyc?->additional_info, 'bank_account_number'),
                'account_name'    => data_get($kyc?->additional_info, 'bank_account_name'),
                'is_active'       => $user->status === User::STATUS_ACTIVE,
                'last_login'      => $user->activated_at ?? $user->email_verified_at,
                'tx_count'        => $transaksiStats['total'],
                'total_invested'  => (float) $transaksiStats['total_investasi'],
                'active_products' => $user->portfolios()->where('total_units', '>', 0)->count(),
                'recent_transactions' => $recent,
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
