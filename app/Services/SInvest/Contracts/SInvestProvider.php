<?php

namespace App\Services\SInvest\Contracts;

use App\Models\User;

/**
 * Kontrak provider S-INVEST (KSEI). Mock & Ksei mengimplementasikan ini agar
 * SInvestService (orchestrator) tidak berubah saat integrasi asli diaktifkan.
 */
interface SInvestProvider
{
    public function name(): string;

    /**
     * Daftarkan investor ke S-INVEST → terbitkan SID & IFUA.
     *
     * @return array{sid_number:string,ifua_number:string,raw:array}
     */
    public function registerInvestor(User $user): array;
}
