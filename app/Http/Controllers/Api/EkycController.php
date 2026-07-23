<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CompleteAccountMail;
use App\Models\EkycResult;
use App\Models\EkycSession;
use App\Services\Ekyc\EkycService;
use App\Services\Ekyc\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * EkycController — API Gateway modul eKYC (Victoria Sekuritas).
 *
 * Endpoint:
 *   POST /api/ekyc/session        buat sesi baru
 *   POST /api/ekyc/ocr            upload KTP + OCR
 *   POST /api/ekyc/liveness       upload selfie + passive liveness
 *   POST /api/ekyc/face-match     cocokkan selfie dengan KTP
 *   POST /api/ekyc/signature      tanda tangan digital (canvas/privy)
 *   POST /api/ekyc/verify         hitung skor & keputusan akhir
 *   GET  /api/ekyc/status/{id}    status & detail sesi
 *
 * Proses AI didelegasikan ke provider yang dapat diganti via adapter
 * (config('ekyc.provider')). Lihat App\Services\Ekyc\*.
 */
class EkycController extends Controller
{
    public function __construct(
        private readonly EkycService $ekyc,
        private readonly SignatureService $signature,
    ) {}

    /** Buat sesi eKYC baru. */
    public function createSession(Request $request): JsonResponse
    {
        $session = $this->ekyc->createSession(JWTAuth::user()->id, [
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->ok('Sesi eKYC dibuat.', $this->serialize($session), 201);
    }

    /** Langkah 1 — OCR KTP. */
    public function ocr(Request $request): JsonResponse
    {
        // Model OCR (vision LLM via llama-cpp) lebih berat dari PaddleOCR lama,
        // proses bisa >30 detik. Naikkan batas eksekusi PHP untuk endpoint ini saja.
        set_time_limit(240);

        $v = Validator::make($request->all(), [
            'session_id' => 'nullable|uuid',
            'file'       => 'required|file|mimes:jpg,jpeg,png|max:5120',
            // field OCR on-device (opsional, lebih dipercaya dari OCR server)
            'nik'        => 'nullable|string|size:16',
            'name'       => 'nullable|string|max:120',
            'birth_date' => 'nullable|date',
            'gender'     => 'nullable|string|max:20',
            'address'    => 'nullable|string|max:500',
        ], ['file.mimes' => 'Format harus JPG/PNG.', 'file.max' => 'Maksimal 5MB.']);
        if ($v->fails()) return $this->fail($v);

        $user    = JWTAuth::user();
        $session = $this->resolveSession($request->input('session_id'), $user->id, createIfMissing: true);
        if (! $session) return $this->notFound();

        $path = $this->ekyc->storeUpload($user->id, $request->file('file'), 'ktp');
        $doc  = $this->ekyc->runOcr($session, $path, $request->only([
            'nik', 'name', 'birth_date', 'gender', 'address',
        ]));

        return $this->ok('OCR KTP selesai.', [
            'session' => $this->serialize($session->fresh()),
            'ocr'     => $doc,
            'ktp_url' => $this->ekyc->url($doc->image_path),
        ]);
    }

    /** Langkah 2a — Liveness. */
    public function liveness(Request $request): JsonResponse
    {
        // Liveness (InsightFace/buffalo_l) juga berat di CPU + bisa trigger
        // download model pertama kali; samakan batas eksekusi dgn endpoint OCR.
        set_time_limit(240);

        $v = Validator::make($request->all(), [
            'session_id' => 'required|uuid',
            'file'       => 'required|file|mimes:jpg,jpeg,png|max:5120',
        ]);
        if ($v->fails()) return $this->fail($v);

        $user    = JWTAuth::user();
        $session = $this->resolveSession($request->input('session_id'), $user->id);
        if (! $session) return $this->notFound();

        $path   = $this->ekyc->storeUpload($user->id, $request->file('file'), 'selfie');
        $selfie = $this->ekyc->runLiveness($session, $path);

        return $this->ok('Liveness diproses.', [
            'session'  => $this->serialize($session->fresh()),
            'liveness' => $selfie->only([
                'liveness_passed', 'liveness_score', 'is_printed_photo', 'is_replay',
                'ktp_detected', 'nik_in_photo', 'nik_match', 'id_face_match', 'id_face_match_score',
            ]),
        ]);
    }

    /** Langkah 2b — Face match. */
    public function faceMatch(Request $request): JsonResponse
    {
        // Sama seperti liveness: inference wajah di CPU bisa >30 detik.
        set_time_limit(240);

        $v = Validator::make($request->all(), ['session_id' => 'required|uuid']);
        if ($v->fails()) return $this->fail($v);

        $session = $this->resolveSession($request->input('session_id'), JWTAuth::user()->id);
        if (! $session) return $this->notFound();

        $selfie = $this->ekyc->runFaceMatch($session);

        return $this->ok('Face match diproses.', [
            'session'    => $this->serialize($session->fresh()),
            'face_match' => $selfie->only(['face_matched', 'face_match_score']),
        ]);
    }

    /** Langkah 3 — Tanda tangan digital. */
    public function sign(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'session_id' => 'required|uuid',
            'signature'  => 'required_without:privy|string', // data URI base64 PNG
        ]);
        if ($v->fails()) return $this->fail($v);

        $session = $this->resolveSession($request->input('session_id'), JWTAuth::user()->id);
        if (! $session) return $this->notFound();

        $sig = config('ekyc.signature.provider') === 'privy'
            ? $this->signature->signWithPrivy($session, $request->all())
            : $this->signature->signWithCanvas($session, $request->input('signature'));

        $session->update(['status' => EkycSession::STATUS_SIGNED]);

        return $this->ok('Tanda tangan tersimpan.', [
            'session'   => $this->serialize($session->fresh()),
            'signature' => $sig->only(['provider', 'status', 'external_ref', 'signed_at']),
        ]);
    }

