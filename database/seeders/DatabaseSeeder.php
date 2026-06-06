<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\MutualFund;
use App\Models\NavHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed database demo platform reksa dana.
     *
     * Yang di-seed:
     * 1. Admin users (super_admin & admin_ops)
     * 2. 10 produk reksa dana Indonesia yang realistis
     * 3. Histori NAV 1 tahun untuk setiap produk
     */
    public function run(): void
    {
        $this->seedAdminUsers();
        $this->seedMutualFunds();
        $this->seedEvents();

        $this->command->info('Database seeder selesai!');
        $this->command->info('Login CMS: admin@sekuritas-demo.id / password: Admin@123456');
        $this->command->info('Login Ops: ops@sekuritas-demo.id / password: Ops@123456');
        $this->command->info('Event demo: SCHRODERS-2025 | BOOTH-MANDIRI-JKT | WEBINAR-BAHANA-JUNI | ROADSHOW-SUCORINVEST-2025');
    }

    /**
     * Seed akun admin.
     */
    private function seedAdminUsers(): void
    {
        // Super Admin
        User::firstOrCreate(
            ['email' => 'admin@sekuritas-demo.id'],
            [
                'name'              => 'Super Admin',
                'phone'             => '081234567890',
                'password'          => Hash::make('Admin@123456'),
                'role'              => User::ROLE_SUPER_ADMIN,
                'status'            => User::STATUS_ACTIVE,
                'email_verified_at' => Carbon::now(),
            ]
        );

        // Admin Ops
        User::firstOrCreate(
            ['email' => 'ops@sekuritas-demo.id'],
            [
                'name'              => 'Admin Operasional',
                'phone'             => '081234567891',
                'password'          => Hash::make('Ops@123456'),
                'role'              => User::ROLE_ADMIN_OPS,
                'status'            => User::STATUS_ACTIVE,
                'email_verified_at' => Carbon::now(),
            ]
        );

        $this->command->info('Admin users berhasil di-seed.');
    }

    /**
     * Seed 10 produk reksa dana Indonesia yang realistis.
     * Data berdasarkan produk-produk yang ada di pasar reksa dana Indonesia.
     */
    private function seedMutualFunds(): void
    {
        $products = [
            // ============================================================
            // REKSA DANA PASAR UANG
            // ============================================================
            [
                'fund_code'          => 'SUCOMM',
                'name'               => 'Sucorinvest Money Market Fund',
                'investment_manager' => 'PT Sucorinvest Asset Management',
                'custodian_bank'     => 'Bank CIMB Niaga',
                'fund_type'          => MutualFund::TYPE_MONEY_MARKET,
                'nav_per_unit'       => 1245.67,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 0.75,
                'subscription_fee'   => 0.00,
                'redemption_fee'     => 0.00,
                'total_aum'          => 12500000000000, // Rp 12,5 T
                'performance_1yr'    => 5.82,
                'performance_3yr'    => 16.45,
                'performance_ytd'    => 4.12,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'Reksa Dana Pasar Uang yang berinvestasi pada instrumen pasar uang dan surat berharga dengan jangka pendek. Cocok untuk investor konservatif yang menginginkan imbal hasil lebih tinggi dari deposito dengan likuiditas tinggi.',
                'base_nav'           => 1180.00, // NAV awal 1 tahun lalu (untuk simulasi histori)
            ],
            [
                'fund_code'          => 'BDLIKUID',
                'name'               => 'Bahana Dana Likuid',
                'investment_manager' => 'PT Bahana TCW Investment Management',
                'custodian_bank'     => 'Bank Mandiri',
                'fund_type'          => MutualFund::TYPE_MONEY_MARKET,
                'nav_per_unit'       => 1312.45,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 0.50,
                'subscription_fee'   => 0.00,
                'redemption_fee'     => 0.00,
                'total_aum'          => 8200000000000,
                'performance_1yr'    => 5.45,
                'performance_3yr'    => 15.78,
                'performance_ytd'    => 3.89,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'Bahana Dana Likuid merupakan reksa dana pasar uang yang dikelola secara aktif dengan memanfaatkan instrumen pasar uang berkualitas tinggi. Memberikan imbal hasil yang kompetitif dengan risiko yang sangat terbatas.',
                'base_nav'           => 1245.00,
            ],

            // ============================================================
            // REKSA DANA PENDAPATAN TETAP
            // ============================================================
            [
                'fund_code'          => 'MIPENDTETAP',
                'name'               => 'Manulife Pendapatan Bulanan II',
                'investment_manager' => 'PT Manulife Aset Manajemen Indonesia',
                'custodian_bank'     => 'Bank Deutsche',
                'fund_type'          => MutualFund::TYPE_FIXED_INCOME,
                'nav_per_unit'       => 1567.89,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 1.00,
                'subscription_fee'   => 0.50,
                'redemption_fee'     => 0.50,
                'total_aum'          => 5600000000000,
                'performance_1yr'    => 7.23,
                'performance_3yr'    => 22.15,
                'performance_ytd'    => 5.67,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'Reksa Dana Pendapatan Tetap yang bertujuan memberikan pendapatan tetap melalui investasi pada obligasi pemerintah dan korporasi berkualitas tinggi. Distribusi imbal hasil dilakukan setiap bulan.',
                'base_nav'           => 1460.00,
            ],
            [
                'fund_code'          => 'TRIMASTERBOND',
                'name'               => 'Trim Dana Obligasi Tetap',
                'investment_manager' => 'PT Trimegah Asset Management',
                'custodian_bank'     => 'Bank BNI',
                'fund_type'          => MutualFund::TYPE_FIXED_INCOME,
                'nav_per_unit'       => 2134.56,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 1.25,
                'subscription_fee'   => 0.50,
                'redemption_fee'     => 0.50,
                'total_aum'          => 3400000000000,
                'performance_1yr'    => 8.12,
                'performance_3yr'    => 24.67,
                'performance_ytd'    => 6.34,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'Reksa Dana Pendapatan Tetap dengan portofolio terdiversifikasi pada obligasi pemerintah (SUN, ORI, SR) dan obligasi korporasi investment grade. Cocok untuk investor yang menginginkan pendapatan lebih tinggi dari deposito dalam jangka menengah.',
                'base_nav'           => 1970.00,
            ],

            // ============================================================
            // REKSA DANA CAMPURAN
            // ============================================================
            [
                'fund_code'          => 'SCHRMIX',
                'name'               => 'Schroder Dana Campuran',
                'investment_manager' => 'PT Schroder Investment Management Indonesia',
                'custodian_bank'     => 'Citibank N.A.',
                'fund_type'          => MutualFund::TYPE_BALANCED,
                'nav_per_unit'       => 3456.78,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 1.50,
                'subscription_fee'   => 1.00,
                'redemption_fee'     => 1.00,
                'total_aum'          => 4100000000000,
                'performance_1yr'    => 12.45,
                'performance_3yr'    => 38.56,
                'performance_ytd'    => 9.23,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'Reksa Dana Campuran yang mengalokasikan investasi secara fleksibel antara saham, obligasi, dan instrumen pasar uang. Dikelola secara aktif untuk mengoptimalkan imbal hasil di berbagai kondisi pasar.',
                'base_nav'           => 3080.00,
            ],

            // ============================================================
            // REKSA DANA SAHAM (EQUITY)
            // ============================================================
            [
                'fund_code'          => 'SCHNASAB',
                'name'               => 'Schroder Dana Prestasi Plus',
                'investment_manager' => 'PT Schroder Investment Management Indonesia',
                'custodian_bank'     => 'Citibank N.A.',
                'fund_type'          => MutualFund::TYPE_EQUITY,
                'nav_per_unit'       => 8745.32,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 2.00,
                'subscription_fee'   => 1.00,
                'redemption_fee'     => 1.00,
                'total_aum'          => 15200000000000,
                'performance_1yr'    => 18.67,
                'performance_3yr'    => 62.34,
                'performance_ytd'    => 14.23,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'Reksa Dana Saham yang berinvestasi minimal 80% pada saham-saham unggulan di Bursa Efek Indonesia. Bertujuan memberikan pertumbuhan modal jangka panjang melalui seleksi saham yang ketat dan strategi investasi aktif.',
                'base_nav'           => 7370.00,
            ],
            [
                'fund_code'          => 'MIDARMASAHAM',
                'name'               => 'Manulife Saham Andalan',
                'investment_manager' => 'PT Manulife Aset Manajemen Indonesia',
                'custodian_bank'     => 'Bank Deutsche',
                'fund_type'          => MutualFund::TYPE_EQUITY,
                'nav_per_unit'       => 4523.17,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 2.00,
                'subscription_fee'   => 1.50,
                'redemption_fee'     => 1.50,
                'total_aum'          => 6800000000000,
                'performance_1yr'    => 15.34,
                'performance_3yr'    => 48.92,
                'performance_ytd'    => 11.78,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'Reksa Dana Saham dengan fokus pada perusahaan-perusahaan unggulan Indonesia berkapitalisasi besar (blue chip). Portofolio terdiversifikasi mencakup sektor perbankan, konsumer, infrastruktur, dan teknologi.',
                'base_nav'           => 3920.00,
            ],

            // ============================================================
            // REKSA DANA SYARIAH
            // ============================================================
            [
                'fund_code'          => 'MAIPM',
                'name'               => 'Manulife Syariah Sektoral Amanah',
                'investment_manager' => 'PT Manulife Aset Manajemen Indonesia',
                'custodian_bank'     => 'Bank CIMB Niaga',
                'fund_type'          => MutualFund::TYPE_SHARIA,
                'nav_per_unit'       => 2876.43,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 2.00,
                'subscription_fee'   => 1.00,
                'redemption_fee'     => 1.00,
                'total_aum'          => 3100000000000,
                'performance_1yr'    => 16.23,
                'performance_3yr'    => 52.78,
                'performance_ytd'    => 12.45,
                'is_syariah'         => true,
                'is_active'          => true,
                'description'        => 'Reksa Dana Saham Syariah yang berinvestasi pada saham-saham yang memenuhi kriteria syariah sesuai Daftar Efek Syariah (DES) dari OJK. Dikelola berdasarkan prinsip-prinsip investasi Islam yang bertanggung jawab.',
                'base_nav'           => 2475.00,
            ],
            [
                'fund_code'          => 'CIMBNISBT',
                'name'               => 'CIMB-Principal Islamic Money Market Fund',
                'investment_manager' => 'PT CIMB-Principal Asset Management',
                'custodian_bank'     => 'Bank BCA Syariah',
                'fund_type'          => MutualFund::TYPE_SHARIA,
                'nav_per_unit'       => 1198.56,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 0.60,
                'subscription_fee'   => 0.00,
                'redemption_fee'     => 0.00,
                'total_aum'          => 2300000000000,
                'performance_1yr'    => 5.23,
                'performance_3yr'    => 14.89,
                'performance_ytd'    => 3.67,
                'is_syariah'         => true,
                'is_active'          => true,
                'description'        => 'Reksa Dana Pasar Uang Syariah yang mengelola dana pada instrumen-instrumen pasar uang yang memenuhi prinsip syariah. Memberikan alternatif investasi halal dengan likuiditas tinggi dan risiko rendah.',
                'base_nav'           => 1138.00,
            ],
            [
                'fund_code'          => 'MNLBSYAR',
                'name'               => 'Mandiri Investa Atraktif Syariah',
                'investment_manager' => 'PT Mandiri Manajemen Investasi',
                'custodian_bank'     => 'Bank Mandiri',
                'fund_type'          => MutualFund::TYPE_SHARIA,
                'nav_per_unit'       => 3412.89,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 2.00,
                'subscription_fee'   => 1.00,
                'redemption_fee'     => 1.00,
                'total_aum'          => 5400000000000,
                'performance_1yr'    => 17.89,
                'performance_3yr'    => 58.34,
                'performance_ytd'    => 13.56,
                'is_syariah'         => true,
                'is_active'          => true,
                'description'        => 'Reksa Dana Campuran Syariah dari Mandiri yang mengalokasikan investasi pada saham dan sukuk sesuai prinsip syariah. Cocok untuk investor yang menginginkan pertumbuhan modal dengan standar halal.',
                'base_nav'           => 2895.00,
            ],
        ];

        foreach ($products as $productData) {
            $baseNav = $productData['base_nav'];
            unset($productData['base_nav']);

            $fund = MutualFund::firstOrCreate(
                ['fund_code' => $productData['fund_code']],
                $productData
            );

            // Generate histori NAV 365 hari ke belakang
            $this->generateNavHistory($fund, $baseNav, (float) $productData['nav_per_unit']);
        }

        $this->command->info('10 produk reksa dana berhasil di-seed.');
    }

    /**
     * Generate histori NAV realistis selama 1 tahun.
     * Menggunakan random walk dengan tren naik sesuai fund type.
     *
     * @param MutualFund $fund
     * @param float      $startNav NAV awal (1 tahun lalu)
     * @param float      $endNav   NAV saat ini
     */
    private function generateNavHistory(MutualFund $fund, float $startNav, float $endNav): void
    {
        // Hitung volatilitas berdasarkan jenis reksa dana
        $volatilityMap = [
            MutualFund::TYPE_MONEY_MARKET => 0.0002, // Sangat stabil
            MutualFund::TYPE_FIXED_INCOME => 0.0015, // Stabil
            MutualFund::TYPE_BALANCED     => 0.0040, // Sedang
            MutualFund::TYPE_EQUITY       => 0.0080, // Tinggi
            MutualFund::TYPE_SHARIA       => 0.0060, // Menengah-tinggi
        ];

        $volatility = $volatilityMap[$fund->fund_type] ?? 0.003;
        $days       = 365;
        $navData    = [];
        $currentNav = $startNav;

        // Hitung daily drift agar mencapai endNav
        $totalReturn  = ($endNav - $startNav) / $startNav;
        $dailyDrift   = $totalReturn / $days;

        for ($i = $days; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);

            // Skip weekend (pasar tutup Sabtu & Minggu)
            if ($date->isWeekend()) continue;

            // Random walk dengan drift
            $randomChange = (mt_rand(-100, 100) / 100) * $volatility;
            $change       = $dailyDrift + $randomChange;
            $currentNav   = round($currentNav * (1 + $change), 4);

            // Pastikan NAV tidak negatif
            $currentNav = max($currentNav, 100.0);

            $navData[] = [
                'fund_id'      => $fund->id,
                'nav_date'     => $date->toDateString(),
                'nav_per_unit' => $currentNav,
            ];
        }

        // Hitung nav_change dan nav_change_pct
        $processedData = [];
        $prevNav       = $startNav;

        foreach ($navData as $entry) {
            $change    = $entry['nav_per_unit'] - $prevNav;
            $changePct = $prevNav > 0 ? round(($change / $prevNav) * 100, 4) : 0;

            $processedData[] = [
                'fund_id'        => $entry['fund_id'],
                'nav_date'       => $entry['nav_date'],
                'nav_per_unit'   => $entry['nav_per_unit'],
                'nav_change'     => round($change, 4),
                'nav_change_pct' => $changePct,
                'created_at'     => Carbon::now(),
                'updated_at'     => Carbon::now(),
            ];

            $prevNav = $entry['nav_per_unit'];
        }

        // Insert batch (upsert by fund_id + nav_date)
        foreach (array_chunk($processedData, 50) as $chunk) {
            NavHistory::upsert(
                $chunk,
                ['fund_id', 'nav_date'],
                ['nav_per_unit', 'nav_change', 'nav_change_pct', 'updated_at']
            );
        }
    }

    private function seedEvents(): void
    {
        $admin = User::where('role', 'super_admin')->first();

        $events = [
            [
                'code'               => 'SCHRODERS-2025',
                'name'               => 'Schroder Investment Day Jakarta 2025',
                'description'        => 'Temui tim analis Schroder secara langsung dan pelajari strategi investasi saham terbaik untuk 2025. Dapatkan insight eksklusif dan konsultasi portofolio gratis.',
                'investment_manager' => 'PT Schroder Investment Management Indonesia',
                'location'           => 'Ballroom Hotel Mulia, Jakarta Selatan',
                'event_type'         => 'seminar',
                'reward_quota'       => 100,
                'reward_description' => '100 investor tercepat mendapat e-voucher Rp100.000 dan akses eksklusif sesi Q&A dengan Chief Investment Officer Schroder.',
                'max_participants'   => 500,
                'start_at'           => Carbon::now()->addDays(7),
                'end_at'             => Carbon::now()->addDays(30),
                'is_active'          => true,
                'created_by'         => $admin?->id,
            ],
            [
                'code'               => 'BOOTH-MANDIRI-JKT',
                'name'               => 'Mandiri Investasi — Booth Grand Indonesia',
                'description'        => 'Kunjungi booth Mandiri Investasi di Grand Indonesia West Mall dan konsultasikan rencana investasi Anda bersama relationship manager kami. Daftar online untuk antrian prioritas!',
                'investment_manager' => 'PT Mandiri Manajemen Investasi',
                'location'           => 'Grand Indonesia West Mall, Lantai 3, Jakarta Pusat',
                'event_type'         => 'booth',
                'reward_quota'       => 50,
                'reward_description' => '50 pendaftar tercepat mendapat gratis biaya pembelian reksa dana untuk transaksi pertama (max Rp500.000).',
                'max_participants'   => 200,
                'start_at'           => Carbon::now()->addDays(3),
                'end_at'             => Carbon::now()->addDays(10),
                'is_active'          => true,
                'created_by'         => $admin?->id,
            ],
            [
                'code'               => 'WEBINAR-BAHANA-JUNI',
                'name'               => 'Webinar: Reksa Dana Pasar Uang untuk Pemula',
                'description'        => 'Ikuti webinar online bersama tim edukasi Bahana TCW dan pelajari cara memulai investasi reksa dana pasar uang dengan modal minimal. Cocok untuk investor pemula.',
                'investment_manager' => 'PT Bahana TCW Investment Management',
                'location'           => 'Online via Zoom',
                'event_type'         => 'webinar',
                'reward_quota'       => 200,
                'reward_description' => '200 pendaftar pertama mendapat akses rekaman webinar eksklusif + e-book "Panduan Investasi Reksa Dana" senilai Rp150.000.',
                'max_participants'   => 1000,
                'start_at'           => Carbon::now()->subDay(), // sudah mulai
                'end_at'             => Carbon::now()->addDays(14),
                'is_active'          => true,
                'created_by'         => $admin?->id,
            ],
            [
                'code'               => 'ROADSHOW-SUCORINVEST-2025',
                'name'               => 'Sucorinvest Roadshow — Kota-kota Indonesia',
                'description'        => 'Roadshow edukasi investasi reksa dana bersama Sucorinvest di 5 kota besar. Daftar di kota Anda dan dapatkan analisis portofolio gratis.',
                'investment_manager' => 'PT Sucorinvest Asset Management',
                'location'           => 'Jakarta, Surabaya, Bandung, Medan, Makassar',
                'event_type'         => 'roadshow',
                'reward_quota'       => 500,
                'reward_description' => '500 investor tercepat yang hadir mendapat cashback Rp50.000 untuk pembelian reksa dana perdana.',
                'max_participants'   => null, // unlimited
                'start_at'           => Carbon::now()->addDays(14),
                'end_at'             => Carbon::now()->addDays(60),
                'is_active'          => true,
                'created_by'         => $admin?->id,
            ],
        ];

        foreach ($events as $eventData) {
            Event::updateOrCreate(['code' => $eventData['code']], $eventData);
        }

        $this->command->info('✓ Seeded 4 demo events.');
    }
}
