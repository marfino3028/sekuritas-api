<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pendaftar event.
     * Setiap user yang mendaftar via kode event dicatat dengan timestamp presisi tinggi
     * sehingga bisa ditentukan siapa yang mendaftar paling cepat (leaderboard).
     */
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Urutan pendaftaran dalam event ini (1 = tercepat)
            // Di-assign atomically saat registrasi menggunakan DB transaction + lock
            $table->unsignedInteger('registration_rank');

            // Apakah masuk dalam kuota reward?
            $table->boolean('is_reward_eligible')->default(false);

            // Timestamp presisi microsecond untuk memastikan fairness ranking
            $table->timestamp('registered_at', 6)->useCurrent();

            // Catatan opsional dari user saat registrasi (misal: "Saya tertarik investasi saham")
            $table->text('note')->nullable();

            $table->timestamps();

            // Satu user hanya boleh daftar satu kali per event
            $table->unique(['event_id', 'user_id']);

            $table->index(['event_id', 'registration_rank']);
            $table->index(['event_id', 'is_reward_eligible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
