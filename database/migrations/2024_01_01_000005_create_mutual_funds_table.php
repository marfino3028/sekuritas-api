<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel produk reksa dana.
     * Berisi informasi produk dari berbagai Manajer Investasi (MI)
     * yang terdaftar dan diawasi OJK.
     */
    public function up(): void
    {
        Schema::create('mutual_funds', function (Blueprint $table) {
            $table->id();

            // Kode produk reksa dana (unik, dari OJK/KSEI)
            $table->string('fund_code', 20)->unique();
            $table->string('name');

            // Pihak pengelola
            $table->string('investment_manager');   // Manajer Investasi
            $table->string('custodian_bank');        // Bank Kustodian

            // Jenis reksa dana
            $table->enum('fund_type', [
                'money_market',  // Pasar Uang
                'fixed_income',  // Pendapatan Tetap
                'balanced',      // Campuran
                'equity',        // Saham
                'sharia',        // Syariah
            ]);

            // NAV (Net Asset Value) / Nilai Aktiva Bersih per Unit
            $table->decimal('nav_per_unit', 20, 4)->default(1000.00);
            $table->date('nav_date')->nullable();

            // Ketentuan transaksi (dalam Rupiah)
            $table->decimal('min_subscription', 20, 2)->default(100000.00);    // Min. pembelian
            $table->decimal('min_redemption_unit', 20, 8)->default(1.00000000); // Min. penjualan (unit)

            // Biaya pengelolaan (dalam %)
            $table->decimal('management_fee', 5, 2)->default(0.00);  // Biaya MI per tahun
            $table->decimal('subscription_fee', 5, 2)->default(0.00); // Biaya beli
            $table->decimal('redemption_fee', 5, 2)->default(0.00);   // Biaya jual

            // Data AUM (dalam Rupiah)
            $table->decimal('total_aum', 25, 2)->default(0.00);

            // Performa historis (dalam %)
            $table->decimal('performance_1yr', 8, 2)->nullable();
            $table->decimal('performance_3yr', 8, 2)->nullable();
            $table->decimal('performance_ytd', 8, 2)->nullable();

            // Label syariah
            $table->boolean('is_syariah')->default(false);
            $table->boolean('is_active')->default(true);

            // Deskripsi produk
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutual_funds');
    }
};
