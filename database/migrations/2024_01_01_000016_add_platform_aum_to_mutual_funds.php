<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pisahkan AUM PASAR (total_aum, untuk ditampilkan publik) dari
     * AUM PLATFORM (platform_aum, hasil unit investor di platform ini).
     * Update NAV harian kini menulis ke platform_aum; total_aum tetap nilai pasar.
     */
    public function up(): void
    {
        Schema::table('mutual_funds', function (Blueprint $table) {
            $table->decimal('platform_aum', 25, 2)->default(0)->after('total_aum');
        });

        // Restore total_aum ke nilai AUM PASAR kanonik (jaga-jaga sudah ter-overwrite
        // oleh proses NAV harian sebelumnya di DB produksi).
        $marketAum = [
            'SUCOMM'        => 12500000000000,
            'BDLIKUID'      => 8200000000000,
            'MIPENDTETAP'   => 5600000000000,
            'TRIMASTERBOND' => 3400000000000,
            'SCHRMIX'       => 4100000000000,
            'SCHNASAB'      => 15200000000000,
            'MIDARMASAHAM'  => 6800000000000,
            'MAIPM'         => 3100000000000,
            'CIMBNISBT'     => 2300000000000,
            'MNLBSYAR'      => 5400000000000,
        ];

        foreach ($marketAum as $code => $aum) {
            DB::table('mutual_funds')->where('fund_code', $code)->update(['total_aum' => $aum]);
        }
    }

    public function down(): void
    {
        Schema::table('mutual_funds', function (Blueprint $table) {
            $table->dropColumn('platform_aum');
        });
    }
};
