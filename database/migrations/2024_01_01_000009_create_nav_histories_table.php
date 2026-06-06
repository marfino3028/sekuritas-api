<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel histori NAV (Net Asset Value) produk reksa dana.
     * Digunakan untuk menampilkan grafik performa produk kepada investor.
     */
    public function up(): void
    {
        Schema::create('nav_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('mutual_funds')->cascadeOnDelete();

            // Tanggal NAV (hari bursa, Senin-Jumat)
            $table->date('nav_date');

            // Nilai NAV per unit dalam Rupiah
            $table->decimal('nav_per_unit', 20, 4);

            // Perubahan NAV dari hari sebelumnya
            $table->decimal('nav_change', 10, 4)->default(0.0000);
            $table->decimal('nav_change_pct', 8, 4)->default(0.0000); // dalam %

            // Unique: satu catatan NAV per produk per hari
            $table->unique(['fund_id', 'nav_date']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_histories');
    }
};
