<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah profil risiko produk reksa dana.
     * Skala 1 (Rendah) s/d 5 (Tinggi).
     */
    public function up(): void
    {
        Schema::table('mutual_funds', function (Blueprint $table) {
            $table->unsignedTinyInteger('risk_level')->default(3)->after('fund_type');
        });

        // Backfill produk yang sudah ada (DB produksi yang sudah di-seed)
        // Default berdasarkan jenis reksa dana
        DB::table('mutual_funds')->where('fund_type', 'money_market')->update(['risk_level' => 1]);
        DB::table('mutual_funds')->where('fund_type', 'fixed_income')->update(['risk_level' => 2]);
        DB::table('mutual_funds')->where('fund_type', 'balanced')->update(['risk_level' => 3]);
        DB::table('mutual_funds')->where('fund_type', 'equity')->update(['risk_level' => 5]);
        DB::table('mutual_funds')->where('fund_type', 'sharia')->update(['risk_level' => 3]);

        // Koreksi spesifik untuk produk syariah sesuai underlying asset
        DB::table('mutual_funds')->where('fund_code', 'CIMBNISBT')->update(['risk_level' => 1]); // Islamic Money Market
        DB::table('mutual_funds')->where('fund_code', 'MAIPM')->update(['risk_level' => 5]);     // Saham Syariah
    }

    public function down(): void
    {
        Schema::table('mutual_funds', function (Blueprint $table) {
            $table->dropColumn('risk_level');
        });
    }
};
