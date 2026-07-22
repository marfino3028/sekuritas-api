<?php

namespace App\Services\SInvest\Providers;

use App\Models\User;
use App\Services\SInvest\Contracts\SInvestProvider;
use RuntimeException;

/**
 * KseiProvider — integrasi S-INVEST (KSEI) ASLI.
 *
 * KERANGKA. Implementasi HTTP asli menyusul setelah kredensial + spesifikasi
 * teknis KSEI tersedia (lihat file `prompt integrasi s-invest.md`). Dibiarkan
 * melempar error yang jelas agar tidak dipakai di produksi sebelum siap.
 */
class KseiProvider implements SInvestProvider
{
    public function name(): string
    {
        return 'ksei';
    }

    public function registerInvestor(User $user): array
    {
        $cfg = config('sinvest.ksei');
        if (empty($cfg['base_url']) || empty($cfg['user']) || empty($cfg['secret'])) {
            throw new RuntimeException(
                'Integrasi S-INVEST (KSEI) belum dikonfigurasi. Set SINVEST_BASE_URL/USER/SECRET, '
                . 'lalu implementasikan pemanggilan API sesuai spesifikasi KSEI.'
            );
        }

        // TODO(prod): susun payload data investor (dari tabel kyc), kirim ke S-INVEST
        // dgn TLS/cert, parse SID + IFUA, tangani kode error KSEI, retry idempoten.
        //
        // $res = Http::withOptions(['cert' => $cfg['cert_path']])
        //     ->timeout($cfg['timeout'])
        //     ->withBasicAuth($cfg['user'], $cfg['secret'])
        //     ->post(rtrim($cfg['base_url'], '/').'/investor/register', $payload)
        //     ->throw()->json();
        // return ['sid_number' => $res['sid'], 'ifua_number' => $res['ifua'], 'raw' => $res];

        throw new RuntimeException('KseiProvider::registerInvestor belum diimplementasikan.');
    }
}
