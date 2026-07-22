<?php

namespace App\Services\SInvest\Providers;

use App\Models\SidData;
use App\Models\User;
use App\Services\SInvest\Contracts\SInvestProvider;
use Carbon\Carbon;

/**
 * MockProvider — simulasi penerbitan SID/IFUA (default). Menghasilkan nomor
 * unik berformat KSEI tanpa memanggil layanan eksternal (demo/UAT).
 */
class MockProvider implements SInvestProvider
{
    public function name(): string
    {
        return 'mock';
    }

    public function registerInvestor(User $user): array
    {
        // Simulasi latency API
        usleep(random_int(300_000, 800_000));

        $sid  = 'SID' . str_pad((string) mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        $ifua = 'IFUA' . str_pad((string) mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        while (SidData::where('sid_number', $sid)->exists()) {
            $sid = 'SID' . str_pad((string) mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        }
        while (SidData::where('ifua_number', $ifua)->exists()) {
            $ifua = 'IFUA' . str_pad((string) mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        }

        return [
            'sid_number'  => $sid,
            'ifua_number' => $ifua,
            'raw'         => [
                'status'           => 'SUCCESS',
                'participant_code' => config('sinvest.participant_code', 'VS001'),
                'sid_number'       => $sid,
                'ifua_number'      => $ifua,
                'investor_name'    => $user->name,
                'registered_at'    => Carbon::now()->toIso8601String(),
                'mock'             => true,
            ],
        ];
    }
}
