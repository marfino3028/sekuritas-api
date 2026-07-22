<?php

namespace App\Services\Ekyc\Providers;

use App\Services\Ekyc\Contracts\EkycProvider;
use App\Services\Ekyc\DTO\FaceMatchResult;
use App\Services\Ekyc\DTO\LivenessResult;
use App\Services\Ekyc\DTO\OcrResult;
use App\Services\Ekyc\EkycFileStore;

/**
 * StubProvider — provider eKYC self-hosted "nol biaya" untuk tahap awal/demo.
 *
 * Tidak memanggil layanan eksternal. Hasil dibuat DETERMINISTIK berdasar hash
 * file gambar sehingga stabil untuk pengujian, dengan beberapa heuristik kualitas
 * sederhana (ukuran file kecil → blur/low-light). Cocok sebagai default sebelum
 * FastAPI (PaddleOCR + InsightFace) atau provider komersial diaktifkan.
 *
 * OCR asli sebaiknya dilakukan on-device (ML Kit di Flutter, Tesseract.js di web)
 * lalu field hasil OCR dikirim ke server; StubProvider di sini hanya mensimulasikan
 * skor & flag kualitas bila server perlu memvalidasi ulang.
 */
class StubProvider implements EkycProvider
{
    public function name(): string
    {
        return 'stub';
    }

    public function ocr(string $imagePath): OcrResult
    {
        [$size, $seed] = $this->inspect($imagePath);

        // Skor turun bila file sangat kecil (indikasi gambar buram / resolusi rendah)
        $confidence = $size < 40_000 ? 55 : 92;

        return new OcrResult(
            confidence: $confidence,
            isBlur: $size < 25_000,
            isLowLight: $size < 30_000,
            isScreenshot: false,
            raw: [
                'provider' => 'stub',
                'note'     => 'OCR disimulasikan. Kirim field hasil OCR on-device (ML Kit/Tesseract) via payload.',
                'seed'     => $seed,
            ],
        );
    }

    public function liveness(string $selfiePath): LivenessResult
    {
        [$size, $seed] = $this->inspect($selfiePath);
        $score  = $size < 30_000 ? 48 : 95;
        $passed = $score >= config('ekyc.thresholds.liveness', 80);

        return new LivenessResult(
            passed: $passed,
            score: $score,
            isPrintedPhoto: false,
            isReplay: false,
            raw: ['provider' => 'stub', 'seed' => $seed],
        );
    }

    public function faceMatch(string $selfiePath, string $ktpPath): FaceMatchResult
    {
        [, $seedA] = $this->inspect($selfiePath);
        [, $seedB] = $this->inspect($ktpPath);

        // Skor mirip-stabil di rentang tinggi untuk demo (85-98)
        $score   = 85 + (crc32($seedA . $seedB) % 14);
        $matched = $score >= config('ekyc.thresholds.face_match', 80);

        return new FaceMatchResult(
            matched: $matched,
            score: $score,
            embedding: [], // embedding nyata dihasilkan oleh InsightFace di FastAPI
            raw: ['provider' => 'stub'],
        );
    }

    /**
     * Ambil ukuran & hash file sebagai basis skor deterministik.
     * @return array{0:int,1:string} [size, sha1]
     */
    private function inspect(string $path): array
    {
        $content = EkycFileStore::get($path);
        if ($content === '') {
            return [0, sha1($path)];
        }

        return [strlen($content), sha1($content)];
    }
}
