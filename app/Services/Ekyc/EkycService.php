<?php

namespace App\Services\Ekyc;

use App\Models\EkycDocument;
use App\Models\EkycLog;
use App\Models\EkycResult;
use App\Models\EkycSelfie;
use App\Models\EkycSession;
use App\Models\Kyc;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * EkycService — orkestrator business flow eKYC (Laravel sebagai API Gateway).
 *
 * Flow: createSession → runOcr → submitSelfie(liveness+faceMatch)
 *       → sign → verify(hitung skor & keputusan).
 * Semua langkah AI didelegasikan ke provider via EkycManager (adapter pattern),
 * dan tiap pemanggilan dicatat ke ekyc_logs untuk audit & anti-fraud.
 */
class EkycService
{
    /**
     * Flag yang SELALU berarti tolak otomatis — indikasi kualitas dokumen
     * buruk atau upaya spoofing yang jelas. Flag di luar daftar ini (mis.
     * ketidakcocokan dari sinyal sekunder yang kurang reliable) tidak
     * langsung menolak, tapi mendorong sesi ke status REVIEW untuk dicek
     * admin secara manual — lihat verify().
     */
    private const HARD_FRAUD_FLAGS = [
        'ktp_blur',
        'ktp_low_light',
        'ktp_screenshot',
        'selfie_printed_photo',
        'selfie_replay',
        'liveness_failed',
        'selfie_ktp_face_mismatch',
        'duplicate_nik',
    ];

    public function __construct(
        private readonly EkycManager $manager,
    ) {}

    /** Buat sesi eKYC baru untuk seorang user. */
    public function createSession(int $userId, array $meta = []): EkycSession
    {
        return EkycSession::create([
            'user_id'    => $userId,
            'status'     => EkycSession::STATUS_CREATED,
            'provider'   => config('ekyc.provider', 'stub'),
            'meta'       => $meta,
            'expires_at' => Carbon::now()->addMinutes((int) config('ekyc.session_ttl', 60)),
        ]);
    }

    /**
     * Langkah 1 — OCR KTP.
     * @param array $override Field OCR hasil on-device (ML Kit/Tesseract) yang
     *                        lebih dipercaya daripada hasil server-side.
     */
    public function runOcr(EkycSession $session, string $imagePath, array $override = []): EkycDocument
    {
        $this->guard($session);

        $result = $this->timed($session, 'ocr', fn () => $this->manager->provider()->ocr($imagePath));

        $data = array_merge(
            $result->toArray(),
            array_filter($override, fn ($v) => $v !== null && $v !== ''),
            ['session_id' => $session->id, 'type' => 'ktp', 'image_path' => $imagePath],
        );

        $doc = EkycDocument::updateOrCreate(['session_id' => $session->id], $data);

        $session->update(['status' => EkycSession::STATUS_OCR_DONE]);

        return $doc;
    }

    /**
     * Langkah 2 — Selfie: passive liveness + face match terhadap KTP.
     */
    public function submitSelfie(EkycSession $session, string $selfiePath): EkycSelfie
    {
        $this->guard($session);

        $doc = $session->document;
        if (! $doc) {
            throw new RuntimeException('OCR KTP harus dilakukan sebelum selfie.');
        }

        $liveness = $this->timed($session, 'liveness',
            fn () => $this->manager->provider()->liveness($selfiePath, $doc->nik));

        $faceMatch = $this->timed($session, 'face_match',
            fn () => $this->manager->provider()->faceMatch($selfiePath, $doc->image_path));

        $selfie = EkycSelfie::updateOrCreate(
            ['session_id' => $session->id],
            array_merge(
                ['session_id' => $session->id, 'image_path' => $selfiePath],
                $liveness->toArray(),
                $faceMatch->toArray(),
            )
        );

        $status = $liveness->passed
            ? ($faceMatch->matched ? EkycSession::STATUS_FACE_MATCHED : EkycSession::STATUS_LIVENESS_PASSED)
            : EkycSession::STATUS_SELFIE_DONE;
        $session->update(['status' => $status]);

        return $selfie;
    }

    /**
     * Langkah 2a — Simpan selfie & jalankan passive liveness.
     */
    public function runLiveness(EkycSession $session, string $selfiePath): EkycSelfie
    {
        $this->guard($session);

        $expectedNik = $session->document?->nik;

        $liveness = $this->timed($session, 'liveness',
            fn () => $this->manager->provider()->liveness($selfiePath, $expectedNik));

        $selfie = EkycSelfie::updateOrCreate(
            ['session_id' => $session->id],
            array_merge(['session_id' => $session->id, 'image_path' => $selfiePath], $liveness->toArray())
        );

        $session->update([
            'status' => ($liveness->passed && $session->status === EkycSession::STATUS_OCR_DONE)
                ? EkycSession::STATUS_LIVENESS_PASSED
                : EkycSession::STATUS_SELFIE_DONE,
        ]);

        return $selfie;
    }

