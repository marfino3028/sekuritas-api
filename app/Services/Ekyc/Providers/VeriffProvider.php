<?php

namespace App\Services\Ekyc\Providers;

use App\Services\Ekyc\Contracts\EkycProvider;
use App\Services\Ekyc\DTO\FaceMatchResult;
use App\Services\Ekyc\DTO\LivenessResult;
use App\Services\Ekyc\DTO\OcrResult;
use RuntimeException;

/**
 * VeriffProvider — KERANGKA integrasi Veriff (https://veriff.com).
 * Produksi: buat session, kirim media, verifikasi HMAC webhook, petakan decision → DTO.
 * Aktifkan via EKYC_PROVIDER=veriff + kredensial.
 */
class VeriffProvider implements EkycProvider
{
    public function name(): string
    {
        return 'veriff';
    }

    private function guard(): void
    {
        if (empty(config('ekyc.vendors.veriff.api_key'))) {
            throw new RuntimeException('Veriff belum dikonfigurasi (VERIFF_API_KEY).');
        }
    }

    public function ocr(string $imagePath): OcrResult
    {
        $this->guard();
        throw new RuntimeException('VeriffProvider::ocr belum diimplementasikan.');
    }

    public function liveness(string $selfiePath, ?string $expectedNik = null): LivenessResult
    {
        $this->guard();
        throw new RuntimeException('VeriffProvider::liveness belum diimplementasikan.');
    }

    public function faceMatch(string $selfiePath, string $ktpPath): FaceMatchResult
    {
        $this->guard();
        throw new RuntimeException('VeriffProvider::faceMatch belum diimplementasikan.');
    }
}
