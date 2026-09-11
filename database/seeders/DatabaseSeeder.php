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
     * 2. 5 produk reksa dana Danapathi Asset Management
     * 3. Histori NAV 1 tahun untuk setiap produk
     */
    public function run(): void
    {
        $this->seedAdminUsers();
        $this->seedMutualFunds();
        $this->seedEvents();

        // Data demo tambahan (artikel, nasabah + KYC/eKYC/portofolio/transaksi, leaderboard)
        $this->call([
            ArticleSeeder::class,
            NasabahSeeder::class,
            DemoAccountSeeder::class,   // member aktif + member baru (akun demo tetap)
            EventRegistrationSeeder::class,
        ]);

        $this->command->info('Database seeder selesai!');
        $this->command->info('Login CMS Super Admin: superadmin@danapathi-demo.id / Admin@123456');
        $this->command->info('Login CMS Ops: ops@danapathi-demo.id / Ops@123456');
        $this->command->info('Event demo: DANAPATHI-INVESTDAY | BOOTH-DANAPATHI-JKT | WEBINAR-DANAPATHI-PU | ROADSHOW-DANAPATHI');
    }

    /**
     * Seed akun admin.
     */
    private function seedAdminUsers(): void
    {
        // Super Admin
        User::firstOrCreate(
            ['email' => 'superadmin@danapathi-demo.id'],
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
            ['email' => 'ops@danapathi-demo.id'],
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
        // Produk Danapathi Asset Management — nama & NAB/unit sesuai danapathi.co.id
        // (NAB per 10 Sep 2026). AUM & kinerja = ANGKA DEMO, ganti dgn data resmi klien.
        $products = [
            [
                'fund_code'          => 'DPMMF',
                'name'               => 'Danapathi Money Market Fund',
                'investment_manager' => 'PT Danapathi Asset Management',
                'custodian_bank'     => 'Bank Kustodian',
                'fund_type'          => MutualFund::TYPE_MONEY_MARKET,
                'nav_per_unit'       => 1561.4435,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 0.75,
                'subscription_fee'   => 0.00,
                'redemption_fee'     => 0.00,
                'total_aum'          => 1250000000000,
                'performance_1yr'    => 5.62,
                'performance_3yr'    => 16.9,
                'performance_ytd'    => 3.95,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'Reksa dana pasar uang yang berinvestasi pada instrumen pasar uang dan deposito berjangka waktu kurang dari satu tahun. Cocok untuk dana darurat dan investor konservatif dengan kebutuhan likuiditas tinggi.',
                'base_nav'           => 1475.0,
            ],
            [
                'fund_code'          => 'DPSUKUK1',
                'name'               => 'Danapathi Sukuk Syariah I',
                'investment_manager' => 'PT Danapathi Asset Management',
                'custodian_bank'     => 'Bank Kustodian',
                'fund_type'          => MutualFund::TYPE_SHARIA,
                'nav_per_unit'       => 2065.0728,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 1.25,
                'subscription_fee'   => 0.00,
                'redemption_fee'     => 0.00,
                'total_aum'          => 780000000000,
                'performance_1yr'    => 6.85,
                'performance_3yr'    => 20.4,
                'performance_ytd'    => 4.7,
                'is_syariah'         => true,
                'is_active'          => true,
                'description'        => 'Reksa dana pendapatan tetap syariah yang berfokus pada sukuk negara dan korporasi sesuai prinsip syariah, untuk menghasilkan pendapatan yang relatif stabil.',
                'base_nav'           => 1930.0,
            ],
            [
                'fund_code'          => 'DPFIF',
                'name'               => 'Danapathi Fixed Income Fund',
                'investment_manager' => 'PT Danapathi Asset Management',
                'custodian_bank'     => 'Bank Kustodian',
                'fund_type'          => MutualFund::TYPE_FIXED_INCOME,
                'nav_per_unit'       => 1520.85,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 1.5,
                'subscription_fee'   => 0.00,
                'redemption_fee'     => 0.00,
                'total_aum'          => 960000000000,
                'performance_1yr'    => 7.1,
                'performance_3yr'    => 21.35,
                'performance_ytd'    => 4.85,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'Reksa dana pendapatan tetap dengan portofolio obligasi pemerintah dan korporasi berkualitas untuk menghasilkan pendapatan yang relatif stabil dalam jangka menengah.',
                'base_nav'           => 1418.0,
            ],
            [
                'fund_code'          => 'DPBAL',
                'name'               => 'Danapathi Balance Fund',
                'investment_manager' => 'PT Danapathi Asset Management',
                'custodian_bank'     => 'Bank Kustodian',
                'fund_type'          => MutualFund::TYPE_BALANCED,
                'nav_per_unit'       => 3036.09,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 2.0,
                'subscription_fee'   => 0.00,
                'redemption_fee'     => 0.00,
                'total_aum'          => 540000000000,
                'performance_1yr'    => 8.75,
                'performance_3yr'    => 24.6,
                'performance_ytd'    => 5.3,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'Reksa dana campuran yang menyeimbangkan saham dan efek pendapatan tetap untuk pertumbuhan modal dengan volatilitas yang lebih terjaga.',
                'base_nav'           => 2790.0,
            ],
            [
                'fund_code'          => 'DPEQG',
                'name'               => 'Danapathi Equity Growth',
                'investment_manager' => 'PT Danapathi Asset Management',
                'custodian_bank'     => 'Bank Kustodian',
                'fund_type'          => MutualFund::TYPE_EQUITY,
                'nav_per_unit'       => 2857.25,
                'nav_date'           => Carbon::today()->subDay(),
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 2.5,
                'subscription_fee'   => 0.00,
                'redemption_fee'     => 0.00,
                'total_aum'          => 1120000000000,
                'performance_1yr'    => 11.4,
                'performance_3yr'    => 31.8,
                'performance_ytd'    => 6.9,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'Reksa dana saham yang berinvestasi pada saham-saham berfundamental kuat dengan potensi pertumbuhan jangka panjang. Cocok untuk investor agresif dengan horizon investasi di atas 5 tahun.',
                'base_nav'           => 2560.0,
            ],
        ];

        $riskByType = [
            MutualFund::TYPE_MONEY_MARKET => 1,
            MutualFund::TYPE_FIXED_INCOME => 2,
            MutualFund::TYPE_BALANCED     => 3,
            MutualFund::TYPE_EQUITY       => 5,
            MutualFund::TYPE_SHARIA       => 3,
        ];

        foreach ($products as $productData) {
            $baseNav = $productData['base_nav'];
            unset($productData['base_nav']);

            // Profil risiko: nilai eksplisit bila ada, jika tidak derive dari jenis
            $productData['risk_level'] = $productData['risk_level']
                ?? ($riskByType[$productData['fund_type']] ?? 3);

            $fund = MutualFund::firstOrCreate(
                ['fund_code' => $productData['fund_code']],
                $productData
            );

            // Generate histori NAV 365 hari ke belakang
            $this->generateNavHistory($fund, $baseNav, (float) $productData['nav_per_unit']);
        }

        $this->command->info(count($products) . ' produk reksa dana berhasil di-seed.');
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
                'code'               => 'DANAPATHI-INVESTDAY',
                'name'               => 'Danapathi Investor Day Jakarta',
                'description'        => 'Temui tim manajer investasi Danapathi secara langsung dan pelajari strategi alokasi aset reksa dana. Dapatkan insight pasar dan konsultasi portofolio gratis.',
                'investment_manager' => 'PT Danapathi Asset Management',
                'location'           => 'Ballroom Hotel Mulia, Jakarta Selatan',
                'event_type'         => 'seminar',
                'reward_quota'       => 100,
                'reward_description' => '100 investor tercepat mendapat e-voucher Rp100.000 dan akses eksklusif sesi tanya jawab dengan Chief Investment Officer Danapathi.',
                'max_participants'   => 500,
                'start_at'           => Carbon::now()->addDays(7),
                'end_at'             => Carbon::now()->addDays(30),
                'is_active'          => true,
                'created_by'         => $admin?->id,
            ],
            [
                'code'               => 'BOOTH-DANAPATHI-JKT',
                'name'               => 'Booth Danapathi — Grand Indonesia',
                'description'        => 'Kunjungi booth Danapathi di Grand Indonesia West Mall dan konsultasikan rencana investasi Anda bersama tim kami. Daftar online untuk antrian prioritas!',
                'investment_manager' => 'PT Danapathi Asset Management',
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
                'code'               => 'WEBINAR-DANAPATHI-PU',
                'name'               => 'Webinar: Reksa Dana Pasar Uang untuk Pemula',
                'description'        => 'Ikuti webinar online bersama tim edukasi Danapathi dan pelajari cara memulai investasi di Danapathi Money Market Fund dengan modal minimal. Cocok untuk investor pemula.',
                'investment_manager' => 'PT Danapathi Asset Management',
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
                'code'               => 'ROADSHOW-DANAPATHI',
                'name'               => 'Danapathi Roadshow — Kota-kota Indonesia',
                'description'        => 'Roadshow edukasi investasi reksa dana bersama Danapathi di 5 kota besar. Daftar di kota Anda dan dapatkan analisis portofolio gratis.',
                'investment_manager' => 'PT Danapathi Asset Management',
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
