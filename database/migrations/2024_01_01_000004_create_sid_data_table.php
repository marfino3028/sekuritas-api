<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel data SID (Single Investor Identification).
     * Menyimpan hasil generasi SID & IFUA dari sistem S-INVEST KSEI.
     * SID adalah identitas unik investor di pasar modal Indonesia.
     */
    public function up(): void
    {
        Schema::create('sid_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Nomor SID unik dari KSEI (format: SIDXXXXXXXX)
            $table->string('sid_number')->unique();

            // Nomor rekening dana investor (format: IFUAXXXXXXXX)
            $table->string('ifua_number')->unique();

            // Raw response dari S-INVEST API (untuk audit/debugging)
            $table->json('s_invest_response')->nullable();

            // Waktu SID berhasil di-generate
            $table->timestamp('generated_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sid_data');
    }
};
