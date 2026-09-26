<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\Kyc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Email notifikasi ke nasabah: registrasi langsung aktif, KYC disetujui, dan SID terbit.
 */
class NotificationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_email_is_sent_on_auto_activated_registration(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);

        $this->postJson('/api/auth/register-email', ['email' => 'baru@mail.test', 'password' => 'Rahasia123'])->assertCreated();

        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('baru@mail.test') && str_contains($m->subjectLine, 'Akun Berhasil Dibuat'));
    }

    public function test_ops_approval_and_sid_issuance_notify_the_member(): void
    {
        Mail::fake();

        $ops = User::create(['name' => 'Ops', 'email' => 'ops@mail.test', 'phone' => '081200000001', 'password' => Hash::make('x'),
            'role' => User::ROLE_ADMIN_OPS, 'status' => User::STATUS_ACTIVE]);
        $member = User::create(['name' => 'Rina', 'email' => 'rina@mail.test', 'phone' => '081200000002', 'password' => Hash::make('x'),
            'role' => User::ROLE_USER, 'status' => User::STATUS_PENDING, 'sid_status' => User::SID_NOT_GENERATED]);
        $kyc = Kyc::create(['user_id' => $member->id, 'nik' => '3174015203900009', 'status' => Kyc::STATUS_PENDING]);
        $h = ['Authorization' => 'Bearer ' . JWTAuth::fromUser($ops)];

        $this->putJson("/api/cms/kyc/{$kyc->id}/approve", [], $h)->assertOk();
        $this->postJson("/api/cms/kyc/{$kyc->id}/issue-sid", [], $h)->assertOk();

        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('rina@mail.test') && str_contains($m->subjectLine, 'Disetujui'));
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('rina@mail.test') && str_contains($m->subjectLine, 'Rekening Reksa Dana Anda Aktif'));
    }
}
