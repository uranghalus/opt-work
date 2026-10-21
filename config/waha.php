<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WAHA gateway (self-hosted WhatsApp HTTP API)
    |--------------------------------------------------------------------------
    |
    | Slice 2 outbound multi-tenant (ADR-0009 amendmen): instance WAHA satu
    | untuk semua cabang, tetapi SESI/NOMOR per tenant disimpan di tabel
    | `tenant_wa_sessions` — bukan di sini. Karena itu tidak ada lagi kunci
    | session global; pesan tidak akan pernah jatuh ke nomor lain. Secret
    | tetap di env, bukan tabel settings. Kosongkan WAHA_URL untuk mematikan
    | seluruh pengiriman (channel menjadi no-op, tanpa baris outbox).
    |
    */

    'url' => env('WAHA_URL'),

    'api_key' => env('WAHA_API_KEY'),

    // Batas kirim per sesi (per-nomor) di SendWaMessageJob: maksimum N kali
    // per menit + jeda minimal antar pesan ke sesi yang sama. Kena batas =
    // exception → backoff queue yang menunggu, isolasi antar sesi.
    'rate_per_minute' => (int) env('WAHA_RATE_PER_MINUTE', 20),

    'min_interval_seconds' => (int) env('WAHA_MIN_INTERVAL', 2),

    'timeout' => (int) env('WAHA_TIMEOUT', 15),

    'connect_timeout' => (int) env('WAHA_CONNECT_TIMEOUT', 3),
];