    /**
     * Langkah 2b — Face match selfie tersimpan terhadap foto KTP.
     */
    public function runFaceMatch(EkycSession $session): EkycSelfie
    {
        $this->guard($session);

        $doc    = $session->document;
        $selfie = $session->selfie;
        if (! $doc || ! $selfie) {
            throw new RuntimeException('OCR KTP & selfie/liveness harus dilakukan lebih dulu.');
        }

        $faceMatch = $this->timed($session, 'face_match',
            fn () => $this->manager->provider()->faceMatch($selfie->image_path, $doc->image_path));

        $selfie->update($faceMatch->toArray());

        if ($faceMatch->matched) {
            $session->update(['status' => EkycSession::STATUS_FACE_MATCHED]);
        }

        return $selfie->fresh();
    }

    /**
     * Langkah 4 — Verifikasi akhir: hitung skor agregat & tentukan keputusan.
     * Jika 'approved', tulis identitas hasil OCR ke tabel kyc (status pending)
     * agar masuk alur review admin yang sudah ada.
     */
    public function verify(EkycSession $session): EkycResult
    {
        $this->guard($session);

        $doc    = $session->document;
        $selfie = $session->selfie;
        if (! $doc || ! $selfie) {
            throw new RuntimeException('Sesi belum lengkap (OCR & selfie wajib).');
        }

        $t = config('ekyc.thresholds');
        $ocrScore      = (int) ($doc->ocr_confidence ?? 0);
        $livenessScore = (int) ($selfie->liveness_score ?? 0);
        $faceScore     = (int) ($selfie->face_match_score ?? 0);
        $final         = (int) round(($ocrScore * 0.2) + ($livenessScore * 0.35) + ($faceScore * 0.45));

        $flags = $this->collectFraudFlags($doc, $selfie);
        $hardFlags = array_intersect($flags, self::HARD_FRAUD_FLAGS);

        $decision = match (true) {
            // Flag berat (kualitas dokumen buruk / spoofing jelas / duplikat NIK) → tolak.
            !empty($hardFlags)                            => EkycResult::DECISION_REJECTED,
            // Skor gabungan terlalu rendah → tolak, apapun flag lainnya.
            $final <  $t['min_reject']                     => EkycResult::DECISION_REJECTED,
            // Skor tinggi DAN tidak ada flag sama sekali (termasuk yang soft) → approve otomatis.
            empty($flags) && $final >= $t['auto_approve']  => EkycResult::DECISION_APPROVED,
            // Sisanya (ada flag soft yang ambigu, atau skor di zona abu-abu) → review admin.
            default                                         => EkycResult::DECISION_REVIEW,
        };

        $result = EkycResult::updateOrCreate(
            ['session_id' => $session->id],
            [
                'ocr_score'        => $ocrScore,
                'liveness_score'   => $livenessScore,
                'face_match_score' => $faceScore,
                'final_score'      => $final,
                'decision'         => $decision,
                'flags'            => $flags,
            ]
        );

        $session->update([
            'score'         => $final,
            'auto_approved' => $decision === EkycResult::DECISION_APPROVED,
            'status'        => $decision === EkycResult::DECISION_REJECTED
                ? EkycSession::STATUS_REJECTED
                : EkycSession::STATUS_VERIFIED,
            'reject_reason' => $decision === EkycResult::DECISION_REJECTED ? implode(', ', $hardFlags ?: ['skor di bawah ambang']) : null,
            'completed_at'  => Carbon::now(),
        ]);

        // Salin path foto KTP/selfie ke tabel kyc TERLEPAS dari keputusan akhir eKYC,
        // supaya endpoint /kyc/submit (yang cek kyc.ktp_photo_path/selfie_photo_path)
        // tidak pernah stuck walau skor eKYC ditolak otomatis — tetap masuk antrian
        // review admin manual di CMS (lihat syncToKyc()).
        // Pengecualian: jika NIK milik user lain (duplicate_nik), skip sync
        // agar tidak crash UNIQUE constraint — keputusan sudah REJECTED.
        if (! in_array('duplicate_nik', $flags)) {
            $this->syncToKyc($session, $doc);
        }

        $this->log($session, 'verify', 'success', 0, ['final' => $final, 'decision' => $decision]);

        return $result;
    }

