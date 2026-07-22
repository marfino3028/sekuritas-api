<?php

namespace Database\Seeders;

use App\Models\EkycDocument;
use App\Models\EkycResult;
use App\Models\EkycSelfie;
use App\Models\EkycSession;
use App\Models\EkycSignature;
use App\Models\Kyc;
use App\Models\MutualFund;
use App\Models\Portfolio;
use App\Models\RiskProfile;
use App\Models\SidData;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seed nasabah (investor) demo lengkap dengan KYC, profil risiko, sesi eKYC,
 * SID, portofolio, dan transaksi — agar CMS, dashboard, halaman transaksi, dan
 * review eKYC ada isinya. Password semua nasabah: Nasabah@123
 */
class NasabahSeeder extends Seeder
{
    public function run(): void
    {
        $funds = MutualFund::where('is_active', true)->get();
        if ($funds->isEmpty()) {
            $this->command->warn('NasabahSeeder dilewati: produk reksa dana belum ada. Jalankan seedMutualFunds dulu.');
            return;
        }

        $names = [
            'Budi Santoso', 'Siti Rahmawati', 'Ahmad Fauzi', 'Dewi Lestari', 'Rizky Pratama',
            'Putri Anggraini', 'Eko Wijaya', 'Nur Aisyah', 'Bagus Setiawan', 'Maya Sari',
        ];

        // Distribusi status KYC: 6 approved, 2 pending, 2 rejected
        $kycPlan = ['approved','approved','approved','approved','approved','approved','pending','pending','rejected','rejected'];
        $risks   = ['conservative','moderate_conservative','moderate','moderate_aggressive','aggressive'];

        foreach ($names as $i => $name) {
            $kycStatus = $kycPlan[$i];
            $email     = strtolower(str_replace(' ', '.', $name)) . '@mail.test';
            $nik       = '32' . str_pad((string) (10000000000000 + $i * 7331), 14, '0', STR_PAD_LEFT);
            $gender    = in_array($i, [1,3,5,7,9]) ? 'F' : 'M';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'              => $name,
                    'phone'             => '08' . str_pad((string) (1200000000 + $i), 10, '0', STR_PAD_LEFT),
                    'password'          => Hash::make('Nasabah@123'),
                    'role'              => User::ROLE_USER,
                    'status'            => $kycStatus === 'approved' ? User::STATUS_ACTIVE : User::STATUS_PENDING,
                    'email_verified_at' => Carbon::now()->subDays(30 - $i),
                    'sid_status'        => $kycStatus === 'approved' ? User::SID_ACTIVE : User::SID_NOT_GENERATED,
                    'risk_profile_result' => $risks[$i % 5],
                ]
            );

