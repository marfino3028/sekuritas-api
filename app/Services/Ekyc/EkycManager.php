<?php

namespace App\Services\Ekyc;

use App\Services\Ekyc\Contracts\EkycProvider;
use App\Services\Ekyc\Providers\FastApiProvider;
use App\Services\Ekyc\Providers\StubProvider;
use InvalidArgumentException;

/**
 * EkycManager — factory yang me-resolve provider AI eKYC berdasar konfigurasi.
 *
 * Menambah provider baru (mis. AdvanceAiProvider) cukup: buat class implements
 * EkycProvider, daftarkan di sini, dan set EKYC_PROVIDER di .env. Tidak ada
 * perubahan pada controller/service/frontend.
 */
class EkycManager
{
    /** @var array<string, EkycProvider> */
    private array $resolved = [];

    public function provider(?string $name = null): EkycProvider
    {
        $name ??= config('ekyc.provider', 'stub');

        return $this->resolved[$name] ??= $this->make($name);
    }

    private function make(string $name): EkycProvider
    {
        return match ($name) {
            'stub'       => new StubProvider(),
            'fastapi'    => new FastApiProvider(),
            'sumsub'     => new \App\Services\Ekyc\Providers\SumsubProvider(),
            'veriff'     => new \App\Services\Ekyc\Providers\VeriffProvider(),
            'advance_ai' => new \App\Services\Ekyc\Providers\AdvanceAiProvider(),
            default      => throw new InvalidArgumentException("Provider eKYC tidak dikenal: {$name}"),
        };
    }
}
