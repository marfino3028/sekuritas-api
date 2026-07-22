<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tanda tangan & paraf pada langkah "Persyaratan & Ketentuan" (ala CGS).
     */
    public function up(): void
    {
        Schema::table('kyc', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('bank_book_photo_path');
            $table->string('paraf_path')->nullable()->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('kyc', function (Blueprint $table) {
            $table->dropColumn(['signature_path', 'paraf_path']);
        });
    }
};
