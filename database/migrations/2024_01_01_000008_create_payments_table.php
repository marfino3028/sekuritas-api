<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel data pembayaran.
     * Menyimpan detail konfirmasi pembayaran dari payment gateway.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();

            // Referensi dari payment gateway (contoh: Midtrans, Doku, dll.)
            $table->string('gateway_ref')->nullable()->unique();

            // Metode pembayaran: va_bca, va_bri, va_mandiri, qris, dll.
            $table->string('method');

            // Jumlah pembayaran dalam Rupiah
            $table->decimal('amount', 20, 2);

            // Status pembayaran
            $table->enum('status', [
                'pending',
                'success',
                'failed',
                'expired',
            ])->default('pending');

            // Raw response dari payment gateway (untuk audit/reconciliasi)
            $table->json('gateway_response')->nullable();

            // Waktu pembayaran berhasil dikonfirmasi
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
