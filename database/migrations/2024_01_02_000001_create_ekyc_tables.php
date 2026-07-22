<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul eKYC — verifikasi identitas otomatis (OCR KTP, liveness, face match,
     * tanda tangan digital) sebelum data masuk ke tabel `kyc` untuk direview admin.
     *
     * Laravel berperan sebagai API Gateway. Proses AI (OCR/liveness/face-match)
     * dijalankan oleh provider yang dapat diganti via Adapter Pattern
     * (lihat config/ekyc.php + App\Services\Ekyc\*). Tabel di bawah menyimpan
     * jejak setiap langkah untuk audit & anti-fraud.
     */
    public function up(): void
    {
        // --------------------------------------------------------------------
        // ekyc_sessions — 1 sesi verifikasi = 1 percobaan pembukaan rekening
        // --------------------------------------------------------------------
        Schema::create('ekyc_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID: aman dibagikan ke frontend/mobile
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Tahapan sesi mengikuti business flow:
            // created → ocr_done → selfie_done → liveness_passed → face_matched
            // → signed → verified | rejected | expired
            $table->string('status')->default('created')->index();

            // Provider AI yang dipakai (stub|fastapi|advance_ai|sumsub|veriff|...)
            $table->string('provider')->default('stub');

            // Skor akhir agregat (0-100) & keputusan otomatis
            $table->unsignedTinyInteger('score')->nullable();
            $table->boolean('auto_approved')->default(false);

            $table->text('reject_reason')->nullable();
            $table->json('meta')->nullable();     // device, ip, user-agent, dsb
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // --------------------------------------------------------------------
        // ekyc_documents — hasil OCR dokumen (KTP)
        // --------------------------------------------------------------------
        Schema::create('ekyc_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id');
            $table->foreign('session_id')->references('id')->on('ekyc_sessions')->cascadeOnDelete();

            $table->string('type')->default('ktp'); // ktp|npwp|passport
            $table->string('image_path');            // path terenkripsi di storage/MinIO

            // Hasil ekstraksi OCR
            $table->string('nik', 32)->nullable()->index();
            $table->string('name')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();
            $table->text('address')->nullable();
            $table->string('religion')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('occupation')->nullable();

            $table->json('raw_ocr')->nullable();          // response mentah provider
            $table->unsignedTinyInteger('ocr_confidence')->nullable(); // 0-100

            // Deteksi kualitas gambar (anti-fraud)
            $table->boolean('is_blur')->default(false);
            $table->boolean('is_low_light')->default(false);
            $table->boolean('is_screenshot')->default(false); // foto dari layar

            $table->timestamps();
        });

        // --------------------------------------------------------------------
        // ekyc_selfies — foto wajah untuk face match & liveness
        // --------------------------------------------------------------------
        Schema::create('ekyc_selfies', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id');
            $table->foreign('session_id')->references('id')->on('ekyc_sessions')->cascadeOnDelete();

            $table->string('image_path');

            // Liveness (deteksi wajah asli vs foto/replay)
            $table->boolean('liveness_passed')->nullable();
            $table->unsignedTinyInteger('liveness_score')->nullable();  // 0-100
            $table->boolean('is_printed_photo')->default(false);
            $table->boolean('is_replay')->default(false);

            // Face match terhadap foto KTP
            $table->boolean('face_matched')->nullable();
            $table->unsignedTinyInteger('face_match_score')->nullable(); // 0-100

            // Embedding wajah (untuk cek duplikat wajah antar user)
            $table->json('face_embedding')->nullable();

            $table->timestamps();
        });

        // --------------------------------------------------------------------
        // ekyc_signatures — tanda tangan digital (canvas / Privy)
        // --------------------------------------------------------------------
        Schema::create('ekyc_signatures', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id');
            $table->foreign('session_id')->references('id')->on('ekyc_sessions')->cascadeOnDelete();

            $table->string('provider')->default('canvas'); // canvas|privy
            $table->string('image_path')->nullable();       // hasil goresan tanda tangan
            $table->string('document_path')->nullable();     // dokumen final tertandatangani
            $table->string('external_ref')->nullable();      // reference id Privy
            $table->string('status')->default('signed');     // signed|pending|failed
            $table->json('raw_response')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });

        // --------------------------------------------------------------------
        // ekyc_results — ringkasan keputusan akhir per sesi
        // --------------------------------------------------------------------
        Schema::create('ekyc_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id');
            $table->foreign('session_id')->references('id')->on('ekyc_sessions')->cascadeOnDelete();

            $table->unsignedTinyInteger('ocr_score')->nullable();
            $table->unsignedTinyInteger('liveness_score')->nullable();
            $table->unsignedTinyInteger('face_match_score')->nullable();
            $table->unsignedTinyInteger('final_score')->nullable();

            $table->string('decision')->default('review'); // approved|review|rejected
            $table->json('flags')->nullable();              // daftar red-flag fraud
            $table->timestamps();
        });

        // --------------------------------------------------------------------
        // ekyc_logs — audit trail tiap pemanggilan provider (immutable)
        // --------------------------------------------------------------------
        Schema::create('ekyc_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id')->nullable();
            $table->foreign('session_id')->references('id')->on('ekyc_sessions')->nullOnDelete();

            $table->string('step');       // ocr|liveness|face_match|signature|verify
            $table->string('provider');
            $table->string('status');     // success|failed
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('request_meta')->nullable();
            $table->json('response_meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekyc_logs');
        Schema::dropIfExists('ekyc_results');
        Schema::dropIfExists('ekyc_signatures');
        Schema::dropIfExists('ekyc_selfies');
        Schema::dropIfExists('ekyc_documents');
        Schema::dropIfExists('ekyc_sessions');
    }
};
