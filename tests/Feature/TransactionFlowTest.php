<?php

namespace Tests\Feature;

use App\Models\Kyc;
use App\Models\MutualFund;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Alur beli & jual: nasabah bayar → Ops "Kirim ke S-INVEST" di CMS → unit dialokasikan.
 */
class TransactionFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Guard JWT menyimpan user antar-request dalam satu test — reset lalu login sebagai aktor berikutnya. */
    private function fresh(?User $as = null): static
    {
        // tymon/jwt-auth menyimpan instance guard di singleton — buang agar ikut guard baru
        app('auth')->forgetGuards();
        foreach (['auth.driver', 'tymon.jwt.provider.auth', 'tymon.jwt.auth'] as $abstract) app()->forgetInstance($abstract);
        JWTAuth::clearResolvedInstances();
        if ($as) $this->actingAs($as, 'api');

        return $this;
    }

    private function actors(): array
    {
        $member = User::create(['name' => 'Rina', 'email' => 'rina@mail.test', 'phone' => '081200000011', 'password' => Hash::make('x'),
            'role' => User::ROLE_USER, 'status' => User::STATUS_ACTIVE, 'sid_status' => User::SID_ACTIVE, 'sid_number' => 'IDD1', 'ifua_number' => 'IF1']);
        Kyc::create(['user_id' => $member->id, 'nik' => '3174015203900010', 'status' => Kyc::STATUS_APPROVED]);
        $ops = User::create(['name' => 'Ops', 'email' => 'ops@mail.test', 'phone' => '081200000012', 'password' => Hash::make('x'),
            'role' => User::ROLE_ADMIN_OPS, 'status' => User::STATUS_ACTIVE]);
        $fund = MutualFund::create(['fund_code' => 'TST', 'name' => 'Dana Uji', 'investment_manager' => 'MI Uji', 'custodian_bank' => 'Bank Uji',
            'fund_type' => MutualFund::TYPE_MONEY_MARKET, 'risk_level' => 1, 'nav_per_unit' => 1000, 'nav_date' => now(),
            'min_subscription' => 10000, 'min_redemption_unit' => 1, 'management_fee' => 0.5, 'subscription_fee' => 0,
            'redemption_fee' => 0, 'is_active' => true]);

        return [
            ['Authorization' => 'Bearer ' . JWTAuth::fromUser($member)],
            ['Authorization' => 'Bearer ' . JWTAuth::fromUser($ops)],
            $member, $fund, $ops,
        ];
    }

    public function test_units_are_allocated_only_after_ops_sends_order_to_sinvest(): void
    {
        [$mh, $oh, $member, $fund, $ops] = $this->actors();

        $trxId = $this->fresh($member)->postJson('/api/transactions/subscribe', ['fund_id' => $fund->id, 'amount' => 100000, 'payment_method' => 'va_bca'], $mh)
            ->assertCreated()->json('data.transaction_id');

        // Belum bayar → Ops tidak bisa memproses
        $this->fresh($ops)->postJson("/api/cms/transactions/{$trxId}/process", [], $oh)->assertStatus(400);

        $this->fresh($member)->postJson('/api/payment/confirm', ['transaction_id' => $trxId], $mh)->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertNull(Portfolio::where('user_id', $member->id)->first());

        $this->fresh($ops)->postJson("/api/cms/transactions/{$trxId}/process", [], $oh)->assertOk();
        $this->assertSame(Transaction::STATUS_SETTLED, Transaction::find($trxId)->status);
        $this->assertEqualsWithDelta(100.0, (float) Portfolio::where('user_id', $member->id)->value('total_units'), 0.0001);

        // Jual 40 unit → antre; unit yang antre tidak bisa dijual dua kali
        $sellId = $this->fresh($member)->postJson('/api/transactions/redeem', ['fund_id' => $fund->id, 'units' => 40], $mh)->assertCreated()->json('data.transaction_id');
        $this->fresh($member)->postJson('/api/transactions/redeem', ['fund_id' => $fund->id, 'units' => 70], $mh)->assertStatus(400);

        $this->fresh($ops)->postJson("/api/cms/transactions/{$sellId}/process", [], $oh)->assertOk();
        $this->assertEqualsWithDelta(60.0, (float) Portfolio::where('user_id', $member->id)->value('total_units'), 0.0001);
    }

    public function test_finance_cannot_send_orders_to_sinvest(): void
    {
        $this->actors();
        $finance = User::create(['name' => 'Fin', 'email' => 'fin@mail.test', 'phone' => '081200000013', 'password' => Hash::make('x'),
            'role' => User::ROLE_FINANCE, 'status' => User::STATUS_ACTIVE]);

        $this->fresh()->postJson('/api/cms/transactions/1/process', [], ['Authorization' => 'Bearer ' . JWTAuth::fromUser($finance)])->assertForbidden();
    }
}
