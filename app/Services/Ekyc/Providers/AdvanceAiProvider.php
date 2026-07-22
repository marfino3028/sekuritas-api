<?php

namespace App\Services\Ekyc\Providers;

use App\Services\Ekyc\Contracts\EkycProvider;
use App\Services\Ekyc\DTO\FaceMatchResult;
use App\Services\Ekyc\DTO\LivenessResult;
use App\Services\Ekyc\DTO\OcrResult;
use RuntimeException;

/**
 * AdvanceAiProvider — KERANGKA integrasi ADVANCE.AI.
 * Produksi: panggil endpoint OCR/Liveness/Face-Compare ADVANCE.AI (signed request),
 * petakan respons → DTO. Aktifkan via EKYC_PROVIDER=advance_ai + kredensial.
 */
class AdvanceAiProvider implements EkycProvider
{
    public function name(): string
    {
        return 'advance_ai';
    }

    private function guard(): void
    {
        if (empty(config('ekyc.vendors.advance_ai.access_key')) || empty(config('ekyc.vendors.advance_ai.secret_key'))) {
            throw new RuntimeException('ADVANCE.AI belum dikonfigurasi (ADVANCE_AI_ACCESS_KEY/SECRET_KEY).');
        }
    }

    public function ocr(string $imagePath): OcrResult
    {
        $this->guard();
        throw new RuntimeException('AdvanceAiProvider::ocr belum diimplementasikan.');
    }

    public function liveness(string $selfiePath): LivenessResult
    {
        $this->guard();
        throw new RuntimeException('AdvanceAiProvider::liveness belum diimplementasikan.');
    }

    public function faceMatch(string $selfiePath, string $ktpPath): FaceMatchResult
    {
        $this->guard();
        throw new RuntimeException('AdvanceAiProvider::faceMatch belum diimplementasikan.');
    }
}
