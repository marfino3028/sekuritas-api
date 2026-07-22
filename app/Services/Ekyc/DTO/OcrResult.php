<?php

namespace App\Services\Ekyc\DTO;

/**
 * Hasil OCR dokumen KTP dari provider eKYC.
 * DTO ini menjaga kontrak tetap stabil meski provider AI diganti.
 */
class OcrResult
{
    public function __construct(
        public readonly ?string $nik = null,
        public readonly ?string $name = null,
        public readonly ?string $birthPlace = null,
        public readonly ?string $birthDate = null,   // format Y-m-d
        public readonly ?string $gender = null,
        public readonly ?string $address = null,
        public readonly ?string $religion = null,
        public readonly ?string $maritalStatus = null,
        public readonly ?string $occupation = null,
        public readonly int $confidence = 0,          // 0-100
        public readonly bool $isBlur = false,
        public readonly bool $isLowLight = false,
        public readonly bool $isScreenshot = false,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'nik'            => $this->nik,
            'name'           => $this->name,
            'birth_place'    => $this->birthPlace,
            'birth_date'     => $this->birthDate,
            'gender'         => $this->gender,
            'address'        => $this->address,
            'religion'       => $this->religion,
            'marital_status' => $this->maritalStatus,
            'occupation'     => $this->occupation,
            'ocr_confidence' => $this->confidence,
            'is_blur'        => $this->isBlur,
            'is_low_light'   => $this->isLowLight,
            'is_screenshot'  => $this->isScreenshot,
            'raw_ocr'        => $this->raw,
        ];
    }
}
