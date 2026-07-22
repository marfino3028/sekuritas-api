<?php

namespace App\Services;

use App\Models\SidData;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * SInvestService — Mock integrasi dengan sistem S-INVEST KSEI.
 *
 * Di production, service ini akan melakukan HTTP call ke API S-INVEST
 * untuk mendaftarkan investor dan mendapatkan SID (Single Investor Identification)
 * serta IFUA (Investor Fund Unit Account).
 *
 * Untuk keperluan demo, semua API call disimulasikan secara lokal.
 */
class SInvestService
{
    /**
     * Generate SID & IFUA untuk nasabah yang sudah KYC approved.
     *
     * Proses:
     * 1. Simulasi delay 1-2 detik (meniru latency API eksternal)
     * 2. Generate nomor SID & IFUA unik dengan format KSEI
     * 3. Simpan ke tabel sid_data
     * 4. Update status SID user menjadi 'active'
     *
     * @param int $userId ID user yang akan di-generate SID-nya
     * @return array Data SID yang berhasil di-generate
     * @throws \Exception Jika user tidak ditemukan atau sudah memiliki SID aktif
     */
    public function __construct(
        private \App\Services\SInvest\SInvestManager $manager = new \App\Services\SInvest\SInvestManager(),
    ) {}

    public function generateSid(int $userId): array
    {
        $user = User::findOrFail($userId);

        // Cek apakah SID sudah aktif
        if ($user->sid_status === User::SID_ACTIVE) {
            throw new \Exception("Nasabah {$user->name} sudah memiliki SID aktif: {$user->sid_number}");
        }

        // Update status ke 'processing' dahulu
        $user->update(['sid_status' => User::SID_PROCESSING]);

        try {
            // Delegasikan penerbitan ke provider aktif (mock/ksei) — adapter pattern
            $result = $this->manager->provider()->registerInvestor($user);
        } catch (\Throwable $e) {
            // Kembalikan status agar tidak terkunci di 'processing'
            $user->update(['sid_status' => User::SID_NOT_GENERATED]);
            throw $e;
        }

        $sidNumber  = $result['sid_number'];
        $ifuaNumber = $result['ifua_number'];

        // Simpan ke tabel sid_data
        $sidData = SidData::create([
            'user_id'           => $userId,
            'sid_number'        => $sidNumber,
            'ifua_number'       => $ifuaNumber,
            's_invest_response' => $result['raw'],
            'generated_at'      => Carbon::now(),
        ]);

        // Update user: aktifkan SID
        $user->update([
            'sid_status'  => User::SID_ACTIVE,
            'sid_number'  => $sidNumber,
            'ifua_number' => $ifuaNumber,
            'status'      => User::STATUS_ACTIVE,
        ]);

        Log::info('[SInvest] SID diterbitkan', [
            'driver'     => config('sinvest.driver', 'mock'),
            'user_id'    => $userId,
            'sid_number' => $sidNumber,
        ]);

        return [
            'sid_number'        => $sidNumber,
            'ifua_number'       => $ifuaNumber,
            's_invest_response' => $result['raw'],
            'generated_at'      => $sidData->generated_at,
        ];
    }

    /**
     * Cek status SID nasabah di S-INVEST.
     * (Mock: hanya membaca dari database lokal)
     *
     * @param int $userId
     * @return array
     */
    public function checkSidStatus(int $userId): array
    {
        $user    = User::findOrFail($userId);
        $sidData = SidData::where('user_id', $userId)->first();

        return [
            'user_id'    => $userId,
            'sid_status' => $user->sid_status,
            'sid_number' => $user->sid_number,
            'ifua_number'=> $user->ifua_number,
            'data'       => $sidData,
            'mock'       => true,
        ];
    }
}
