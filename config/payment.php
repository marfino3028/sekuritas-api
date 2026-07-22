<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway aktif
    |--------------------------------------------------------------------------
    | 'mock'     → simulasi lokal (default, demo/UAT internal)
    | 'midtrans' → Midtrans Snap/Core (sandbox atau production)
    | (siap ditambah: 'xendit')
    */
    'gateway' => env('PAYMENT_GATEWAY', 'mock'),

    // Masa berlaku instruksi pembayaran (jam)
    'expiry_hours' => (int) env('PAYMENT_EXPIRY_HOURS', 24),

    'midtrans' => [
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'server_key'    => env('MIDTRANS_SERVER_KEY', ''),
        'client_key'    => env('MIDTRANS_CLIENT_KEY', ''),
        // Base URL Core API — otomatis dipilih dari is_production
        'base_url'      => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com',
    ],

    'xendit' => [
        'secret_key'      => env('XENDIT_SECRET_KEY', ''),
        'callback_token'  => env('XENDIT_CALLBACK_TOKEN', ''),
        'base_url'        => 'https://api.xendit.co',
    ],

];
