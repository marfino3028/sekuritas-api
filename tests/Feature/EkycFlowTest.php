<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Happy-path modul eKYC (provider stub): session → ocr → liveness → face-match
 * → signature → verify → status. Memastikan status sesi & keputusan benar.
 */
class EkycFlowTest extends TestCase
{
    use RefreshDatabase;

    private function auth(User $user): array
    {
        $token = JWTAuth::fromUser($user);
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_full_ekyc_happy_path_returns_verified(): void
    {
        config(['ekyc.provider' => 'stub', 'ekyc.storage_disk' => 'public']);
        Storage::fake('public');

        $user    = User::factory()->create(['status' => User::STATUS_PENDING]);
        $headers = $this->auth($user);

        // 1. Buat sesi
        $session = $this->withHeaders($headers)->postJson('/api/ekyc/session');
        $session->assertCreated();
        $sessionId = $session->json('data.id');
        $this->assertNotEmpty($sessionId);

        // 2. OCR KTP (file cukup besar agar lolos ambang kualitas stub)
        $ktp = UploadedFile::fake()->create('ktp.jpg', 120, 'image/jpeg');
        $this->withHeaders($headers)->post('/api/ekyc/ocr', [
            'session_id' => $sessionId,
            'file'       => $ktp,
            'nik'        => '3201234567890001',
            'name'       => 'Budi Santoso',
        ])->assertOk()->assertJsonPath('data.session.status', 'ocr_done');

        // 3. Liveness (selfie)
        $selfie = UploadedFile::fake()->create('selfie.jpg', 120, 'image/jpeg');
        $this->withHeaders($headers)->post('/api/ekyc/liveness', [
            'session_id' => $sessionId,
            'file'       => $selfie,
        ])->assertOk()->assertJsonPath('data.liveness.liveness_passed', true);

        // 4. Face match
        $this->withHeaders($headers)->postJson('/api/ekyc/face-match', [
            'session_id' => $sessionId,
        ])->assertOk()->assertJsonPath('data.face_match.face_matched', true);

        // 5. Tanda tangan (canvas base64)
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC';
        $this->withHeaders($headers)->postJson('/api/ekyc/signature', [
            'session_id' => $sessionId,
            'signature'  => $png,
        ])->assertOk()->assertJsonPath('data.session.status', 'signed');

        // 6. Verifikasi akhir
        $verify = $this->withHeaders($headers)->postJson('/api/ekyc/verify', [
            'session_id' => $sessionId,
        ]);
        $verify->assertOk()
            ->assertJsonPath('data.session.status', 'verified')
            ->assertJsonPath('data.result.decision', 'approved');

        // 7. Status + efek samping: data KYC tersinkron
        $this->withHeaders($headers)->getJson("/api/ekyc/status/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $this->assertDatabaseHas('kyc', ['user_id' => $user->id, 'nik' => '3201234567890001']);
        $this->assertDatabaseHas('ekyc_results', ['session_id' => $sessionId, 'decision' => 'approved']);
    }
}
