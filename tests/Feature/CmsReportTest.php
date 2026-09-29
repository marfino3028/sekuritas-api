<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Laporan CMS: pratinjau JSON dengan filter, unduhan Excel (.xlsx) dan CSV.
 */
class CmsReportTest extends TestCase
{
    use RefreshDatabase;

    private function finance(): array
    {
        $u = User::create(['name' => 'Fin', 'email' => 'fin@mail.test', 'phone' => '081200000099', 'password' => Hash::make('x'),
            'role' => User::ROLE_FINANCE, 'status' => User::STATUS_ACTIVE]);
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($u)];
    }

    public function test_all_reports_preview_and_download(): void
    {
        $h = $this->finance();
        foreach (['transactions', 'kyc', 'aum', 'users', 'nav'] as $r) {
            $this->getJson("/api/cms/reports/{$r}?format=json&date_from=2020-01-01&date_to=2030-12-31", $h)
                ->assertOk()->assertJsonStructure(['headers', 'rows', 'total']);

            $res = $this->get("/api/cms/reports/{$r}?date_from=2020-01-01&date_to=2030-12-31", $h);
            $res->assertOk();
            $this->assertStringContainsString('spreadsheetml', $res->headers->get('Content-Type'));
            $this->assertStringStartsWith('PK', $res->streamedContent()); // file zip = xlsx
        }

        $csv = $this->get('/api/cms/reports/transactions?format=csv&status=paid&type=subscription', $h);
        $csv->assertOk();
        $this->assertStringContainsString('Tanggal;Nasabah;Email', $csv->streamedContent());
    }
}
