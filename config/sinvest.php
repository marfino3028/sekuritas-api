<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver S-INVEST (KSEI)
    |--------------------------------------------------------------------------
    | 'mock' → simulasi lokal (default; generate SID/IFUA palsu untuk demo/UAT)
    | 'ksei' → integrasi S-INVEST asli (butuh kredensial + spesifikasi KSEI)
    */
    'driver' => env('SINVEST_DRIVER', 'mock'),

    'participant_code' => env('SINVEST_PARTICIPANT_CODE', 'VS001'),

    'ksei' => [
        'base_url' => env('SINVEST_BASE_URL', ''),
        'user'     => env('SINVEST_USER', ''),
        'secret'   => env('SINVEST_SECRET', ''),
        'cert_path'=> env('SINVEST_CERT_PATH', ''),
        'timeout'  => (int) env('SINVEST_TIMEOUT', 60),
    ],

    // Bank Administrator RDN (Rekening Dana Nasabah)
    'rdn' => [
        'bank'     => env('RDN_BANK', ''),   // mis. cimb, bca
        'base_url' => env('RDN_BASE_URL', ''),
        'key'      => env('RDN_KEY', ''),
    ],

];
