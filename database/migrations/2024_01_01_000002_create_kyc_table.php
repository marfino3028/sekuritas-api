<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel KYC (Know Your Customer) — data identitas dan kelayakan investor.
     * Sesuai regulasi OJK untuk platform investasi reksa dana.
     */
    public function up(): void
    {
        Schema::create('kyc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Data identitas (KTP)
            $table->string('nik', 16)->unique();       // Nomor Induk Kependudukan
            $table->string('mother_maiden_name');       // Nama ibu kandung (verifikasi identitas)
            $table->date('birth_date');
            $table->enum('gender', ['M', 'F']);
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed']);

            // Data latar belakang
            $table->enum('education', [
                'sd', 'smp', 'sma', 'diploma', 's1', 's2', 's3', 'other',
            ]);
            $table->enum('occupation', [
                'pns', 'tni_polri', 'karyawan_swasta', 'wiraswasta',
                'profesional', 'ibu_rumah_tangga', 'pelajar', 'pensiunan', 'other',
            ]);

            // Data keuangan — wajib untuk profil investor OJK
            $table->enum('income_level', [
                'below_5jt', '5jt_10jt', '10jt_25jt', '25jt_50jt', 'above_50jt',
            ]);
            $table->enum('source_of_fund', [
                'gaji', 'usaha', 'investasi', 'warisan', 'hadiah', 'other',
            ]);
            $table->enum('investment_objective', [
                'pendidikan', 'pensiun', 'dana_darurat', 'pertumbuhan_aset',
                'pendapatan_rutin', 'other',
            ]);

            // Alamat domisili
            $table->text('address');
            $table->string('province');
            $table->string('city');
            $table->string('postal_code', 10)->nullable();

            // Upload dokumen — path relatif ke storage
            $table->string('ktp_photo_path')->nullable();
            $table->string('selfie_photo_path')->nullable();

            // Status pengajuan KYC
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejected_reason')->nullable();

            // Audit review oleh admin
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc');
    }
};
