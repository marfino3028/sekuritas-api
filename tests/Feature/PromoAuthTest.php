<?php

namespace Tests\Feature;

use App\Mail\RegistrationMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Alur registrasi web (email + aktivasi) dan pendaftaran promo/event via kode referral.
 */
class PromoAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_email_creates_pending_user_and_sends_activation(): void
    {
        Mail::fake();

        $res = $this->postJson('/api/auth/register-email', [
            'email'    => 'calon@mail.test',
            'password' => 'Rahasia123',
        ]);

        $res->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('users', ['email' => 'calon@mail.test', 'status' => User::STATUS_PENDING]);
        Mail::assertSent(RegistrationMail::class);

        // Aktivasi memakai token yang tersimpan
        $token = User::where('email', 'calon@mail.test')->value('activation_token');
        $this->assertNotEmpty($token);

        $this->postJson('/api/auth/activate', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user = User::where('email', 'calon@mail.test')->first();
        $this->assertSame(User::STATUS_ACTIVE, $user->status);
        $this->assertNull($user->activation_token);
    }

    public function test_user_can_register_to_active_event_via_code(): void
    {
        $event = Event::create([
            'code'               => 'RAMADAN25',
            'name'               => 'Promo Ramadan 2025',
            'description'        => 'Cashback untuk pendaftar via link.',
            'investment_manager' => 'Victoria Manajemen Investasi',
            'event_type'         => 'webinar',
            'reward_quota'       => 5,
            'reward_description' => 'Cashback Rp50.000',
            'start_at'           => Carbon::now()->subDay(),
            'end_at'             => Carbon::now()->addDays(7),
            'is_active'          => true,
        ]);

        $user  = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $token = JWTAuth::fromUser($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/events/register', ['code' => 'RAMADAN25'])
            ->assertSuccessful();

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'user_id'  => $user->id,
        ]);

        // Leaderboard publik menampilkan peserta
        $this->getJson('/api/events/RAMADAN25/leaderboard')->assertOk();
    }
}
