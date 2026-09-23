<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah role staf `admin` & `finance` (selain super_admin & admin_ops).
 * Enum Laravel = kolom varchar + CHECK constraint, jadi constraint-nya yang diganti.
 */
return new class extends Migration
{
    private const OLD = ['user', 'admin_ops', 'super_admin'];
    private const NEW = ['user', 'admin_ops', 'super_admin', 'admin', 'finance'];

    public function up(): void
    {
        $this->setRoles(self::NEW);
    }

    public function down(): void
    {
        DB::table('users')->whereIn('role', ['admin', 'finance'])->update(['role' => 'admin_ops']);
        $this->setRoles(self::OLD);
    }

    private function setRoles(array $roles): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('" . implode("','", $roles) . "'))");
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($roles) {
            $table->enum('role', $roles)->default('user')->change();
        });
    }
};