    /** Salin identitas hasil eKYC ke tabel kyc (status pending untuk review admin). */
    private function syncToKyc(EkycSession $session, EkycDocument $doc): void
    {
        try {
            Kyc::updateOrCreate(
                ['user_id' => $session->user_id],
                array_filter([
                    'nik'               => $doc->nik,
                    'birth_date'        => $doc->birth_date,
                    'gender'            => $this->normalizeGender($doc->gender),
                    'address'           => $doc->address,
                    'ktp_photo_path'    => $doc->image_path,
                    'selfie_photo_path' => $session->selfie?->image_path,
                    'status'            => Kyc::STATUS_PENDING,
                    'submitted_at'      => Carbon::now(),
                ], fn ($v) => $v !== null)
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // NIK sudah terdaftar di akun lain — log saja, jangan crash.
            // Keputusan eKYC sudah handled via collectFraudFlags (duplicate_nik).
            \Illuminate\Support\Facades\Log::warning('[eKYC] syncToKyc: NIK sudah terdaftar di akun lain, sync dilewati.', [
                'user_id'    => $session->user_id,
                'session_id' => $session->id,
                'nik'        => $doc->nik,
            ]);
        }
    }

    /** Normalisasi gender OCR (LAKI-LAKI/PEREMPUAN/M/F) → 'M' | 'F' | null. */
    private function normalizeGender(?string $g): ?string
    {
        if (! $g) return null;
        $u = strtoupper($g);
        if (str_contains($u, 'PEREMPUAN') || $u === 'F' || $u === 'P') return 'F';
        if (str_contains($u, 'LAKI') || $u === 'M' || $u === 'L') return 'M';
        return null;
    }

    /**
     * Deteksi red-flag anti-fraud.
     *
     * Dua sumber sinyal wajah dipakai:
     *   - face_matched/face_match_score (endpoint /face-match, InsightFace,
     *     berbasis embedding) → sinyal UTAMA, dibuat khusus untuk task ini.
     *   - id_face_match (dibaca sekaligus oleh model vision-OCR saat /liveness,
     *     bukan model perbandingan wajah) → sinyal SEKUNDER, kurang reliable.
     * Kalau cuma sinyal sekunder yang gagal sementara sinyal utama cocok,
     * tidak langsung ditolak (supaya satu model yang noisy tidak sendirian
     * menjatuhkan keputusan tolak) — cukup ditandai utk review admin.
     *
     * Sama halnya nik_match (NIK yang terbaca dari foto KTP yang dipegang
     * saat selfie) rawan salah baca karena sudut/blur, jadi juga hanya
     * ditandai review, bukan tolak otomatis.
     */
    private function collectFraudFlags(EkycDocument $doc, EkycSelfie $selfie): array
    {
        $flags = [];
        if ($doc->is_blur)          $flags[] = 'ktp_blur';
        if ($doc->is_low_light)     $flags[] = 'ktp_low_light';
        if ($doc->is_screenshot)    $flags[] = 'ktp_screenshot';
        if ($selfie->is_printed_photo) $flags[] = 'selfie_printed_photo';
        if ($selfie->is_replay)     $flags[] = 'selfie_replay';
        if ($selfie->liveness_passed === false) $flags[] = 'liveness_failed';

        $primaryFaceMismatch   = $selfie->face_matched === false;
        $secondaryFaceMismatch = $selfie->id_face_match === false;
        if ($primaryFaceMismatch && $secondaryFaceMismatch) {
            // Kedua model sepakat wajah tidak cocok → hard flag, tolak otomatis.
            $flags[] = 'selfie_ktp_face_mismatch';
        } elseif ($primaryFaceMismatch || $secondaryFaceMismatch) {
            // Cuma satu model yang bilang tidak cocok → ambigu, minta review manual.
            $flags[] = 'selfie_ktp_face_mismatch_review';
        }

        if ($selfie->nik_match === false) {
            $flags[] = 'selfie_ktp_nik_mismatch_review';
        }

        // Cek duplikat KTP: NIK yang sama sudah dipakai user lain
        if ($doc->nik) {
            $dupe = Kyc::where('nik', $doc->nik)
                ->where('user_id', '!=', $doc->session->user_id)
                ->exists();
            if ($dupe) $flags[] = 'duplicate_nik';
        }

        return $flags;
    }

    private function guard(EkycSession $session): void
    {
        if ($session->isExpired()) {
            $session->update(['status' => EkycSession::STATUS_EXPIRED]);
            throw new RuntimeException('Sesi eKYC sudah kedaluwarsa. Mulai ulang.');
        }
    }

    /** Jalankan closure sambil mencatat latency & status ke ekyc_logs. */
    private function timed(EkycSession $session, string $step, callable $fn)
    {
        $start = microtime(true);
        try {
            $out = $fn();
            $this->log($session, $step, 'success', (int) ((microtime(true) - $start) * 1000));
            return $out;
        } catch (\Throwable $e) {
            $this->log($session, $step, 'failed', (int) ((microtime(true) - $start) * 1000), ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function log(EkycSession $session, string $step, string $status, int $latencyMs, array $meta = []): void
    {
        EkycLog::create([
            'session_id'    => $session->id,
            'step'          => $step,
            'provider'      => $session->provider,
            'status'        => $status,
            'latency_ms'    => $latencyMs,
            'response_meta' => $meta,
            'ip'            => request()->ip(),
        ]);
    }

    /** Simpan file upload ke disk eKYC (terenkripsi bila diaktifkan), kembalikan path. */
    public function storeUpload(int $userId, $file, string $kind): string
    {
        return EkycFileStore::put($userId, $file, $kind);
    }

    public function url(?string $path): ?string
    {
        return EkycFileStore::url($path);
    }
}
