<?php

namespace Database\Seeders;

use App\Models\Kyc;
use App\Models\MutualFund;
use App\Models\RiskProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Akun MEMBER khusus demo (selain 10 nasabah acak dari NasabahSeeder):
 *
 *  - member@<domain>       — nasabah AKTIF: KYC approved, SID/IFUA terbit, punya portofolio
 *                            & riwayat transaksi → langsung demo beli/jual & portofolio.
 *  - member.baru@<domain>  — akun sudah diaktivasi tapi BELUM KYC → login langsung masuk
 *                            alur Pembukaan Rekening (eKYC) dari awal.
 *
 * <domain> mengikuti email Super Admin (sekuritas-demo.id / danapathi-demo.id) agar
 * seeder yang sama berlaku di branch main & danapathi. Password: Member@123 (atau DEMO_MEMBER_PASSWORD)
 */
class DemoAccountSeeder extends NasabahSeeder
{
    public const DEFAULT_PASSWORD = 'Member@123';

    /** Di server publik set DEMO_MEMBER_PASSWORD agar tidak memakai password default yang ada di dokumen. */
    private function password(): string
    {
        return env('DEMO_MEMBER_PASSWORD') ?: self::DEFAULT_PASSWORD;
    }

    public function run(): void
    {
        $funds = MutualFund::where('is_active', true)->get();
        $adminEmail = User::where('role', User::ROLE_SUPER_ADMIN)->orderBy('id')->value('email') ?? 'admin@sekuritas-demo.id';
        $domain = Str::after($adminEmail, '@');

        // --- Member aktif (siap transaksi) ---
        $name   = 'Rina Wulandari';
        $nik    = '3174015203900001';
        $member = User::firstOrCreate(
            ['email' => "member@{$domain}"],
            [
                'name'                => $name,
                'phone'               => '081311110001',
                'password'            => Hash::make($this->password()),
                'role'                => User::ROLE_USER,
                'status'              => User::STATUS_ACTIVE,
                'email_verified_at'   => Carbon::now()->subDays(40),
                'sid_status'          => User::SID_ACTIVE,
                'risk_profile_result' => 'moderate',
            ]
        );

        RiskProfile::firstOrCreate(
            ['user_id' => $member->id],
            ['answers' => [3, 3, 4, 3, 2, 3, 4, 3], 'result' => 'moderate', 'score' => 50]
        );

        Kyc::firstOrCreate(
            ['user_id' => $member->id],
            [
                'nik'                  => $nik,
                'mother_maiden_name'   => 'Sri Handayani',
                'birth_date'           => '1990-03-12',
                'gender'               => 'F',
                'marital_status'       => 'married',
                'education'            => 's1',
                'occupation'           => 'karyawan_swasta',
                'income_level'         => '10jt_25jt',
                'source_of_fund'       => 'gaji',
                'investment_objective' => 'pertumbuhan_aset',
                'address'              => 'Jl. Kebon Jeruk Raya No. 21, Jakarta Barat',
                'province'             => 'DKI Jakarta',
                'city'                 => 'Jakarta Barat',
                'postal_code'          => '11530',
                'status'               => 'approved',
                'submitted_at'         => Carbon::now()->subDays(38),
                'reviewed_at'          => Carbon::now()->subDays(37),
            ]
        );

        $this->seedEkyc($member, $name, $nik, 'F', 'approved');
        $this->seedSid($member, $name);
        if ($funds->isNotEmpty()) {
            $this->seedPortfolioAndTransactions($member, $funds, 0);
        }

        // --- Member baru (untuk demo eKYC dari nol) ---
        User::firstOrCreate(
            ['email' => "member.baru@{$domain}"],
            [
                'name'              => 'Member Baru',
                'phone'             => '081311110002',
                'password'          => Hash::make($this->password()),
                'role'              => User::ROLE_USER,
                'status'            => User::STATUS_PENDING,
                'email_verified_at' => Carbon::now(), // sudah aktivasi email
                'sid_status'        => User::SID_NOT_GENERATED,
            ]
        );

        $pw = env('DEMO_MEMBER_PASSWORD') ? '(dari DEMO_MEMBER_PASSWORD)' : self::DEFAULT_PASSWORD;
        $this->command->info("Akun member demo: member@{$domain} (aktif) & member.baru@{$domain} (belum KYC). Password: {$pw}");
    }
}
