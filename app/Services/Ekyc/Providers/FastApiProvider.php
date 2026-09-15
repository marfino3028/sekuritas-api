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
            birthPlace: $this->cleanBirthPlace($res['birth_place'] ?? null),
            birthDate: $this->normalizeDate($res['birth_date'] ?? null),
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

    public function liveness(string $selfiePath, ?string $expectedNik = null): LivenessResult
    {
        $res = $this->client()
            ->attach('file', $this->contents($selfiePath), basename($selfiePath))
            ->post('/liveness', $expectedNik ? ['expected_nik' => $expectedNik] : [])
            ->throw()
            ->json();

        return new LivenessResult(
            passed: (bool) ($res['passed'] ?? false),
            score: (int) ($res['score'] ?? 0),
            isPrintedPhoto: (bool) ($res['is_printed_photo'] ?? false),
            isReplay: (bool) ($res['is_replay'] ?? false),
            ktpDetected: array_key_exists('ktp_detected', $res) ? (bool) $res['ktp_detected'] : null,
            nikInPhoto: $res['nik_in_photo'] ?? null,
            nikMatch: array_key_exists('nik_match', $res) ? $res['nik_match'] : null,
            idFaceMatch: array_key_exists('id_face_match', $res) ? $res['id_face_match'] : null,
            idFaceMatchScore: isset($res['id_face_match_score']) ? (int) $res['id_face_match_score'] : null,
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

    /**
     * Engine OCR asli (Nanonets) mengembalikan tanggal format KTP "DD-MM-YYYY";
     * kolom DB bertipe date butuh "YYYY-MM-DD". Tanggal tak terbaca → null
     * (bukan 500), user tetap bisa mengoreksi manual.
     */
    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $value, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    /** "SURAKARTA, 26-07-2001" (field TTL KTP) → "SURAKARTA". */
    private function cleanBirthPlace(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = trim(preg_replace('/[,\s]+\d{1,2}[-\/.]\d{1,2}[-\/.]\d{4}\s*$/', '', $value));

        return $clean === '' ? null : $clean;
    }
}
