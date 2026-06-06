<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel transaksi reksa dana (pembelian & penjualan unit).
     * Setiap order subscription/redemption dicatat di sini.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('fund_id')->constrained('mutual_funds')->cascadeOnDelete();

            // Jenis transaksi
            $table->enum('type', ['subscription', 'redemption']);

            // Nilai transaksi dalam Rupiah
            $table->decimal('amount', 20, 2)->default(0.00);

            // Jumlah unit (dihitung setelah pembayaran terkonfirmasi & NAV T+1)
            $table->decimal('units', 20, 8)->nullable();

            // NAV yang digunakan untuk kalkulasi unit (T+1 setelah cut-off 13.00)
            $table->decimal('nav_price', 20, 4)->nullable();

            // Biaya transaksi dalam Rupiah
            $table->decimal('fee_amount', 20, 2)->default(0.00);

            // Status alur transaksi
            $table->enum('status', [
                'pending',     // Menunggu pembayaran
                'paid',        // Sudah bayar, menunggu proses
                'processing',  // Sedang diproses
                'settled',     // Selesai, unit sudah dialokasikan
                'failed',      // Gagal/expired
            ])->default('pending');

            // Data pembayaran
            $table->string('payment_method')->nullable();   // transfer/va/qris
            $table->string('payment_code')->nullable();     // Kode VA atau nomor referensi
            $table->timestamp('payment_expired_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamp('settled_at')->nullable();

            // Catatan tambahan
            $table->text('notes')->nullable();

            // Nomor order unik untuk display ke user
            $table->string('order_number')->unique()->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
