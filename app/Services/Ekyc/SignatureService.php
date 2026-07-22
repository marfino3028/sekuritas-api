<?php

namespace App\Services\Ekyc;

use App\Services\Ekyc\EkycFileStore;

use App\Models\EkycSession;
use App\Models\EkycSignature;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SignatureService — tanda tangan digital dokumen pembukaan rekening.
 *
 * Driver 'canvas' (default, gratis): menyimpan gambar goresan tanda tangan.
 * Driver 'privy': placeholder integrasi Privy (sandbox) — diaktifkan saat lisensi
 * tersedia. Keduanya menghasilkan record EkycSignature yang seragam.
 */
class SignatureService
{
    /**
     * Simpan tanda tangan dari data URI base64 (canvas web/Flutter).
     *
     * @param string $base64Png data:image/png;base64,.... atau base64 murni
     */
    public function signWithCanvas(EkycSession $session, string $base64Png): EkycSignature
    {
        $binary = $this->decodeDataUri($base64Png);
        $path   = "ekyc/{$session->user_id}/signatures/" . Str::uuid() . '.png';

        EkycFileStore::putRaw($path, $binary);

        return EkycSignature::updateOrCreate(
            ['session_id' => $session->id],
            [
                'provider'  => EkycSignature::PROVIDER_CANVAS,
                'image_path'=> $path,
                'status'    => 'signed',
                'signed_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Placeholder Privy: di production kirim dokumen + minta tanda tangan
     * tersertifikasi, simpan external_ref & dokumen final.
     */
    public function signWithPrivy(EkycSession $session, array $payload): EkycSignature
    {
        // TODO(prod): panggil Privy API (config('ekyc.signature.privy')).
        return EkycSignature::updateOrCreate(
            ['session_id' => $session->id],
            [
                'provider'     => EkycSignature::PROVIDER_PRIVY,
                'external_ref' => 'PRIVY-SANDBOX-' . strtoupper(Str::random(10)),
                'status'       => 'signed',
                'raw_response' => ['sandbox' => true, 'payload' => $payload],
                'signed_at'    => Carbon::now(),
            ]
        );
    }

    private function decodeDataUri(string $data): string
    {
        if (str_contains($data, ',')) {
            $data = explode(',', $data, 2)[1];
        }
        return base64_decode($data) ?: '';
    }
}
