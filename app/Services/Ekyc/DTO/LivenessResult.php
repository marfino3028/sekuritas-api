<?php

namespace App\Services\Ekyc\DTO;

/**
 * Hasil passive liveness detection (wajah asli vs foto/replay).
 */
class LivenessResult
{
    public function __construct(
        public readonly bool $passed = false,
        public readonly int $score = 0,          // 0-100
        public readonly bool $isPrintedPhoto = false,
        public readonly bool $isReplay = false,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'liveness_passed'  => $this->passed,
            'liveness_score'   => $this->score,
            'is_printed_photo' => $this->isPrintedPhoto,
            'is_replay'        => $this->isReplay,
        ];
    }
}
