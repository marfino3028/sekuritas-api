<?php

namespace App\Services\SInvest;

use App\Services\SInvest\Contracts\SInvestProvider;
use App\Services\SInvest\Providers\KseiProvider;
use App\Services\SInvest\Providers\MockProvider;
use InvalidArgumentException;

/**
 * Resolve provider S-INVEST aktif berdasar config('sinvest.driver').
 */
class SInvestManager
{
    public function provider(?string $name = null): SInvestProvider
    {
        $name ??= config('sinvest.driver', 'mock');

        return match ($name) {
            'mock' => new MockProvider(),
            'ksei' => new KseiProvider(),
            default => throw new InvalidArgumentException("Driver S-INVEST tidak dikenal: {$name}"),
        };
    }
}
