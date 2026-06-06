<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel utama pengguna platform reksa dana.
     * Mencakup data autentikasi, status akun, dan status SID/IFUA.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 15)->nullable()->unique();
            $table->string('password');
            $table->rememberToken();

            // Role pengguna: user biasa, ops admin, atau super admin
            $table->enum('role', ['user', 'admin_ops', 'super_admin'])->default('user');

            // Status akun
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');

            // Verifikasi email & phone
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();

            // Status SID (Single Investor Identification) dari S-INVEST
            $table->enum('sid_status', ['not_generated', 'processing', 'active'])->default('not_generated');
            $table->string('sid_number')->nullable();   // Contoh: SID12345678
            $table->string('ifua_number')->nullable();  // Investor Fund Unit Account

            // Hasil profil risiko
            $table->enum('risk_profile_result', [
                'conservative',
                'moderate_conservative',
                'moderate',
                'moderate_aggressive',
                'aggressive',
            ])->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
