<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token aktivasi untuk registrasi via WEB (User ID + email + password),
     * sesuai flow CGS: daftar → email link aktivasi → aktivasi → login.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('activation_token', 64)->nullable()->unique()->after('email_verified_at');
            $table->timestamp('activated_at')->nullable()->after('activation_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['activation_token', 'activated_at']);
        });
    }
};
