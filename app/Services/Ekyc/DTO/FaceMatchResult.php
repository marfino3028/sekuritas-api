<?php

namespace App\Services\Ekyc\DTO;

/**
 * Hasil face match antara selfie dan foto KTP.
 */
class FaceMatchResult
{
    public function __construct(
        public readonly bool $matched = false,
        public readonly int $score = 0,          // 0-100 (similarity)
        public readonly array $embedding = [],    // vektor wajah (cek duplikat)
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'face_matched'     => $this->matched,
            'face_match_score' => $this->score,
            'face_embedding'   => $this->embedding,
        ];
    }
}
