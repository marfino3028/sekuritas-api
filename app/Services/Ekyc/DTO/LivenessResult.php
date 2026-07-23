<?php

namespace App\Services\Ekyc\DTO;

/**
 * Hasil passive liveness detection (wajah asli vs foto/replay).
 *
 * Field ktp_detected..idFaceMatchScore adalah hasil pelengkap OPSIONAL: kalau
 * di foto selfie itu ada juga KTP yang ikut kefoto ("selfie sambil pegang
 * KTP"), provider (FastApiProvider → Nanonets-OCR-s) membaca NIK di foto dan
 * mencocokkan wajah kartu vs wajah orangnya. Provider yang tidak mendukung
 * fitur ini akan mengembalikan null untuk field-field tersebut.
 */
class LivenessResult
{
    public function __construct(
        public readonly bool $passed = false,
        public readonly int $score = 0,          // 0-100
        public readonly bool $isPrintedPhoto = false,
        public readonly bool $isReplay = false,
        public readonly ?bool $ktpDetected = null,
        public readonly ?string $nikInPhoto = null,
        public readonly ?bool $nikMatch = null,
        public readonly ?bool $idFaceMatch = null,
        public readonly ?int $idFaceMatchScore = null, // 0-100
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'liveness_passed'      => $this->passed,
            'liveness_score'       => $this->score,
            'is_printed_photo'     => $this->isPrintedPhoto,
            'is_replay'            => $this->isReplay,
            'ktp_detected'         => $this->ktpDetected,
            'nik_in_photo'         => $this->nikInPhoto,
            'nik_match'            => $this->nikMatch,
            'id_face_match'        => $this->idFaceMatch,
            'id_face_match_score'  => $this->idFaceMatchScore,
        ];
    }
}
