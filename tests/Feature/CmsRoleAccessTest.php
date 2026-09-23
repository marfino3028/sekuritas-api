<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Hak akses menu CMS per role staf (super_admin, admin, admin_ops, finance).
 */
class CmsRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): array
    {
        $user = User::create([
            'name'              => "Staf {$role}",
            'email'             => "{$role}@cms.test",
            'phone'             => '0812' . random_int(10000000, 99999999),
            'password'          => Hash::make('Rahasia123'),
            'role'              => $role,
            'status'            => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    public function test_all_staff_roles_can_login_to_cms(): void
    {
        foreach (User::STAFF_ROLES as $role) {
            $this->staff($role);
            $this->postJson('/api/cms/auth/login', ['email' => "{$role}@cms.test", 'password' => 'Rahasia123'])
                ->assertOk()
                ->assertJsonPath('data.user.role', $role);
        }
    }

    public function test_member_cannot_login_to_cms(): void
    {
        $this->staff(User::ROLE_USER);

        $this->postJson('/api/cms/auth/login', ['email' => 'user@cms.test', 'password' => 'Rahasia123'])
            ->assertForbidden();
    }

    public function test_finance_sees_transactions_and_reports_only(): void
    {
        $h = $this->staff(User::ROLE_FINANCE);

        $this->getJson('/api/cms/dashboard', $h)->assertOk();
        $this->getJson('/api/cms/transactions', $h)->assertOk();
        $this->getJson('/api/cms/reports/transactions', $h)->assertOk();
        $this->getJson('/api/cms/kyc', $h)->assertForbidden();
        $this->getJson('/api/cms/users', $h)->assertForbidden();
        $this->getJson('/api/cms/products', $h)->assertForbidden();
        $this->getJson('/api/cms/articles', $h)->assertForbidden();
    }

    public function test_ops_handles_operations_but_not_reports_or_content(): void
    {
        $h = $this->staff(User::ROLE_ADMIN_OPS);

        $this->getJson('/api/cms/kyc', $h)->assertOk();
        $this->getJson('/api/cms/users', $h)->assertOk();
        $this->getJson('/api/cms/products', $h)->assertOk();
        $this->getJson('/api/cms/reports/transactions', $h)->assertForbidden();
        $this->getJson('/api/cms/articles', $h)->assertForbidden();
        $this->getJson('/api/cms/events', $h)->assertForbidden();
    }

    public function test_only_super_admin_can_delete(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);
        $super = $this->staff(User::ROLE_SUPER_ADMIN);

        $this->getJson('/api/cms/articles', $admin)->assertOk();
        $this->getJson('/api/cms/reports/transactions', $admin)->assertOk();
        $this->deleteJson('/api/cms/products/999', [], $admin)->assertForbidden();

        // Lolos cek role → sampai controller (produk tidak ada = 404)
        $this->deleteJson('/api/cms/products/999', [], $super)->assertNotFound();
    }
}
