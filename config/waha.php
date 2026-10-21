<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WAHA gateway (self-hosted WhatsApp HTTP API)
    |--------------------------------------------------------------------------
    |
    | Slice 1 outbound gateway (ADR-0009). Secret sengaja di env, bukan di
    | tabel settings seperti blueprint opti-work2 — konsisten dengan
    | WEB_PORTAL_TOKEN. Kosongkan WAHA_URL untuk mematikan seluruh pengiriman
    | (channel menjadi no-op, tanpa baris outbox).
    |
    */

    'url' => env('WAHA_URL'),

    'api_key' => env('WAHA_API_KEY'),

    // Nama session WAHA = nomor pengirim. Satu session global untuk semua
    // cabang di MVP; pemetaan per-tenant adalah future work (G1).
    'session' => env('WAHA_SESSION', 'default'),

    'timeout' => (int) env('WAHA_TIMEOUT', 15),

    'connect_timeout' => (int) env('WAHA_CONNECT_TIMEOUT', 3),
];
