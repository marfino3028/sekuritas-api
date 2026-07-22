<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Kyc;
use App\Services\SInvestService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * CMS KycController — Review dan persetujuan data KYC nasabah.
 *
 * Workflow:
 * 1. Admin lihat daftar KYC pending
 * 2. Admin review dokumen KTP & selfie
 * 3. Admin approve → sistem otomatis generate SID via SInvestService
 * 4. Admin reject → isi alasan penolakan
 */
class KycController extends Controller
{
    public function __construct(private SInvestService $sInvestService) {}

    /**
     * Daftar pengajuan KYC — default tampilkan yang pending.
     *
     * Query params:
     * - status: pending|approved|rejected (default: pending)
     * - search: keyword nama/email nasabah
     * - per_page: default 20
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Kyc::with(['user:id,name,email,phone,status,created_at'])
            ->latest('submitted_at');

        // Filter status
        $status = $request->input('status', 'pending');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Filter pencarian
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->whereHas('user', function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('email', 'like', "%{$keyword}%");
            })->orWhere('nik', 'like', "%{$keyword}%");
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $kycList = $query->paginate($perPage);

        // Statistik ringkas
        $stats = [
            'pending'  => Kyc::where('status', 'pending')->count(),
            'approved' => Kyc::where('status', 'approved')->count(),
            'rejected' => Kyc::where('status', 'rejected')->count(),
        ];

        return response()->json([
            'success' => true,
            'stats'   => $stats,
            'data'    => $kycList->items(),
            'meta'    => [
                'current_page' => $kycList->currentPage(),
                'last_page'    => $kycList->lastPage(),
                'per_page'     => $kycList->perPage(),
                'total'        => $kycList->total(),
            ],
        ]);
    }

    /**
     * Detail satu pengajuan KYC.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $kyc = Kyc::with(['user', 'reviewer'])->findOrFail($id);

        // Sertakan hasil eKYC otomatis terbaru milik nasabah (untuk review admin)
        $ekyc = \App\Models\EkycSession::with(['document', 'selfie', 'signature', 'result'])
            ->where('user_id', $kyc->user_id)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data'    => array_merge($kyc->toArray(), [
                'ekyc' => $ekyc,
            ]),
        ]);
    }

    /**
     * Setujui pengajuan KYC nasabah (LANGKAH 1).
     *
     * Catatan: penerbitan SID DIPISAH ke langkah terpisah (issueSid) agar ops
     * punya kontrol dua langkah — approve KYC dulu, lalu "Kirim ke S-INVEST".
     *
     * @param int $id KYC ID
     * @return JsonResponse
     */
    public function approve(int $id): JsonResponse
    {
        $kyc = Kyc::with('user')->findOrFail($id);

        if ($kyc->status !== Kyc::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => "KYC ini sudah berstatus '{$kyc->status}'. Tidak bisa diproses ulang.",
            ], 400);
        }

        $admin = JWTAuth::user();

        $kyc->update([
            'status'      => Kyc::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => Carbon::now(),
        ]);

        Log::info("[CMS] KYC disetujui (SID belum diterbitkan)", [
            'kyc_id'   => $kyc->id,
            'user_id'  => $kyc->user_id,
            'admin_id' => $admin->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "KYC nasabah {$kyc->user->name} disetujui. Lanjutkan dengan 'Kirim ke S-INVEST' untuk menerbitkan SID.",
            'data'    => [
                'kyc_id'      => $kyc->id,
                'user_id'     => $kyc->user_id,
                'user_name'   => $kyc->user->name,
                'status'      => $kyc->status,
                'sid_issued'  => false,
                'approved_by' => $admin->name,
                'approved_at' => $kyc->reviewed_at,
            ],
        ]);
    }

    /**
     * Terbitkan SID via S-INVEST (LANGKAH 2 — dipicu manual oleh ops).
     * Hanya untuk nasabah yang KYC-nya sudah APPROVED dan SID belum aktif.
     *
     * POST /api/cms/kyc/{id}/issue-sid
     *
     * @param int $id KYC ID
     * @return JsonResponse
     */
    public function issueSid(int $id): JsonResponse
    {
        $kyc = Kyc::with('user')->findOrFail($id);

        if ($kyc->status !== Kyc::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'SID hanya bisa diterbitkan setelah KYC disetujui.',
            ], 400);
        }

        if ($kyc->user->sid_status === \App\Models\User::SID_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => "Nasabah sudah memiliki SID aktif: {$kyc->user->sid_number}.",
            ], 400);
        }

        $admin = JWTAuth::user();

        try {
            $sidResult = $this->sInvestService->generateSid($kyc->user_id);

            Log::info("[CMS] SID diterbitkan ke S-INVEST", [
                'kyc_id'     => $kyc->id,
                'user_id'    => $kyc->user_id,
                'sid_number' => $sidResult['sid_number'],
                'admin_id'   => $admin->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => "SID nasabah {$kyc->user->name} berhasil diterbitkan.",
                'data'    => [
                    'kyc_id'      => $kyc->id,
                    'user_id'     => $kyc->user_id,
                    'user_name'   => $kyc->user->name,
                    'sid_number'  => $sidResult['sid_number'],
                    'ifua_number' => $sidResult['ifua_number'],
                    'issued_by'   => $admin->name,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("[CMS] Gagal menerbitkan SID", [
                'kyc_id' => $id,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menerbitkan SID: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tolak pengajuan KYC nasabah.
     * Harus menyertakan alasan penolakan.
     *
     * @param Request $request
     * @param int     $id KYC ID
     * @return JsonResponse
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rejected_reason' => 'required|string|min:10|max:500',
        ], [
            'rejected_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejected_reason.min'      => 'Alasan penolakan minimal 10 karakter.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $kyc = Kyc::with('user')->findOrFail($id);

        if ($kyc->status !== Kyc::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => "KYC ini sudah berstatus '{$kyc->status}'. Tidak bisa diproses ulang.",
            ], 400);
        }

        $admin = JWTAuth::user();

        $kyc->update([
            'status'          => Kyc::STATUS_REJECTED,
            'rejected_reason' => $request->rejected_reason,
            'reviewed_by'     => $admin->id,
            'reviewed_at'     => Carbon::now(),
        ]);

        Log::info("[CMS] KYC ditolak", [
            'kyc_id'   => $kyc->id,
            'user_id'  => $kyc->user_id,
            'reason'   => $request->rejected_reason,
            'admin_id' => $admin->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "KYC nasabah {$kyc->user->name} ditolak.",
            'data'    => [
                'kyc_id'          => $kyc->id,
                'user_name'       => $kyc->user->name,
                'rejected_reason' => $request->rejected_reason,
                'rejected_by'     => $admin->name,
                'rejected_at'     => $kyc->reviewed_at,
            ],
        ]);
    }
}