    /** Langkah 4 — Verifikasi akhir. */
    public function verify(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), ['session_id' => 'required|uuid']);
        if ($v->fails()) return $this->fail($v);

        $session = $this->resolveSession($request->input('session_id'), JWTAuth::user()->id);
        if (! $session) return $this->notFound();

        try {
            $result = $this->ekyc->verify($session);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'NIK yang sama telah terdaftar pada akun lain. Hubungi layanan pelanggan jika ini merupakan kesalahan.',
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        // Kirim email "Lengkapi Akun" bila verifikasi tidak ditolak (gagal email tak menggagalkan proses)
        if ($result->decision !== EkycResult::DECISION_REJECTED) {
            $user = JWTAuth::user();
            if ($user->email) {
                try {
                    Mail::to($user->email)->send(new CompleteAccountMail());
                } catch (\Throwable $e) {
                    Log::warning('Gagal kirim email Lengkapi Akun: ' . $e->getMessage(), ['user_id' => $user->id]);
                }
            }
        }

        return $this->ok('Verifikasi selesai.', [
            'session' => $this->serialize($session->fresh()),
            'result'  => $result,
        ]);
    }

    /** Verifikasi NIK (dari OCR) ke Dukcapil (mock/asli). */
    public function verifyNik(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), ['session_id' => 'required|uuid']);
        if ($v->fails()) return $this->fail($v);

        $session = $this->resolveSession($request->input('session_id'), JWTAuth::user()->id);
        if (! $session) return $this->notFound();

        $doc = $session->document;
        if (! $doc || ! $doc->nik) {
            return response()->json(['success' => false, 'message' => 'NIK belum terbaca dari KTP.'], 400);
        }

        $result = app(\App\Services\Dukcapil\DukcapilService::class)
            ->verify($doc->nik, $doc->name, $doc->birth_date?->toDateString());

        return $this->ok('Verifikasi NIK selesai.', ['dukcapil' => $result]);
    }

    /** Status & detail sesi. */
    public function status(string $id): JsonResponse
    {
        $session = EkycSession::with(['document', 'selfie', 'signature', 'result'])
            ->where('id', $id)
            ->where('user_id', JWTAuth::user()->id)
            ->first();

        if (! $session) return $this->notFound();

        return $this->ok('OK', $this->serialize($session, full: true));
    }

    // ================= Helpers =================

    private function resolveSession(?string $id, int $userId, bool $createIfMissing = false): ?EkycSession
    {
        if ($id) {
            return EkycSession::where('id', $id)->where('user_id', $userId)->first();
        }
        return $createIfMissing ? $this->ekyc->createSession($userId) : null;
    }

    private function serialize(EkycSession $s, bool $full = false): array
    {
        $base = [
            'id'            => $s->id,
            'status'        => $s->status,
            'provider'      => $s->provider,
            'score'         => $s->score,
            'auto_approved' => $s->auto_approved,
            'reject_reason' => $s->reject_reason,
            'expires_at'    => $s->expires_at,
            'completed_at'  => $s->completed_at,
        ];
        if ($full) {
            $base['document']  = $s->document;
            $base['selfie']    = $s->selfie;
            $base['signature'] = $s->signature;
            $base['result']    = $s->result;
        }
        return $base;
    }

    private function ok(string $message, $data = null, int $code = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }

    private function fail($validator): JsonResponse
    {
        return response()->json([
            'success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors(),
        ], 422);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Sesi eKYC tidak ditemukan.'], 404);
    }
}
