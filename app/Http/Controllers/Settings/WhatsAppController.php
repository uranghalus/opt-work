<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantWaSession;
use App\Services\WahaClient;
use App\TenantWaSessionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan → Gateway WhatsApp per tenant (ADR-0009 amendmen slice 2).
 *
 * Satu baris `tenant_wa_sessions` per tenant (konvensi nama sesi `tw-{kode}`).
 * Aksi kelola (daftar nomor, mulai sesi, QR, putus) memakai Gate
 * `whatsapp.update`; tampilan memakai `whatsapp.read` — tanpa policy class,
 * mengikuti pola PermissionController (Gate::authorize di controller).
 *
 * QR pairing diambil on-demand (bukan dikirim otomatis) karena sifatnya
 * rahasia: siapa pun yang melihat QR bisa menautkan nomor.
 */
class WhatsAppController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('whatsapp.read');

        return Inertia::render('settings/whatsapp', [
            'configured' => WahaClient::configured(),
            // Scope tenant otomatis (BelongsToTenant) — hanya milik tenant
            // aktif yang pernah terlihat, tanpa perlu filter manual.
            'session' => $this->currentSession(),
        ]);
    }

    /**
     * Daftarkan/ganti nomor: membuat baris `tw-{kode}` bila belum ada,
     * atau memperbarui nomor bila sudah ada. Proses pemindaian QR tetap
     * terpisah (Mulai Sesi → Tampilkan QR).
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('whatsapp.update');

        $validated = $request->validate([
            'phone_number' => [
                'required',
                'string',
                'regex:/^\+?[0-9][0-9\s\-]{6,18}$/',
            ],
        ], [
            'phone_number.required' => 'Nomor WhatsApp tenant wajib diisi.',
            'phone_number.regex' => 'Format nomor tidak valid — hanya angka, spasi, tanda minus/plus (8–20 digit).',
        ]);

        $tenant = Tenant::current();

        if ($tenant === null) {
            abort(404, 'Tidak ada konteks tenant untuk pengaturan ini.');
        }

        $phone = preg_replace('/\D/', '', $validated['phone_number']);

        TenantWaSession::updateOrCreate(
            ['tenant_id' => $tenant->getKey()],
            [
                // Nama sesi berlaku se-instance WAHA; konvensi tetap walau
                // nomor berganti (ganti nomor = putus lalu pasang ulang).
                'session_name' => 'tw-'.$tenant->code,
                'phone_number' => $phone,
            ],
        );

        return back()->with('success', 'Nomor WhatsApp tersimpan. Lanjutkan dengan "Mulai Sesi" lalu pindai QR.');
    }

    /**
     * Nyalakan/buat sesi di WAHA (start → fallback create, pola blueprint
     * opti-work2). Status final diverifikasi oleh cron sinkronisasi.
     */
    public function start(): RedirectResponse
    {
        Gate::authorize('whatsapp.update');

        $session = TenantWaSession::first(); // scope: tenant aktif

        if ($session === null) {
            return back()->withErrors(['session' => 'Daftarkan nomor WhatsApp terlebih dahulu.']);
        }

        if (! WahaClient::configured()) {
            return back()->withErrors(['session' => 'WAHA belum dikonfigurasi (WAHA_URL kosong di .env).']);
        }

        try {
            $started = app(WahaClient::class)->startOrCreateSession($session->session_name);
        } catch (\Throwable $exception) {
            return back()->withErrors(['session' => 'Gateway tidak terjangkau: '.$exception->getMessage()]);
        }

        if (! $started) {
            return back()->withErrors(['session' => 'WAHA menolak permintaan start/create sesi.']);
        }

        // Optimistik: cron app:waha-sync-sessions (tiap menit) membetulkan ke
        // scan_qr / working / error sesuai kenyataan di WAHA.
        $session->forceFill([
            'status' => TenantWaSessionStatus::Starting,
            'last_synced_at' => now(),
        ])->save();

        return back()->with('success', 'Sesi dimulai — buka "Tampilkan QR" lalu pindai dengan WhatsApp di HP tujuan.');
    }

    /**
     * QR pairing on-demand (JSON data URI). Bukan halaman terpisah: QR
     * kadaluarsa cepat dan harus tampil di dalam alur Mulai Sesi → Pindai.
     */
    public function qr()
    {
        Gate::authorize('whatsapp.update');

        $session = TenantWaSession::first();

        if ($session === null) {
            return response()->json(['qr' => null, 'error' => 'Belum ada sesi — daftarkan nomor dulu.'], 404);
        }

        if (! WahaClient::configured()) {
            return response()->json(['qr' => null, 'error' => 'WAHA belum dikonfigurasi.'], 503);
        }

        try {
            $qr = app(WahaClient::class)->qrImage($session->session_name);
        } catch (\Throwable $exception) {
            return response()->json(['qr' => null, 'error' => 'Gateway tidak terjangkau: '.$exception->getMessage()], 502);
        }

        return response()->json(['qr' => $qr]);
    }

    /**
     * Putuskan sesi (stop; fallback logout bila endpoint stop tidak ada).
     * Tidak menghapus baris: nomor bisa dipasang ulang tanpa kehilangan
     * riwayat outbox per tenant.
     */
    public function stop(): RedirectResponse
    {
        Gate::authorize('whatsapp.update');

        $session = TenantWaSession::first();

        if ($session === null) {
            return back()->withErrors(['session' => 'Tidak ada sesi untuk diputus.']);
        }

        if (! WahaClient::configured()) {
            return back()->withErrors(['session' => 'WAHA belum dikonfigurasi (WAHA_URL kosong di .env).']);
        }

        try {
            $stopped = app(WahaClient::class)->stopSession($session->session_name);
        } catch (\Throwable $exception) {
            return back()->withErrors(['session' => 'Gateway tidak terjangkau: '.$exception->getMessage()]);
        }

        if (! $stopped) {
            return back()->withErrors(['session' => 'WAHA menolak permintaan putus sesi.']);
        }

        $session->forceFill([
            'status' => TenantWaSessionStatus::Stopped,
            'last_synced_at' => now(),
        ])->save();

        return back()->with('success', 'Sesi diputus. Pesan tenant berikutnya akan berstatus gagal sampai sesi diaktifkan lagi.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentSession(): ?array
    {
        $session = TenantWaSession::first(); // scope tenant aktif

        if ($session === null) {
            return null;
        }

        return [
            'session_name' => $session->session_name,
            'phone_number' => $session->phone_number,
            'status' => $session->status->value,
            'status_label' => $session->status->label(),
            'last_connected_at' => $session->last_connected_at?->toIso8601String(),
            'last_synced_at' => $session->last_synced_at?->toIso8601String(),
        ];
    }
}
