<?php

namespace App\Services\Ekyc\Contracts;

use App\Services\Ekyc\DTO\FaceMatchResult;
use App\Services\Ekyc\DTO\LivenessResult;
use App\Services\Ekyc\DTO\OcrResult;

/**
 * Kontrak provider AI eKYC.
 *
 * Semua provider (stub self-hosted, FastAPI PaddleOCR/InsightFace, atau layanan
 * komersial seperti ADVANCE.AI/Sumsub/Veriff) HARUS mengimplementasikan interface
 * ini. Dengan begitu Laravel (API Gateway), Nuxt, dan Flutter tidak perlu diubah
 * saat provider diganti — cukup tambah adapter baru & ubah config('ekyc.provider').
 */
interface EkycProvider
{
    /** Nama provider (untuk logging/audit). */
    public function name(): string;

    /**
     * OCR dokumen KTP.
     * @param string $imagePath Path file di disk storage aplikasi.
     */
    public function ocr(string $imagePath): OcrResult;

    /**
     * Passive liveness detection pada foto selfie.
     *
     * @param string|null $expectedNik NIK hasil OCR KTP sebelumnya (opsional).
     *                                 Kalau diisi dan provider mendukung cek
     *                                 "selfie sambil pegang KTP", provider akan
     *                                 mencocokkan NIK yang kebaca di foto selfie
     *                                 tsb terhadap NIK ini (LivenessResult::$nikMatch).
     */
    public function liveness(string $selfiePath, ?string $expectedNik = null): LivenessResult;

    /**
     * Face match antara selfie dan foto wajah pada KTP.
     */
    public function faceMatch(string $selfiePath, string $ktpPath): FaceMatchResult;
}
