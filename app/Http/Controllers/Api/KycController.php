<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EkycResult;
use App\Models\EkycSession;
use App\Models\Kyc;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * KycController — Pengajuan dan manajemen data KYC nasabah.
 *
 * Flow KYC:
 * 1. Upload foto KTP: POST /kyc/upload?type=ktp
 * 2. Upload selfie: POST /kyc/upload?type=selfie
 * 3. Submit data KYC lengkap: POST /kyc/submit
 * 4. Admin review di CMS: /cms/kyc/{id}/approve atau /reject
 */
class KycController extends Controller
{
    /**
     * Upload dokumen foto KTP atau selfie.
     * Disimpan ke local storage: storage/app/public/kyc/{user_id}/
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:ktp,selfie,npwp,bank_book,signature,paraf',
            'file' => 'required|file|mimes:jpg,jpeg,png|max:5120', // max 5MB
        ], [
            'file.mimes' => 'Format file harus JPG atau PNG.',
            'file.max'   => 'Ukuran file maksimal 5MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = JWTAuth::user();
        $type = $request->input('type'); // 'ktp' atau 'selfie'

        // Simpan file ke local storage
        $path = $request->file('file')->store(
            "kyc/{$user->id}",
            'public'
        );

        // Update path di tabel KYC (buat record jika belum ada)
        $kyc = Kyc::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => Kyc::STATUS_PENDING]
        );

        $fieldMap = [
            'ktp'       => 'ktp_photo_path',
            'selfie'    => 'selfie_photo_path',
            'npwp'      => 'npwp_photo_path',
            'bank_book' => 'bank_book_photo_path',
            'signature' => 'signature_path',
            'paraf'     => 'paraf_path',
        ];
        $kyc->update([$fieldMap[$type] => $path]);

        return response()->json([
            'success' => true,
            'message' => ucfirst($type) . ' berhasil diupload.',
            'data'    => [
                'type'      => $type,
                'path'      => $path,
                'url'       => Storage::url($path),
            ],
        ]);
    }

    /**
     * Submit data KYC lengkap.
     * User harus sudah upload KTP dan selfie sebelumnya.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function submit(Request $request): JsonResponse
    {
        $user = JWTAuth::user();

        // Cek apakah KYC sudah approved
        $existingKyc = Kyc::where('user_id', $user->id)->first();
        if ($existingKyc && $existingKyc->status === Kyc::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'KYC Anda sudah disetujui. Tidak perlu submit ulang.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'nik'                  => 'required|string|size:16|unique:kyc,nik' . ($existingKyc ? ",{$existingKyc->id}" : ''),
            'mother_maiden_name'   => 'required|string|max:100',
            'birth_date'           => 'required|date|before:-17 years',
            'gender'               => 'required|in:M,F',
            'marital_status'       => 'required|in:single,married,divorced,widowed',
            'education'            => 'required|in:sd,smp,sma,diploma,s1,s2,s3,other',
            'occupation'           => 'required|in:pns,tni_polri,karyawan_swasta,wiraswasta,profesional,ibu_rumah_tangga,pelajar,pensiunan,other',
            'income_level'         => 'required|in:below_5jt,5jt_10jt,10jt_25jt,25jt_50jt,above_50jt',
            'source_of_fund'       => 'required|in:gaji,usaha,investasi,warisan,hadiah,other',
            'investment_objective' => 'required|in:pendidikan,pensiun,dana_darurat,pertumbuhan_aset,pendapatan_rutin,other',
            'address'              => 'required|string|max:500',
            'province'             => 'required|string|max:100',
            'city'                 => 'required|string|max:100',
            'postal_code'          => 'nullable|string|max:10',
            // Data pekerjaan & informasi tambahan (CGS) — disimpan sebagai JSON
            'employment'           => 'nullable|array',
            'additional_info'      => 'nullable|array',
        ], [
            'nik.size'           => 'NIK harus 16 digit.',
            'nik.unique'         => 'NIK sudah terdaftar.',
            'birth_date.before'  => 'Investor minimal berusia 17 tahun.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi data KYC gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Cek apakah dokumen foto sudah diupload
        if (!$existingKyc || !$existingKyc->hasKtpPhoto()) {
            return response()->json([
                'success' => false,
                'message' => 'Foto KTP belum diupload. Silakan upload terlebih dahulu.',
            ], 400);
        }

        if (!$existingKyc->hasSelfiePhoto()) {
            return response()->json([
                'success' => false,
                'message' => 'Foto selfie belum diupload. Silakan upload terlebih dahulu.',
            ], 400);
        }

        // Verifikasi otomatis (eKYC) lolos → langsung disetujui tanpa antre review Ops
        $autoApproved = $this->passedAutoEkyc($user, $request);

        // Simpan/update data KYC
        $kycData = array_merge($request->only([
            'nik', 'mother_maiden_name', 'birth_date', 'gender',
            'marital_status', 'education', 'occupation', 'income_level',
            'source_of_fund', 'investment_objective', 'address',
            'province', 'city', 'postal_code',
            'employment', 'additional_info',
        ]), [
            'status'          => $autoApproved ? Kyc::STATUS_APPROVED : Kyc::STATUS_PENDING,
            'submitted_at'    => Carbon::now(),
            'reviewed_by'     => null,
            'reviewed_at'     => $autoApproved ? Carbon::now() : null,
            'rejected_reason' => null,
        ]);

        $kyc = Kyc::updateOrCreate(
            ['user_id' => $user->id],
            $kycData
        );

        $autoApproved
            ? \App\Support\Notify::kycApproved($user, auto: true)
            : \App\Support\Notify::kycSubmitted($user);

        return response()->json([
            'success' => true,
            'message' => $autoApproved
                ? 'Verifikasi otomatis berhasil. Data Anda disetujui dan SID sedang diproses.'
                : 'Data KYC berhasil disubmit. Tim kami akan memverifikasi dalam 1x24 jam.',
            'data'    => array_merge($kyc->toArray(), ['auto_approved' => $autoApproved]),
        ], 201);
    }

    /**
     * eKYC dianggap lolos otomatis bila sesi terakhir (24 jam) berkeputusan "approved"
     * dan NIK serta tanggal lahir yang dikirim sama dengan hasil baca AI dari e-KTP.
     */
    private function passedAutoEkyc(User $user, Request $request): bool
    {
        $session = EkycSession::with(['document', 'result'])
            ->where('user_id', $user->id)
            ->where('completed_at', '>=', Carbon::now()->subDay())
            ->latest('completed_at')
            ->first();

        if (! $session || ! $session->auto_approved || $session->result?->decision !== EkycResult::DECISION_APPROVED) {
            return false;
        }

        $doc = $session->document;
        if (! $doc || $doc->nik !== $request->input('nik')) {
            return false;
        }

        return ! $doc->birth_date
            || $doc->birth_date->toDateString() === Carbon::parse($request->input('birth_date'))->toDateString();
    }

