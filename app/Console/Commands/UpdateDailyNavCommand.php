<?php

namespace App\Console\Commands;

use App\Models\AumHistory;
use App\Models\MutualFund;
use App\Models\NavHistory;
use App\Models\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Artisan command untuk update NAV dan recalculate AUM harian.
 *
 * Cara pakai:
 *   # Hitung ulang AUM semua produk berdasarkan NAV saat ini (tanpa ubah NAV)
 *   php artisan nav:update-daily --all
 *
 *   # Update NAV produk tertentu + hitung AUM-nya
 *   php artisan nav:update-daily --fund_id=1 --nav=1234.5678 --date=2026-06-05
 *
 * Jadwal otomatis: setiap hari kerja pukul 16:30 WIB (setelah cut-off pasar)
 */
class UpdateDailyNavCommand extends Command
{
    protected $signature = 'nav:update-daily
                            {--fund_id= : ID produk reksa dana}
                            {--nav=     : NAV per unit baru (Rupiah)}
                            {--date=    : Tanggal NAV, default hari ini (YYYY-MM-DD)}
                            {--all      : Hitung ulang AUM semua produk tanpa mengubah NAV}';

    protected $description = 'Update NAV harian dan recalculate AUM per produk reksa dana';

    public function handle(): int
    {
        $date = $this->option('date') ?? now('Asia/Jakarta')->toDateString();

        if ($this->option('all')) {
            return $this->recalculateAllAum($date);
        }

        $fundId = $this->option('fund_id');
        $nav    = $this->option('nav');

        if (! $fundId || ! $nav) {
            $this->error('Gunakan --all, atau berikan --fund_id dan --nav.');
            $this->line('Contoh: php artisan nav:update-daily --fund_id=1 --nav=1250.00 --date=2026-06-05');
            return self::FAILURE;
        }

        return $this->updateFundNav((int) $fundId, (float) $nav, $date);
    }

    /**
     * Hitung ulang AUM semua produk aktif berdasarkan NAV saat ini.
     * Dipakai saat NAV tidak berubah tapi portofolio investor bergerak
     * (ada settlement transaksi baru).
     */
    private function recalculateAllAum(string $date): int
    {
        $funds = MutualFund::active()->get();

        if ($funds->isEmpty()) {
            $this->warn('Tidak ada produk aktif.');
            return self::SUCCESS;
        }

        $this->info("Menghitung ulang AUM {$funds->count()} produk pada {$date}...");
        $bar = $this->output->createProgressBar($funds->count());
        $bar->start();

        $updatedCount = 0;

        foreach ($funds as $fund) {
            try {
                DB::transaction(function () use ($fund, $date, &$updatedCount) {
                    $nav           = (float) $fund->nav_per_unit;
                    $totalUnits    = (float) Portfolio::where('fund_id', $fund->id)->sum('total_units');
                    $totalAum      = round($totalUnits * $nav, 2);
                    $investorCount = Portfolio::where('fund_id', $fund->id)->where('total_units', '>', 0)->count();

                    $fund->update(['total_aum' => $totalAum]);

                    AumHistory::updateOrCreate(
                        ['fund_id' => $fund->id, 'aum_date' => $date],
                        [
                            'nav_per_unit'  => $nav,
                            'total_units'   => $totalUnits,
                            'total_aum'     => $totalAum,
                            'investor_count'=> $investorCount,
                        ]
                    );

                    // Perbarui nilai pasar semua portofolio investor
                    Portfolio::where('fund_id', $fund->id)
                        ->where('total_units', '>', 0)
                        ->each(fn ($p) => $p->updateMarketValue($nav));

                    $updatedCount++;
                });
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Gagal untuk fund_id={$fund->id} ({$fund->name}): {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Selesai. {$updatedCount}/{$funds->count()} produk berhasil diperbarui.");
        return self::SUCCESS;
    }

    /**
     * Update NAV produk tertentu kemudian recalculate AUM-nya.
     */
    private function updateFundNav(int $fundId, float $newNav, string $date): int
    {
        $fund = MutualFund::find($fundId);

        if (! $fund) {
            $this->error("Produk dengan ID {$fundId} tidak ditemukan.");
            return self::FAILURE;
        }

        $oldNav    = (float) $fund->nav_per_unit;
        $navChange = $newNav - $oldNav;
        $pct       = $oldNav > 0 ? round(($navChange / $oldNav) * 100, 4) : 0;

        $this->info("Produk : {$fund->name}");
        $this->info("NAV    : {$oldNav} → {$newNav} ({$pct}%)");
        $this->info("Tanggal: {$date}");

        try {
            DB::transaction(function () use ($fund, $newNav, $date, $navChange, $pct, &$totalAum, &$totalUnits) {
                // 1. Update NAV
                $fund->update(['nav_per_unit' => $newNav, 'nav_date' => $date]);

                // 2. Catat ke nav_histories
                NavHistory::updateOrCreate(
                    ['fund_id' => $fund->id, 'nav_date' => $date],
                    [
                        'nav_per_unit'   => $newNav,
                        'nav_change'     => round($navChange, 4),
                        'nav_change_pct' => $pct,
                    ]
                );

                // 3. Hitung AUM
                $totalUnits    = (float) Portfolio::where('fund_id', $fund->id)->sum('total_units');
                $totalAum      = round($totalUnits * $newNav, 2);
                $investorCount = Portfolio::where('fund_id', $fund->id)->where('total_units', '>', 0)->count();

                $fund->update(['total_aum' => $totalAum]);

                // 4. Catat ke aum_histories
                AumHistory::updateOrCreate(
                    ['fund_id' => $fund->id, 'aum_date' => $date],
                    [
                        'nav_per_unit'  => $newNav,
                        'total_units'   => $totalUnits,
                        'total_aum'     => $totalAum,
                        'investor_count'=> $investorCount,
                    ]
                );

                // 5. Update nilai pasar semua portofolio investor
                $updatedPortfolios = Portfolio::where('fund_id', $fund->id)
                    ->where('total_units', '>', 0)
                    ->count();

                Portfolio::where('fund_id', $fund->id)
                    ->where('total_units', '>', 0)
                    ->each(fn ($p) => $p->updateMarketValue($newNav));

                $this->line("  ✓ {$investorCount} investor, {$updatedPortfolios} portofolio diperbarui");
            });

            $this->info('AUM    : Rp ' . number_format($totalAum ?? 0, 0, ',', '.'));
            $this->info('Unit   : ' . number_format($totalUnits ?? 0, 4, ',', '.') . ' unit');
            $this->info('Berhasil!');
            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("Gagal: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
