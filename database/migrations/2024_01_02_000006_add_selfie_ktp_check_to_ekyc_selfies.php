<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hasil cek "selfie sambil pegang KTP" (pelengkap opsional /liveness):
     * apakah KTP ikut kefoto, NIK yang kebaca di foto tsb, apakah cocok dengan
     * NIK hasil OCR KTP sebelumnya, dan apakah wajah di foto KTP (dalam foto
     * yang sama) cocok dengan wajah orangnya. Lihat app/services/selfie_ktp.py
     * di repo sekuritas-ai.
     */
    public function up(): void
    {
        Schema::table('ekyc_selfies', function (Blueprint $table) {
            $table->boolean('ktp_detected')->nullable()->after('is_replay');
            $table->string('nik_in_photo', 32)->nullable()->after('ktp_detected');
            $table->boolean('nik_match')->nullable()->after('nik_in_photo');
            $table->boolean('id_face_match')->nullable()->after('nik_match');
            $table->unsignedTinyInteger('id_face_match_score')->nullable()->after('id_face_match'); // 0-100
        });
    }

    public function down(): void
    {
        Schema::table('ekyc_selfies', function (Blueprint $table) {
            $table->dropColumn([
                'ktp_detected', 'nik_in_photo', 'nik_match', 'id_face_match', 'id_face_match_score',
            ]);
        });
    }
};