    /**
     * Lihat status dan data KYC milik user yang sedang login.
     *
     * @return JsonResponse
     */
    public function show(): JsonResponse
    {
        $user = JWTAuth::user();
        $kyc  = Kyc::where('user_id', $user->id)->first();

        if (!$kyc) {
            return response()->json([
                'success' => true,
                'message' => 'KYC belum disubmit.',
                'data'    => null,
            ]);
        }

        // Hasil eKYC otomatis terakhir (skor AI) untuk halaman status nasabah
        $result = EkycResult::whereIn('session_id',
            EkycSession::where('user_id', $user->id)->pluck('id'))->latest()->first();

        return response()->json([
            'success' => true,
            'data'    => array_merge($kyc->toArray(), [
                'ktp_url'    => $kyc->ktp_photo_path ? Storage::url($kyc->ktp_photo_path) : null,
                'selfie_url' => $kyc->selfie_photo_path ? Storage::url($kyc->selfie_photo_path) : null,
                'sid_status' => $user->sid_status,
                'auto_approved' => $kyc->status === Kyc::STATUS_APPROVED && ! $kyc->reviewed_by && $result?->decision === EkycResult::DECISION_APPROVED,
                'sid_number' => $user->sid_number,
                'ekyc'       => $result ? [
                    'ocr_score'        => $result->ocr_score,
                    'liveness_score'   => $result->liveness_score,
                    'face_match_score' => $result->face_match_score,
                    'final_score'      => $result->final_score,
                    'decision'         => $result->decision,
                    'flags'            => $result->flags ?? [],
                ] : null,
                'thresholds' => config('ekyc.thresholds'),
            ]),
        ]);
    }
}
