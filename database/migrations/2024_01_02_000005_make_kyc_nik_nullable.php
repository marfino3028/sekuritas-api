<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Longgarkan NOT NULL pada kolom kyc agar baris bisa dibuat bertahap:
     * - saat verifikasi eKYC (hanya path KTP/selfie),
     * - saat upload NPWP/buku/tanda tangan (firstOrCreate),
     * lalu dilengkapi (NIK, data pribadi/pekerjaan) saat submit data.
     * Kelengkapan tetap divalidasi di endpoint submit. Enum diubah jadi string
     * nullable (validasi enum tetap di controller).
     */
    public function up(): void
    {
        Schema::table('kyc', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->change();
            $table->string('mother_maiden_name')->nullable()->change();
            $table->date('birth_date')->nullable()->change();
            $table->string('gender')->nullable()->change();
            $table->string('marital_status')->nullable()->change();
            $table->string('education')->nullable()->change();
            $table->string('occupation')->nullable()->change();
            $table->string('income_level')->nullable()->change();
            $table->string('source_of_fund')->nullable()->change();
            $table->string('investment_objective')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('province')->nullable()->change();
            $table->string('city')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Dibiarkan nullable (tidak dikembalikan otomatis).
    }
};
