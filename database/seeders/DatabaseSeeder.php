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
     * 1. Akun staf CMS (super_admin, admin, admin_ops, finance)
     * 2. 4 produk reksa dana PT LiF Manajemen Investasi
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
        $adminPw = env('DEMO_ADMIN_PASSWORD') ? '(dari DEMO_ADMIN_PASSWORD)' : 'Admin@123456';
        $opsPw   = env('DEMO_OPS_PASSWORD') ? '(dari DEMO_OPS_PASSWORD)' : 'Ops@123456';
        $this->command->info("Login CMS: superadmin@lif-demo.id & admin@lif-demo.id / {$adminPw}");
        $this->command->info("Login CMS: ops@lif-demo.id & finance@lif-demo.id / {$opsPw}");
        $this->command->info('Event demo: LIF-EXTRA-THR | LIF-BIK | LIF-LITERASI-KAMPUS | LIF-BOOKTALK');
    }

    /**
     * Akun staf CMS — satu per role. Password dari env (DEMO_*_PASSWORD) bila diisi.
     *   superadmin@ & admin@  → DEMO_ADMIN_PASSWORD   (default Admin@123456)
     *   ops@ & finance@       → DEMO_OPS_PASSWORD     (default Ops@123456)
     */
    public const STAFF_ACCOUNTS = [
        ['email' => 'superadmin@lif-demo.id', 'name' => 'Super Admin',        'role' => User::ROLE_SUPER_ADMIN, 'phone' => '081234567890', 'pw' => 'admin'],
        ['email' => 'admin@lif-demo.id',      'name' => 'Admin LiF',          'role' => User::ROLE_ADMIN,       'phone' => '081234567892', 'pw' => 'admin'],
        ['email' => 'ops@lif-demo.id',        'name' => 'Admin Operasional',  'role' => User::ROLE_ADMIN_OPS,   'phone' => '081234567891', 'pw' => 'ops'],
        ['email' => 'finance@lif-demo.id',    'name' => 'Staf Finance',       'role' => User::ROLE_FINANCE,     'phone' => '081234567893', 'pw' => 'ops'],
    ];

    private function seedAdminUsers(): void
    {
        $passwords = [
            'admin' => env('DEMO_ADMIN_PASSWORD') ?: 'Admin@123456',
            'ops'   => env('DEMO_OPS_PASSWORD') ?: 'Ops@123456',
        ];

        foreach (self::STAFF_ACCOUNTS as $account) {
            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name'              => $account['name'],
                    'phone'             => $account['phone'],
                    'password'          => Hash::make($passwords[$account['pw']]),
                    'role'              => $account['role'],
                    'status'            => User::STATUS_ACTIVE,
                    'email_verified_at' => Carbon::now(),
                ]
            );
        }

        $this->command->info(count(self::STAFF_ACCOUNTS) . ' akun staf CMS berhasil di-seed.');
    }

    /**
     * Seed 4 reksa dana PT LiF Manajemen Investasi (data asli lif-investasi.co.id).
     */
    private function seedMutualFunds(): void
    {
        // NAB/unit & return (1D, YTD, 1Y, 3Y) sesuai lif-investasi.co.id per 22 Sep 2026.
        // AUM, risiko & kustodian dari Fund Fact Sheet Agustus 2026. Biaya = batas maksimum di prospektus.
        // base_nav = NAB ±1 tahun lalu (diturunkan dari return 1Y) untuk histori grafik.
        $navDate = Carbon::parse('2026-09-22');
        $manager = 'PT LiF Manajemen Investasi';

        $products = [
            [
                'fund_code'          => 'LBP',
                'name'               => 'LiF Bond Plus',
                'investment_manager' => $manager,
                'custodian_bank'     => 'PT Bank Mandiri (Persero) Tbk',
                'fund_type'          => MutualFund::TYPE_FIXED_INCOME,
                'risk_level'         => 2,
                'nav_per_unit'       => 2210.7235,
                'nav_date'           => $navDate,
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 1.25,
                'subscription_fee'   => 2.50,
                'redemption_fee'     => 1.00,
                'total_aum'          => 11629138393.78,
                'performance_1yr'    => 2.36,
                'performance_3yr'    => 13.80,
                'performance_ytd'    => 0.02,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'LiF Bond Plus bertujuan untuk memberikan suatu tingkat pengembalian investasi yang menarik dengan memanfaatkan peluang yang ada di Pasar Obligasi, Pasar Uang dan Pasar Saham dengan tingkat risiko yang minimal serta penekanan pada stabilitas investasi. Kebijakan investasi: 80–98% Efek bersifat utang, 0–18% Efek bersifat ekuitas, 2–20% instrumen pasar uang.',
                'base_nav'           => 2159.7533,
                'daily_change_pct'   => -0.20,
            ],
            [
                'fund_code'          => 'LTFI',
                'name'               => 'LiF Theologia Fixed Income',
                'investment_manager' => $manager,
                'custodian_bank'     => 'PT Bank Negara Indonesia (Persero) Tbk',
                'fund_type'          => MutualFund::TYPE_FIXED_INCOME,
                'risk_level'         => 2,
                'nav_per_unit'       => 1715.1946,
                'nav_date'           => $navDate,
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 1.25,
                'subscription_fee'   => 2.50,
                'redemption_fee'     => 2.50,
                'total_aum'          => 26183268639.37,
                'performance_1yr'    => 2.55,
                'performance_3yr'    => 17.08,
                'performance_ytd'    => 0.95,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'LiF Theologia Fixed Income (d/h Reksa Dana Corpus Theologia Fixed Income Fund) bertujuan untuk memberikan suatu tingkat pengembalian investasi yang menarik dengan memanfaatkan peluang yang ada di Pasar Obligasi dan Pasar Uang, serta memberikan donasi kepada Yayasan Lembaga Perguruan Tinggi Theologi di Indonesia. Kebijakan investasi: 80–100% Efek bersifat utang, 0–20% instrumen pasar uang. Peraih Best Mutual Fund Awards 2025 & 2026.',
                'base_nav'           => 1672.5447,
                'daily_change_pct'   => -0.04,
            ],
            [
                'fund_code'          => 'LMM',
                'name'               => 'LiF Money Market',
                'investment_manager' => $manager,
                'custodian_bank'     => 'PT Bank KEB Hana Indonesia',
                'fund_type'          => MutualFund::TYPE_MONEY_MARKET,
                'risk_level'         => 1,
                'nav_per_unit'       => 1108.0258,
                'nav_date'           => $navDate,
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 0.50,
                'subscription_fee'   => 0.00,
                'redemption_fee'     => 0.00,
                'total_aum'          => 11056557473.57,
                'performance_1yr'    => 5.66,
                'performance_3yr'    => null, // diluncurkan 11 Nov 2024
                'performance_ytd'    => 4.01,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'LiF Money Market bertujuan untuk mempertahankan nilai investasi awal serta memperoleh likuiditas dan tingkat pengembalian yang sesuai dengan tingkat risiko yang dapat diterima, dengan penempatan portofolio investasi 100% pada instrumen pasar uang dalam negeri dan/atau Efek bersifat utang berjangka waktu tidak lebih dari 1 (satu) tahun dan/atau deposito.',
                'base_nav'           => 1048.6710,
                'daily_change_pct'   => 0.01,
            ],
            [
                'fund_code'          => 'LBO',
                'name'               => 'LiF Balanced Optima',
                'investment_manager' => $manager,
                'custodian_bank'     => 'PT Bank KEB Hana Indonesia',
                'fund_type'          => MutualFund::TYPE_BALANCED,
                'risk_level'         => 3,
                'nav_per_unit'       => 1117.5116,
                'nav_date'           => $navDate,
                'min_subscription'   => 100000,
                'min_redemption_unit'=> 1,
                'management_fee'     => 3.00,
                'subscription_fee'   => 2.50,
                'redemption_fee'     => 2.50,
                'total_aum'          => 25670238039.82,
                'performance_1yr'    => 8.93,
                'performance_3yr'    => null, // diluncurkan 11 Nov 2024
                'performance_ytd'    => 6.07,
                'is_syariah'         => false,
                'is_active'          => true,
                'description'        => 'LiF Balanced Optima bertujuan untuk memberikan tingkat pendapatan investasi yang relatif stabil dengan risiko yang minimal dan terukur melalui investasi pada ekuitas, obligasi dan/atau Efek bersifat utang termasuk instrumen pasar uang.',
                'base_nav'           => 1025.8988,
                'daily_change_pct'   => -1.09,
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
            $baseNav   = $productData['base_nav'];
            $lastDaily = $productData['daily_change_pct'] ?? null;
            unset($productData['base_nav'], $productData['daily_change_pct']);

            // Profil risiko: nilai eksplisit bila ada, jika tidak derive dari jenis
            $productData['risk_level'] = $productData['risk_level']
                ?? ($riskByType[$productData['fund_type']] ?? 3);

            $fund = MutualFund::firstOrCreate(
                ['fund_code' => $productData['fund_code']],
                $productData
            );

            // Generate histori NAV 365 hari ke belakang
            $this->generateNavHistory($fund, $baseNav, (float) $productData['nav_per_unit'], $lastDaily);
        }

        $this->command->info(count($products) . ' produk reksa dana berhasil di-seed.');
    }

    /**
     * Generate histori NAV realistis selama 1 tahun.
     * Menggunakan random walk dengan tren naik sesuai fund type.
     *
     * @param MutualFund $fund
     * @param float      $startNav  NAV awal (1 tahun lalu)
     * @param float      $endNav    NAV saat ini
     * @param float|null $lastDaily Perubahan harian terakhir (%) — bila diisi, 2 titik terakhir
     *                              dikunci ke NAB & perubahan resmi agar cocok dengan situs klien.
     */
    private function generateNavHistory(MutualFund $fund, float $startNav, float $endNav, ?float $lastDaily = null): void
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

        // Histori berakhir di tanggal NAB produk (bukan hari ini) agar cocok dengan "As of" situs klien
        $endDate = $fund->nav_date ? Carbon::parse($fund->nav_date) : Carbon::today();

        for ($i = $days; $i >= 0; $i--) {
            $date = $endDate->copy()->subDays($i);

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

        $last = count($navData) - 1;
        if ($lastDaily !== null && $last >= 1) {
            $navData[$last]['nav_per_unit']     = round($endNav, 4);
            $navData[$last - 1]['nav_per_unit'] = round($endNav / (1 + $lastDaily / 100), 4);
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

        // Diadaptasi dari promo & kegiatan asli LiF (Promo THR, Bulan Inklusi Keuangan, literasi keuangan kampus,
        // Book Talk Gramedia). Tanggal dibuat relatif agar selalu aktif saat demo.
        $manager = 'PT LiF Manajemen Investasi';
        $events = [
            [
                'code'               => 'LIF-EXTRA-THR',
                'name'               => 'Promo Extra THR Reksa Dana LiF',
                'description'        => "Hi B'LiFers! Investasikan THR Anda di Reksa Dana LiF. Pembelian pertama minimum Rp5.000.000 berhak mendapatkan \"THR\" berupa Unit Penyertaan Reksa Dana LiF senilai maksimal Rp100.000.",
                'investment_manager' => $manager,
                'location'           => 'Online — Aplikasi & Website LiF',
                'event_type'         => Event::TYPE_OTHER,
                'reward_quota'       => 100,
                'reward_description' => 'Berlaku untuk investor baru, 1x transaksi pembelian per investor (tidak dapat diakumulasikan dan tidak berlaku kelipatan).',
                'max_participants'   => null,
                'start_at'           => Carbon::now()->subDay(), // sudah mulai
                'end_at'             => Carbon::now()->addDays(30),
                'is_active'          => true,
                'created_by'         => $admin?->id,
            ],
            [
                'code'               => 'LIF-BIK',
                'name'               => 'Promo Bulan Inklusi Keuangan — Reksa Dana LiF',
                'description'        => 'Meriahkan Bulan Inklusi Keuangan dengan berinvestasi di Reksa Dana LiF. Setiap pembukaan rekening dan pembelian minimum Rp100.000 untuk investor baru berhak mendapatkan souvenir menarik dari LiF.',
                'investment_manager' => $manager,
                'location'           => 'Online & Kantor LiF, Menara Batavia Lt. 6, Jakarta Pusat',
                'event_type'         => Event::TYPE_OTHER,
                'reward_quota'       => 200,
                'reward_description' => 'Cash back berupa Top Up Unit Penyertaan senilai Rp100.000 untuk investor baru dengan pembelian minimum Rp1.000.000 (tidak berlaku kelipatan).',
                'max_participants'   => null,
                'start_at'           => Carbon::now()->addDays(3),
                'end_at'             => Carbon::now()->addDays(45),
                'is_active'          => true,
                'created_by'         => $admin?->id,
            ],
            [
                'code'               => 'LIF-LITERASI-KAMPUS',
                'name'               => 'Literasi Keuangan: Smart Investment For A Better Life',
                'description'        => 'Seminar literasi keuangan PT LiF Manajemen Investasi bersama mahasiswa: mengenal reksa dana, profil risiko, dan cara berinvestasi yang benar sejak dini.',
                'investment_manager' => $manager,
                'location'           => 'Kampus mitra, Jakarta',
                'event_type'         => Event::TYPE_SEMINAR,
                'reward_quota'       => 100,
                'reward_description' => '100 peserta pertama yang membuka rekening reksa dana LiF mendapat Top Up Unit Penyertaan senilai Rp50.000.',
                'max_participants'   => 300,
                'start_at'           => Carbon::now()->addDays(7),
                'end_at'             => Carbon::now()->addDays(21),
                'is_active'          => true,
                'created_by'         => $admin?->id,
            ],
            [
                'code'               => 'LIF-BOOKTALK',
                'name'               => 'Book Talk & Investasi Bersama LiF',
                'description'        => 'Bincang buku dan investasi bersama tim PT LiF Manajemen Investasi di Gramedia. Konsultasikan rencana keuangan Anda dan pelajari produk Reksa Dana LiF.',
                'investment_manager' => $manager,
                'location'           => 'Gramedia Jalma, Jakarta',
                'event_type'         => Event::TYPE_BOOTH,
                'reward_quota'       => 50,
                'reward_description' => '50 pendaftar pertama yang berinvestasi di Reksa Dana LiF mendapat souvenir eksklusif LiF.',
                'max_participants'   => 150,
                'start_at'           => Carbon::now()->addDays(14),
                'end_at'             => Carbon::now()->addDays(15),
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
