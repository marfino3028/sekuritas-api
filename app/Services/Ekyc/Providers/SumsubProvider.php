<?php

namespace App\Services\Ekyc\Providers;

use App\Services\Ekyc\Contracts\EkycProvider;
use App\Services\Ekyc\DTO\FaceMatchResult;
use App\Services\Ekyc\DTO\LivenessResult;
use App\Services\Ekyc\DTO\OcrResult;
use RuntimeException;

/**
 * SumsubProvider — KERANGKA integrasi Sumsub (https://sumsub.com).
 *
 * Catatan: Sumsub berbasis "applicant + inspection flow" (SDK + webhook), berbeda
 * dengan kontrak per-gambar di sini. Untuk produksi: buat applicant, kirim dokumen
 * (POST /resources/applicants/{id}/info/idDoc), ambil hasil (getApplicantStatus),
 * lalu petakan ke DTO. Aktifkan via EKYC_PROVIDER=sumsub + kredensial.
 */
class SumsubProvider implements EkycProvider
{
    public function name(): string
    {
        return 'sumsub';
    }

    private function guard(): void
    {
        if (empty(config('ekyc.vendors.sumsub.app_token')) || empty(config('ekyc.vendors.sumsub.secret_key'))) {
            throw new RuntimeException('Sumsub belum dikonfigurasi (SUMSUB_APP_TOKEN/SECRET_KEY).');
        }
    }

    public function ocr(string $imagePath): OcrResult
    {
        $this->guard();
        // TODO(prod): buat applicant + upload idDoc + parse hasil OCR Sumsub → OcrResult.
        throw new RuntimeException('SumsubProvider::ocr belum diimplementasikan.');
    }

    public function liveness(string $selfiePath): LivenessResult
    {
        $this->guard();
        throw new RuntimeException('SumsubProvider::liveness belum diimplementasikan.');
    }

    public function faceMatch(string $selfiePath, string $ktpPath): FaceMatchResult
    {
        $this->guard();
        throw new RuntimeException('SumsubProvider::faceMatch belum diimplementasikan.');
    }
}
