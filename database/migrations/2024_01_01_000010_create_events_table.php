<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel event yang diadakan oleh Manajer Investasi (MI).
     * Digunakan untuk booth, seminar, webinar, atau roadshow.
     * User mendaftar via kode event → dapat rank berdasarkan waktu daftar.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            // Kode unik yang dibagikan ke calon investor (case-insensitive)
            // Contoh: SCHRODERS2025, BOOTH-JKT-001, WEBINAR-MI-JUNE
            $table->string('code', 50)->unique();

            // Info event
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('investment_manager');   // Nama MI yang mengadakan
            $table->string('location')->nullable(); // Nama tempat / "Online via Zoom"

            $table->enum('event_type', [
                'booth',    // Pameran / stand di mall, kampus, dll
                'seminar',  // Seminar offline
                'webinar',  // Seminar online
                'roadshow', // Roadshow kota-kota
                'other',
            ])->default('booth');

            // Slot / kuota: berapa investor "tercepat" yang dapat reward
            // NULL = tidak ada batas
            $table->unsignedInteger('reward_quota')->nullable();
            $table->text('reward_description')->nullable(); // "50 pendaftar tercepat dapat cashback Rp50.000"

            // Batas maksimum total pendaftar (NULL = unlimited)
            $table->unsignedInteger('max_participants')->nullable();

            // Counter real-time pendaftar (di-increment atomically)
            $table->unsignedInteger('registered_count')->default(0);

            // Periode event
            $table->dateTime('start_at');
            $table->dateTime('end_at');

            // Registrasi dibuka / ditutup secara manual
            $table->boolean('is_active')->default(true);

            // Banner image (opsional)
            $table->string('banner_path')->nullable();

            // Admin yang membuat event
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
