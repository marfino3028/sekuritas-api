<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field tambahan pembukaan rekening ala CGS: data pekerjaan lengkap,
     * informasi tambahan/FATCA, serta foto NPWP & buku tabungan.
     * Disimpan JSON agar fleksibel tanpa puluhan kolom.
     */
    public function up(): void
    {
        Schema::table('kyc', function (Blueprint $table) {
            $table->json('employment')->nullable()->after('investment_objective');       // data pekerjaan lengkap
            $table->json('additional_info')->nullable()->after('employment');             // info tambahan + FATCA
            $table->string('npwp_photo_path')->nullable()->after('selfie_photo_path');
            $table->string('bank_book_photo_path')->nullable()->after('npwp_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('kyc', function (Blueprint $table) {
            $table->dropColumn(['employment', 'additional_info', 'npwp_photo_path', 'bank_book_photo_path']);
        });
    }
};
