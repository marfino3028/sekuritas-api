<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel portofolio investor per produk reksa dana.
     * Menyimpan akumulasi unit dan nilai investasi aktual setiap nasabah.
     */
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('fund_id')->constrained('mutual_funds')->cascadeOnDelete();

            // Akumulasi unit yang dimiliki investor
            $table->decimal('total_units', 20, 8)->default(0.00000000);

            // Rata-rata NAV saat pembelian (untuk hitung unrealized gain/loss)
            $table->decimal('avg_nav', 20, 4)->default(0.0000);

            // Nilai pasar saat ini (total_units * NAV terkini)
            $table->decimal('current_value', 20, 2)->default(0.00);

            // Keuntungan/kerugian belum terealisasi (dalam Rupiah)
            $table->decimal('unrealized_gain', 20, 2)->default(0.00);

            // Total modal yang diinvestasikan
            $table->decimal('total_invested', 20, 2)->default(0.00);

            // Unique: satu investor satu baris per produk
            $table->unique(['user_id', 'fund_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
