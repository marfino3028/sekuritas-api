<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver verifikasi NIK
    |--------------------------------------------------------------------------
    | 'nikparser' → parse & validasi struktur NIK via nurmanhabib/nik-parser
    |               (default; GRATIS, untuk demo — bukan pengecekan data resmi).
    | 'dukcapil'  → API Ditjen Dukcapil Kemendagri (BERBAYAR, butuh MoU + kredensial).
    */
    'driver' => env('DUKCAPIL_DRIVER', 'nikparser'),

    'dukcapil' => [
        'base_url' => env('DUKCAPIL_BASE_URL', ''),
        'user_id'  => env('DUKCAPIL_USER_ID', ''),
        'password' => env('DUKCAPIL_PASSWORD', ''),
        'ip_user'  => env('DUKCAPIL_IP_USER', ''),
        'api_key'  => env('DUKCAPIL_API_KEY', ''),
        'timeout'  => (int) env('DUKCAPIL_TIMEOUT', 30),
    ],

];
