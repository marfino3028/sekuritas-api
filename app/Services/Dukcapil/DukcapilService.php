<?php

namespace App\Services\Dukcapil;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * DukcapilService — verifikasi NIK.
 *
 * Driver 'mock' (default): validasi STRUKTUR NIK (16 digit, kode wilayah, tanggal
 * lahir tertanam) + soft-match nama/tgl lahir. Cocok untuk demo/UAT tanpa akses resmi.
 *
 * Driver 'dukcapil': integrasi API Ditjen Dukcapil (butuh MoU + kredensial resmi).
 * Skeleton disediakan; lengkapi saat kredensial tersedia.
 */
class DukcapilService
{
    /**
     * @return array{verified:bool,source:string,message:string,data:array}
     */
    public function verify(string $nik, ?string $name = null, ?string $birthDate = null): array
    {
        $nik = preg_replace('/\D/', '', $nik);

        if (strlen($nik) !== 16) {
            return $this->result(false, 'mock', 'NIK harus 16 digit.', ['nik' => $nik]);
        }

        return match (config('dukcapil.driver')) {
            'dukcapil'  => $this->viaDukcapil($nik, $name, $birthDate),
            default     => $this->viaNikParser($nik, $name, $birthDate), // 'nikparser'
        };
    }

    /**
     * Parse NIK via nurmanhabib/nik-parser (gratis, demo). Memberi provinsi,
     * kabupaten/kota, kecamatan, jenis kelamin, tanggal lahir, kode pos.
     * Jika package belum terpasang, fallback ke validasi struktur manual.
     */
    private function viaNikParser(string $nik, ?string $name, ?string $birthDate): array
    {
        $parserClass = 'Nurmanhabib\\NikParser\\Nik';
        if (! class_exists($parserClass)) {
            // Package belum di-install → gunakan validasi struktur bawaan.
            return $this->viaMock($nik, $name, $birthDate);
        }

        try {
            /** @var object $parsed */
            $parsed = new $parserClass($nik);
            $valid  = method_exists($parsed, 'isValid') ? (bool) $parsed->isValid() : true;

            $call = fn (string $m) => method_exists($parsed, $m) ? $parsed->{$m}() : null;
            $dobRaw = $call('getTanggalLahir');
            $dob = $dobRaw ? Carbon::parse((string) $dobRaw)->toDateString() : null;

            $flags = [];
            $birthMatch = null;
            if ($birthDate && $dob) {
                $birthMatch = Carbon::parse($birthDate)->toDateString() === $dob;
                if ($birthMatch === false) $flags[] = 'tgl_lahir_tidak_cocok';
            }

            $verified = $valid && empty($flags);

            return $this->result($verified, 'nik-parser', $verified
                ? 'NIK valid (nik-parser, mode demo — bukan verifikasi Dukcapil resmi).'
                : 'NIK tidak valid / data tidak cocok.', [
                    'nik'         => $nik,
                    'provinsi'    => $call('getProvinsi'),
                    'kabupaten'   => $call('getKabupatenKota') ?? $call('getKabupaten'),
                    'kecamatan'   => $call('getKecamatan'),
                    'kelamin'     => $call('getKelamin'),
                    'tgl_lahir'   => $dob,
                    'kode_pos'    => $call('getKodePos'),
                    'birth_match' => $birthMatch,
                    'name_hint'   => $name,
                    'flags'       => $flags,
                ]);
        } catch (\Throwable $e) {
            return $this->viaMock($nik, $name, $birthDate);
        }
    }

    /**
     * Validasi struktur NIK lokal:
     * PPKKSS DDMMYY NNNN — DD > 40 berarti perempuan.
     */
    private function viaMock(string $nik, ?string $name, ?string $birthDate): array
    {
        $dd = (int) substr($nik, 6, 2);
        $mm = (int) substr($nik, 8, 2);
        $yy = (int) substr($nik, 10, 2);

        $gender = $dd > 40 ? 'F' : 'M';
        $day    = $dd > 40 ? $dd - 40 : $dd;

        // Tentukan abad (heuristik sederhana): >tahun sekarang → 1900-an
        $year = 2000 + $yy;
        if ($year > (int) date('Y')) {
            $year = 1900 + $yy;
        }

        $validDate = checkdate($mm, $day, $year);
        $dob = $validDate ? sprintf('%04d-%02d-%02d', $year, $mm, $day) : null;

        $flags = [];
        if (! $validDate) $flags[] = 'tanggal_lahir_tidak_valid';

        // Soft-match tanggal lahir dari OCR bila tersedia
        $birthMatch = null;
        if ($birthDate && $dob) {
            $birthMatch = Carbon::parse($birthDate)->toDateString() === $dob;
            if ($birthMatch === false) $flags[] = 'tgl_lahir_tidak_cocok';
        }

        $verified = $validDate && empty($flags);

        return $this->result($verified, 'mock', $verified
            ? 'Struktur NIK valid (mode demo — bukan pengecekan Dukcapil resmi).'
            : 'NIK tidak lolos validasi struktur.', [
                'nik'             => $nik,
                'derived_gender'  => $gender,
                'derived_dob'     => $dob,
                'birth_match'     => $birthMatch,
                'name_hint'       => $name,
                'flags'           => $flags,
            ]);
    }

    /**
     * KERANGKA integrasi Dukcapil resmi. Lengkapi sesuai spesifikasi Kemendagri
     * (biasanya SOAP/REST dengan user_id, password, ip_user, dan verifikasi NIK+nama).
     */
    private function viaDukcapil(string $nik, ?string $name, ?string $birthDate): array
    {
        $cfg = config('dukcapil.dukcapil');
        if (empty($cfg['base_url']) || empty($cfg['user_id']) || empty($cfg['password'])) {
            throw new RuntimeException('Integrasi Dukcapil belum dikonfigurasi (DUKCAPIL_BASE_URL/USER_ID/PASSWORD).');
        }

        // TODO(prod): panggil endpoint Dukcapil sesuai spesifikasi + parse hasil.
        // $res = Http::timeout($cfg['timeout'])->post(...)->throw()->json();
        // return $this->result($res['status'] === 'OK', 'dukcapil', $res['message'], $res);

        throw new RuntimeException('DukcapilService::viaDukcapil belum diimplementasikan.');
    }

    private function result(bool $verified, string $source, string $message, array $data): array
    {
        return compact('verified', 'source', 'message', 'data');
    }
}