            // Profil risiko
            RiskProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'answers' => array_map(fn () => rand(1, 5), range(1, 8)),
                    'result'  => $risks[$i % 5],
                    'score'   => [10, 30, 50, 70, 90][$i % 5],
                ]
            );

            // KYC
            Kyc::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'nik'                  => $nik,
                    'mother_maiden_name'   => 'Ibu ' . explode(' ', $name)[0],
                    'birth_date'           => Carbon::now()->subYears(25 + $i)->toDateString(),
                    'gender'               => $gender,
                    'marital_status'       => $i % 2 ? 'married' : 'single',
                    'education'            => ['sma','diploma','s1','s2'][$i % 4],
                    'occupation'           => ['karyawan_swasta','wiraswasta','profesional','pns'][$i % 4],
                    'income_level'         => ['5jt_10jt','10jt_25jt','25jt_50jt'][$i % 3],
                    'source_of_fund'       => ['gaji','usaha','investasi'][$i % 3],
                    'investment_objective' => ['pendidikan','pensiun','pertumbuhan_aset'][$i % 3],
                    'address'              => 'Jl. Melati No. ' . ($i + 1) . ', Jakarta',
                    'province'             => 'DKI Jakarta',
                    'city'                 => 'Jakarta Selatan',
                    'postal_code'          => '12' . str_pad((string) ($i * 11), 3, '0', STR_PAD_LEFT),
                    'status'               => $kycStatus,
                    'rejected_reason'      => $kycStatus === 'rejected' ? 'Foto KTP buram / data tidak sesuai.' : null,
                    'submitted_at'         => Carbon::now()->subDays(20 - $i),
                    'reviewed_at'          => $kycStatus === 'pending' ? null : Carbon::now()->subDays(18 - $i),
                ]
            );

            // Sesi eKYC (menggambarkan hasil verifikasi otomatis)
            $this->seedEkyc($user, $name, $nik, $gender, $kycStatus);

            // SID + portofolio + transaksi hanya untuk yang approved
            if ($kycStatus === 'approved') {
                $this->seedSid($user, $name);
                $this->seedPortfolioAndTransactions($user, $funds, $i);
            }
        }

        $this->command->info('Nasabah demo berhasil di-seed (' . count($names) . ' nasabah). Password: Nasabah@123');
    }

    private function seedEkyc(User $user, string $name, string $nik, string $gender, string $kycStatus): void
    {
        if (EkycSession::where('user_id', $user->id)->exists()) {
            return;
        }

        [$sessionStatus, $decision, $final, $auto] = match ($kycStatus) {
            'approved' => [EkycSession::STATUS_VERIFIED, EkycResult::DECISION_APPROVED, 92, true],
            'pending'  => [EkycSession::STATUS_VERIFIED, EkycResult::DECISION_REVIEW, 78, false],
            default    => [EkycSession::STATUS_REJECTED, EkycResult::DECISION_REJECTED, 44, false],
        };

        $session = EkycSession::create([
            'id'            => (string) Str::uuid(),
            'user_id'       => $user->id,
            'status'        => $sessionStatus,
            'provider'      => 'stub',
            'score'         => $final,
            'auto_approved' => $auto,
            'reject_reason' => $decision === EkycResult::DECISION_REJECTED ? 'ktp_blur, liveness_failed' : null,
            'completed_at'  => Carbon::now()->subDays(19),
        ]);

        EkycDocument::create([
            'session_id'     => $session->id,
            'type'           => 'ktp',
            'image_path'     => "ekyc/{$user->id}/ktp/demo.jpg",
            'nik'            => $nik,
            'name'           => strtoupper($name),
            'birth_place'    => 'JAKARTA',
            'birth_date'     => Carbon::now()->subYears(30)->toDateString(),
            'gender'         => $gender === 'F' ? 'PEREMPUAN' : 'LAKI-LAKI',
            'address'        => 'JL. MELATI, JAKARTA',
            'ocr_confidence' => $decision === EkycResult::DECISION_REJECTED ? 55 : 92,
            'is_blur'        => $decision === EkycResult::DECISION_REJECTED,
            'raw_ocr'        => ['engine' => 'stub', 'demo' => true],
        ]);

        EkycSelfie::create([
            'session_id'       => $session->id,
            'image_path'       => "ekyc/{$user->id}/selfie/demo.jpg",
            'liveness_passed'  => $decision !== EkycResult::DECISION_REJECTED,
            'liveness_score'   => $decision === EkycResult::DECISION_REJECTED ? 48 : 95,
            'face_matched'     => $decision === EkycResult::DECISION_APPROVED,
            'face_match_score' => $decision === EkycResult::DECISION_REJECTED ? 60 : ($decision === EkycResult::DECISION_REVIEW ? 79 : 93),
        ]);

        EkycSignature::create([
            'session_id' => $session->id,
            'provider'   => 'canvas',
            'image_path' => "ekyc/{$user->id}/signatures/demo.png",
            'status'     => 'signed',
            'signed_at'  => Carbon::now()->subDays(19),
        ]);

        EkycResult::create([
            'session_id'       => $session->id,
            'ocr_score'        => $decision === EkycResult::DECISION_REJECTED ? 55 : 92,
            'liveness_score'   => $decision === EkycResult::DECISION_REJECTED ? 48 : 95,
            'face_match_score' => $decision === EkycResult::DECISION_REJECTED ? 60 : ($decision === EkycResult::DECISION_REVIEW ? 79 : 93),
            'final_score'      => $final,
            'decision'         => $decision,
            'flags'            => $decision === EkycResult::DECISION_REJECTED ? ['ktp_blur', 'liveness_failed'] : [],
        ]);
    }

    private function seedSid(User $user, string $name): void
    {
        if (SidData::where('user_id', $user->id)->exists()) {
            return;
        }
        $sid  = 'SID' . str_pad((string) $user->id, 8, '0', STR_PAD_LEFT);
        $ifua = 'IFUA' . str_pad((string) $user->id, 8, '0', STR_PAD_LEFT);
        SidData::create([
            'user_id'           => $user->id,
            'sid_number'        => $sid,
            'ifua_number'       => $ifua,
            's_invest_response' => ['status' => 'SUCCESS', 'mock' => true, 'investor_name' => $name],
            'generated_at'      => Carbon::now()->subDays(17),
        ]);
        $user->update(['sid_number' => $sid, 'ifua_number' => $ifua]);
    }

    private function seedPortfolioAndTransactions(User $user, $funds, int $i): void
    {
        $picks = $funds->random(min(3, $funds->count()));

        foreach ($picks as $j => $fund) {
            $amount = [5_000_000, 10_000_000, 25_000_000][$j % 3];
            $nav    = (float) $fund->nav_per_unit ?: 1000;
            $units  = round($amount / $nav, 8);
            $current = round($units * $nav * (1 + (rand(-5, 15) / 100)), 2);

            Portfolio::updateOrCreate(
                ['user_id' => $user->id, 'fund_id' => $fund->id],
                [
                    'total_units'     => $units,
                    'avg_nav'         => $nav,
                    'current_value'   => $current,
                    'unrealized_gain' => round($current - $amount, 2),
                    'total_invested'  => $amount,
                ]
            );

            // Transaksi settled (pembelian)
            Transaction::firstOrCreate(
                ['order_number' => "VS-{$user->id}-{$fund->id}-S"],
                [
                    'user_id'              => $user->id,
                    'fund_id'              => $fund->id,
                    'type'                 => Transaction::TYPE_SUBSCRIPTION,
                    'amount'               => $amount,
                    'units'                => $units,
                    'nav_price'            => $nav,
                    'fee_amount'           => 0,
                    'status'               => Transaction::STATUS_SETTLED,
                    'payment_method'       => ['va','transfer','qris'][$j % 3],
                    'payment_confirmed_at' => Carbon::now()->subDays(15 - $j),
                    'settled_at'           => Carbon::now()->subDays(14 - $j),
                ]
            );
        }

        // Satu transaksi masih "processing" untuk nasabah pertama (isi tab Dalam Proses)
        if ($i === 0) {
            $fund = $funds->first();
            Transaction::firstOrCreate(
                ['order_number' => "VS-{$user->id}-{$fund->id}-P"],
                [
                    'user_id'        => $user->id,
                    'fund_id'        => $fund->id,
                    'type'           => Transaction::TYPE_SUBSCRIPTION,
                    'amount'         => 3_000_000,
                    'nav_price'      => (float) $fund->nav_per_unit,
                    'status'         => Transaction::STATUS_PROCESSING,
                    'payment_method' => 'va',
                    'payment_code'   => '8808' . str_pad((string) $user->id, 10, '0', STR_PAD_LEFT),
                ]
            );
        }
    }
}
