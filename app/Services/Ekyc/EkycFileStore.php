<?php

namespace App\Services\Ekyc;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * EkycFileStore — penyimpanan file KTP/selfie/tanda tangan.
 *
 * Bila config('ekyc.encrypt_files')=true, isi file DIENKRIPSI at-rest
 * (Laravel Crypt / AES-256) sehingga dokumen identitas tidak tersimpan
 * dalam bentuk plaintext di disk/MinIO. Baca kembali via get() untuk dekripsi.
 */
class EkycFileStore
{
    private static function disk(): string
    {
        return config('ekyc.storage_disk', 'public');
    }

    private static function encrypting(): bool
    {
        return (bool) config('ekyc.encrypt_files', false);
    }

    /** Simpan file upload; kembalikan path relatif. */
    public static function put(int $userId, UploadedFile $file, string $kind): string
    {
        $ext  = $file->getClientOriginalExtension() ?: 'bin';
        $path = "ekyc/{$userId}/{$kind}/" . Str::uuid() . '.' . $ext;

        $binary = file_get_contents($file->getRealPath());
        Storage::disk(self::disk())->put(
            $path,
            self::encrypting() ? Crypt::encryptString($binary) : $binary
        );

        return $path;
    }

    /** Simpan konten mentah (mis. PNG tanda tangan). */
    public static function putRaw(string $path, string $binary): void
    {
        Storage::disk(self::disk())->put(
            $path,
            self::encrypting() ? Crypt::encryptString($binary) : $binary
        );
    }

    /** Ambil konten file (didekripsi bila perlu). */
    public static function get(string $path): string
    {
        $disk = self::disk();
        if (! Storage::disk($disk)->exists($path)) {
            return '';
        }
        $raw = Storage::disk($disk)->get($path);

        if (! self::encrypting()) {
            return $raw;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            // File lama (belum terenkripsi) → kembalikan apa adanya
            return $raw;
        }
    }

    public static function size(string $path): int
    {
        return strlen(self::get($path));
    }

    public static function url(?string $path): ?string
    {
        // Catatan: bila terenkripsi, URL langsung tidak bisa dibuka publik —
        // sajikan lewat endpoint terproteksi yang memanggil get(). Untuk demo
        // (encrypt_files=false) URL storage biasa cukup.
        return $path ? Storage::disk(self::disk())->url($path) : null;
    }
}
