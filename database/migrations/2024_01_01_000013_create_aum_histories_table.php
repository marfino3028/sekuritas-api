<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel riwayat AUM harian per produk reksa dana.
     * Diisi setiap hari setelah admin menginput NAV baru dari S-INVEST.
     * Data ini digunakan untuk laporan AUM dan grafik pertumbuhan dana.
     */
    public function up(): void
    {
        Schema::create('aum_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('mutual_funds')->cascadeOnDelete();

            // Tanggal pencatatan AUM (hari bursa, T+1 setelah cut-off)
            $table->date('aum_date');

            // NAV per unit pada hari tersebut
            $table->decimal('nav_per_unit', 20, 4);

            // Total unit yang beredar (outstanding units) = sum(portfolio.total_units)
            $table->decimal('total_units', 28, 8)->default(0);

            // Total AUM dalam Rupiah = total_units × nav_per_unit
            $table->decimal('total_aum', 28, 2)->default(0);

            // Jumlah investor aktif yang memegang produk ini
            $table->unsignedInteger('investor_count')->default(0);

            // Unique: satu catatan AUM per produk per hari
            $table->unique(['fund_id', 'aum_date']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aum_histories');
    }
};
