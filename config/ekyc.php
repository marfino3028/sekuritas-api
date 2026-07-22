<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider eKYC Aktif
    |--------------------------------------------------------------------------
    | 'stub'    → self-hosted nol biaya (default, untuk demo/tahap awal)
    | 'fastapi' → AI service Python (PaddleOCR + InsightFace + liveness)
    | (siap ditambah: advance_ai, sumsub, veriff, big_vision, bos_api)
    */
    'provider' => env('EKYC_PROVIDER', 'stub'),

    // Disk penyimpanan gambar KTP/selfie (sebaiknya s3/minio + terenkripsi di prod)
    'storage_disk' => env('EKYC_STORAGE_DISK', 'public'),

    // Masa berlaku 1 sesi eKYC (menit) sebelum expired
    'session_ttl' => (int) env('EKYC_SESSION_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Ambang Batas Keputusan Otomatis (0-100)
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'ocr'        => (int) env('EKYC_MIN_OCR', 70),
        'liveness'   => (int) env('EKYC_MIN_LIVENESS', 80),
        'face_match' => (int) env('EKYC_MIN_FACE_MATCH', 80),
        // Skor akhir >= auto_approve → langsung approved; di bawah min_reject → rejected;
        // di antaranya → masuk antrian review manual admin.
        'auto_approve' => (int) env('EKYC_AUTO_APPROVE', 85),
        'min_reject'   => (int) env('EKYC_MIN_REJECT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | FastAPI AI Service (repo sekuritas-ai)
    |--------------------------------------------------------------------------
    */
    'fastapi' => [
        'base_url' => env('EKYC_FASTAPI_URL', 'http://ekyc-ai:8000'),
        'api_key'  => env('EKYC_FASTAPI_KEY', ''),
        'timeout'  => (int) env('EKYC_FASTAPI_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tanda Tangan Digital
    |--------------------------------------------------------------------------
    | 'canvas' → gambar tanda tangan lokal (gratis, default)
    | 'privy'  → Privy (tersertifikasi) — aktifkan saat lisensi tersedia
    */
    'signature' => [
        'provider' => env('SIGNATURE_PROVIDER', 'canvas'),
        'privy' => [
            'base_url'      => env('PRIVY_BASE_URL', 'https://api-sandbox.privy.id'),
            'merchant_key'  => env('PRIVY_MERCHANT_KEY', ''),
            'enterprise_token' => env('PRIVY_ENTERPRISE_TOKEN', ''),
        ],
    ],

];
