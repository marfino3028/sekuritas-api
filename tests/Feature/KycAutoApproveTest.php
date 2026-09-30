<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\EkycResult;
use App\Models\Kyc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Verifikasi otomatis (eKYC): skor AI lolos → pengajuan KYC langsung disetujui (hasil instan).
 * Skor zona review, atau data identitas diubah setelah dibaca AI → tetap antre review Ops.
 */
class KycAutoApproveTest extends TestCase
{
    use RefreshDatabase;

    private const NIK = '3201234567890001';

    private array $headers;
    private User $user;
    private string $sessionId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ekyc.provider' => 'stub', 'ekyc.storage_disk' => 'public', 'mail.default' => 'array']);
        Storage::fake('public');
        Mail::fake();

        $this->user    = User::factory()->create(['status' => User::STATUS_PENDING]);
        $this->headers = ['Authorization' => 'Bearer ' . JWTAuth::fromUser($this->user)];

        $this->sessionId = $this->withHeaders($this->headers)->postJson('/api/ekyc/session')->json('data.id');
        $this->withHeaders($this->headers)->post('/api/ekyc/ocr', [
            'session_id' => $this->sessionId,
            'file'       => UploadedFile::fake()->createWithContent('ktp.jpg', str_repeat('K', 120 * 1024)),
            'nik'        => self::NIK,
            'birth_date' => '1990-06-12',
        ])->assertOk();
        $this->withHeaders($this->headers)->post('/api/ekyc/liveness', [
            'session_id' => $this->sessionId,
            'file'       => UploadedFile::fake()->createWithContent('selfie.jpg', str_repeat('S', 120 * 1024)),
        ])->assertOk();
        $this->withHeaders($this->headers)->postJson('/api/ekyc/face-match', ['session_id' => $this->sessionId])->assertOk();
    }

    private function verify(): void
    {
        $this->withHeaders($this->headers)->postJson('/api/ekyc/verify', ['session_id' => $this->sessionId])
            ->assertOk()->assertJsonPath('data.result.decision', 'approved');
    }

    private function submit(array $override = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->headers)->postJson('/api/kyc/submit', array_merge([
            'nik' => self::NIK, 'mother_maiden_name' => 'Siti Aminah', 'birth_date' => '1990-06-12', 'gender' => 'M',
            'marital_status' => 'married', 'education' => 's1', 'occupation' => 'karyawan_swasta',
            'income_level' => '10jt_25jt', 'source_of_fund' => 'gaji', 'investment_objective' => 'pertumbuhan_aset',
            'address' => 'Jl. Kebon Sirih No. 10', 'province' => 'DKI Jakarta', 'city' => 'Jakarta Pusat',
        ], $override));
    }

    public function test_ekyc_lolos_langsung_disetujui(): void
    {
        $this->verify();

        $this->submit()->assertCreated()
            ->assertJsonPath('data.auto_approved', true)
            ->assertJsonPath('data.status', Kyc::STATUS_APPROVED);

        $kyc = Kyc::where('user_id', $this->user->id)->first();
        $this->assertNotNull($kyc->reviewed_at);
        $this->assertNull($kyc->reviewed_by);
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo($this->user->email) && str_contains($m->subjectLine, 'Disetujui'));

        // Halaman status nasabah menandai persetujuan otomatis beserta skornya
        $this->withHeaders($this->headers)->getJson('/api/kyc')->assertOk()
            ->assertJsonPath('data.auto_approved', true)
            ->assertJsonPath('data.ekyc.decision', 'approved');
    }

    public function test_nik_diubah_setelah_dibaca_ai_tetap_direview(): void
    {
        $this->verify();

        $this->submit(['nik' => '3201234567890002'])->assertCreated()
            ->assertJsonPath('data.auto_approved', false)
            ->assertJsonPath('data.status', Kyc::STATUS_PENDING);
    }

    public function test_skor_zona_review_tetap_direview(): void
    {
        $this->verify();
        EkycResult::where('session_id', $this->sessionId)->update(['decision' => EkycResult::DECISION_REVIEW]);

        $this->submit()->assertCreated()
            ->assertJsonPath('data.auto_approved', false)
            ->assertJsonPath('data.status', Kyc::STATUS_PENDING);
        Mail::assertSent(NotificationMail::class, fn ($m) => str_contains($m->subjectLine, 'Diterima'));
    }
}
