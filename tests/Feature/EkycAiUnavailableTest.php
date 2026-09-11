<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Layanan AI eKYC (FastAPI via tunnel) mati/tidak terjangkau → API membalas 503 dengan
 * pesan jelas, bukan "Server Error" 500.
 */
class EkycAiUnavailableTest extends TestCase
{
    use RefreshDatabase;

    private function ocrRequest(): \Illuminate\Testing\TestResponse
    {
        config(['ekyc.provider' => 'fastapi', 'ekyc.fastapi.base_url' => 'https://ai.example.test', 'ekyc.storage_disk' => 'public']);
        Storage::fake('public');

        $user    = User::factory()->create(['status' => User::STATUS_PENDING]);
        $headers = ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
        $session = $this->withHeaders($headers)->postJson('/api/ekyc/session')->json('data.id');

        return $this->withHeaders($headers)->post('/api/ekyc/ocr', [
            'session_id' => $session,
            'file'       => UploadedFile::fake()->createWithContent('ktp.jpg', str_repeat('K', 60 * 1024)),
        ], ['Accept' => 'application/json']);
    }

    public function test_host_tidak_terjangkau_membalas_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('Could not resolve host: ai.example.test'));

        $this->ocrRequest()->assertStatus(503)->assertJsonPath('success', false);
    }

    public function test_tunnel_mati_530_membalas_503(): void
    {
        Http::fake(['*' => Http::response('origin unreachable', 530)]);

        $this->ocrRequest()->assertStatus(503)->assertJsonPath('success', false);
    }
}
