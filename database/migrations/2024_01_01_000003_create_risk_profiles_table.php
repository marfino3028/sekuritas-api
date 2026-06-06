<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel profil risiko investor.
     * Digunakan untuk menentukan produk reksa dana yang sesuai dengan
     * toleransi risiko nasabah (mandatory per regulasi OJK).
     */
    public function up(): void
    {
        Schema::create('risk_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Jawaban kuesioner profil risiko dalam format JSON
            // Contoh: [{"question_id": 1, "answer": "b", "score": 2}, ...]
            $table->json('answers');

            // Hasil perhitungan profil risiko
            $table->enum('result', [
                'conservative',          // Konservatif (skor < 20)
                'moderate_conservative', // Moderat Konservatif (20-39)
                'moderate',              // Moderat (40-59)
                'moderate_aggressive',   // Moderat Agresif (60-79)
                'aggressive',            // Agresif (>= 80)
            ]);

            // Total skor dari kuesioner
            $table->integer('score')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_profiles');
    }
};
