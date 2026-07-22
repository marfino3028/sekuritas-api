<?php

namespace App\Services\Ekyc\Providers;

use App\Services\Ekyc\Contracts\EkycProvider;
use App\Services\Ekyc\DTO\FaceMatchResult;
use App\Services\Ekyc\DTO\LivenessResult;
use App\Services\Ekyc\DTO\OcrResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * FastApiProvider — memanggil AI service Python (FastAPI) self-hosted:
 * PaddleOCR (OCR KTP), InsightFace (face match), Silent-Face (passive liveness).
 *
 * Kontrak endpoint (lihat repo sekuritas-ai):
 *   POST /ocr        (multipart: file)          → { nik, name, ..., confidence, flags }
 *   POST /liveness   (multipart: file)          → { passed, score, is_printed_photo, is_replay }
 *   POST /face-match (multipart: selfie, ktp)   → { matched, score, embedding }
 */
class FastApiProvider implements EkycProvider
{
    public function name(): string
    {
        return 'fastapi';
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('ekyc.fastapi.base_url'), '/'))
            ->timeout(config('ekyc.fastapi.timeout', 30))
            ->withHeaders(['X-Api-Key' => config('ekyc.fastapi.api_key')]);
    }

    private function contents(string $path): string
    {
        return \App\Services\Ekyc\EkycFileStore::get($path);
    }

    public function ocr(string $imagePath): OcrResult
    {
        $res = $this->client()
            ->attach('file', $this->contents($imagePath), basename($imagePath))
            ->post('/ocr')
            ->throw()
            ->json();

        return new OcrResult(
            nik: $res['nik'] ?? null,
            name: $res['name'] ?? null,
            birthPlace: $res['birth_place'] ?? null,
            birthDate: $res['birth_date'] ?? null,
            gender: $res['gender'] ?? null,
            address: $res['address'] ?? null,
            religion: $res['religion'] ?? null,
            maritalStatus: $res['marital_status'] ?? null,
            occupation: $res['occupation'] ?? null,
            confidence: (int) ($res['confidence'] ?? 0),
            isBlur: (bool) ($res['is_blur'] ?? false),
            isLowLight: (bool) ($res['is_low_light'] ?? false),
            isScreenshot: (bool) ($res['is_screenshot'] ?? false),
            raw: $res,
        );
    }

    public function liveness(string $selfiePath): LivenessResult
    {
        $res = $this->client()
            ->attach('file', $this->contents($selfiePath), basename($selfiePath))
            ->post('/liveness')
            ->throw()
            ->json();

        return new LivenessResult(
            passed: (bool) ($res['passed'] ?? false),
            score: (int) ($res['score'] ?? 0),
            isPrintedPhoto: (bool) ($res['is_printed_photo'] ?? false),
            isReplay: (bool) ($res['is_replay'] ?? false),
            raw: $res,
        );
    }

    public function faceMatch(string $selfiePath, string $ktpPath): FaceMatchResult
    {
        $res = $this->client()
            ->attach('selfie', $this->contents($selfiePath), basename($selfiePath))
            ->attach('ktp', $this->contents($ktpPath), basename($ktpPath))
            ->post('/face-match')
            ->throw()
            ->json();

        return new FaceMatchResult(
            matched: (bool) ($res['matched'] ?? false),
            score: (int) ($res['score'] ?? 0),
            embedding: $res['embedding'] ?? [],
            raw: $res,
        );
    }
}
