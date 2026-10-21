---
status: draft
---

# WhatsApp gateway via WAHA (outbound, slice 1–2)

opti-work2 (implementasi referensi) mengirim notifikasi WhatsApp lewat WAHA self-hosted: template hardcode yang dipakai-ulang antar kejadian, credential di tabel `settings`, dan webhook tanpa autentikasi. Grilling 2026-10-21 (G1–G7) memutuskan bentuk opti-works: kirim dulu untuk empat event yang seam-nya sudah ada, tanpa command-bot.

**Amendmen slice 2 (grilling lanjutan 2026-10-21, Q1–Q5 disetujui user):** sesi/nomor diganti menjadi **per tenant** pada satu instance WAHA — `WAHA_SESSION` global dihapus dari jalur kirim; ditambah halaman Pengaturan → WhatsApp, sinkron status via polling (tetap tanpa webhook inbound), dan rate limit per sesi. Judul/keputusan di bawah sudah mencerminkan amendmen ini; angka keputusan slice 1 dipertahankan untuk referensi silang.

## Decisions

1. **Provider: WAHA (self-hosted HTTP API) — satu instance, sesi/nomor PER TENANT.** Endpoint `POST /api/sendText` dengan header `X-Api-Key`. Pasangan tenant↔sesi disimpan di tabel `tenant_wa_sessions` (konvensi nama `tw-{kode}`; `tenant_id` unique = maksimal satu sesi per tenant, `session_name` unique global karena berlaku se-instance). **Satu-satunya pintu resolusi** `TenantWaSession::activeFor(tenantId)` (tanpa scope tenant, selalu terikat `tenant_id` eksplisit): pesan tenant A tidak akan pernah keluar lewat nomor tenant B, dan **tidak ada jalur fallback ke nomor global** — kunci `WAHA_SESSION` dihapus dari `config/waha.php` dan `.env.example`.
2. **Secret di env, bukan di database.** `WAHA_URL`, `WAHA_API_KEY`, plus `WAHA_RATE_PER_MINUTE`/`WAHA_MIN_INTERVAL` → `config/waha.php`. `WAHA_URL` kosong = gateway mati total: channel notifikasi menjadi no-op mutlak — nol baris outbox, nol HTTP (juga berlaku untuk halaman settings: tombol dinonaktifkan).
3. **Slice 1 = tepat empat event** yang penerima dan notifikasinya sudah ada: WO baru → HOD (`work_order.created`), reviewed → pemohon (`work_order.reviewed`), assigned → karyawan pelaksana (`work_order.assigned`), eskalasi H+3/5/6 → penerima eskalasi (`work_order.escalated`). Event lain (extend, verify/close, daily work) menyusul bersama fiturnya.
4. **Resolusi nomor: `employees.number` otoritatif (portal), fallback `users.phone`.** Employee di-fetch `withoutGlobalScope('tenant')`. Null/tanpa digit → baris outbox `skipped` dengan alasan, tanpa retry. Normalisasi `08xx → 628xx@c.us`; chatId mentah (`...@c.us`) dipertahankan.
5. **Outbox tabel `wa_messages` (ter-scope tenant sebagai data).** Satu baris per penerima per event: `pending → sent | failed` (atau `skipped` sejak awal), `attempts`, `last_error`, `sent_at`, plus **snapshot `session_name`** yang benar-benar dipakai (audit) + index `(tenant_id, status)` (amendmen slice 2). Baris ditulis **di dalam transaksi bisnis yang sama** dengan `notify()`; job di-dispatch `afterCommit`. Tabel sengaja **tidak** memakai `BelongsToTenant` (ADR-0001): dibaca by id oleh job yang berjalan tanpa konteks tenant.
6. **Job `SendWaMessageJob`: retry 3x backoff `[30, 120]`, guard status `pending` (idempoten), `failed()` → status `failed`, `NotTenantAware` by design.** Amendmen slice 2: (a) sesi di-resolve ULANG saat job dieksekusi — sesi yang diputus antara dispatch → eksekusi menghasilkan `failed` tanpa satu pun HTTP; (b) **rate limit per sesi** via `RateLimiter` (default 20/menit + jeda 2 detik, env) dengan kunci per `session_name` — kena batas = `RuntimeException` yang dicatat ke `last_error` lalu di-rethrow sehingga backoff antrean yang menunggu, **tanpa** menghabiskan kuota attempts HTTP; kunci terpisah per nomor menjamin antrean satu tenant tidak menahan tenant lain.
7. **Command-bot inbound tetap ditunda total (amendmen menegaskan: polling, bukan webhook).** Route webhook **tidak** dibuat — permukaan serang nol. Status sesi diambil dari luar lewat `app:waha-sync-sessions` (cron `everyMinute`, `GET /api/sessions`); instance tak terjangkau (koneksi gagal / stray) → **tidak ada status yang di-flip massal**, percobaan berikutnya jalan di jadwal berikutnya. Tersedia pula fallback tanya-per-baris bila endpoint daftar tidak tersedia, dengan aturan yang sama.
8. **Diagnostik: `php artisan app:waha-ping --tenant=<kode> {phone?}`.** Tanpa `--tenant` = cek instance saja (terjangkau + jumlah sesi, **tanpa** mengirim — nomor global sudah dihapus); dengan `--tenant` = status sesi tenant itu di WAHA + opsional kirim pesan test lewat sesi tersebut. Secret tidak pernah dicetak.
9. **Halaman Pengaturan → WhatsApp (`/settings/whatsapp`) + RBAC `whatsapp.read`/`whatsapp.update`** (amendmen; menyingkirkan keputusan awal "tanpa halaman settings"). Daftar/ganti nomor, Mulai Sesi (start → fallback create), QR pairing **on-demand** (data URI, auto-refresh 5 detik selama menunggu pairing — QR bersifat rahasia), Putuskan Sesi (**stop**; fallback `logout` bila endpoint stop tidak ada = perlu pairing ulang). Aksi kelola memakai `Gate::authorize` di controller (pola proyek, tanpa `app/Policies/`); izin default hanya `admin_tenant` (+ bypass super admin), di-grant lewat `app:grant-default-permissions`. Status di halaman dibaca dari DB via `usePoll(15s)` — bukan langsung dari WAHA.

## Consequences

- Empat kelas notifikasi tetap menyimpan saluran `database` + `broadcast`; WA hanya menambah satu channel pada `via()`. Penerima tanpa nomor tetap menerima notifikasi in-app — baris `skipped` hanya catatan diagnosis.
- Guard channel berurutan: WAHA_URL kosong → nol baris; tanpa nomor → `skipped`; tanpa tenant / **tanpa sesi aktif** → `failed` + alasan status sesi, tanpa dispatch & tanpa HTTP; sesi aktif → `pending` + snapshot `session_name` → dispatch.
- Template WA per-event dalam Bahasa Indonesia (`toWhatsApp()` per kelas); link memakai route `work-orders.show`.
- Tenant wajib **mendaftarkan nomor dulu** sebelum pesannya bisa keluar (tidak ada nomor darurat bersama) — onboarding per cabang = daftar nomor → Mulai Sesi → pindai QR.
- Teks payload in-app (`toArray`) notifikasi eskalasi masih campuran Tiongkok — pre-existing, di luar scope ADR ini.
- **Cacat pre-existing terekam saat implementasi:** `notify()` tanpa tenant current membuat job broadcast (tenant-aware default) di-queue-delete oleh spatie — jalur eskalasi produksi membuang saluran broadcast hari ini. Di luar scope gateway; tercatat di `TODO.md`.
- Outbox berisi isi pesan + nomor telepon — bacaan UI audit adalah keputusan terpisah.
- Amendmen AGENTS.md (lokal, gitignore): WA keluar dari Non-Goals dan masuk tabel Modul MVP; "notifikasi in-app only" dikecualikan untuk WA outbound.

## References

- Grilling 2026-10-21 — Q1–Q7 dan G1–G7 (slice 1); grilling lanjutan 2026-10-21 — rencana slice 2 + Q1–Q5, semua rekomendasi disetujui user.
- `.scratch/wa-gateway/spec.md` dan `.scratch/wa-gateway/issues.md` — spec dan issue slice 1.
- `D:\Laravel Project\opti-work2` — blueprint: `WahaHelper` (endpoint sesi/QR yang terbukti di instance user), `WahaWhatsAppChannel`, `WhatsAppWebhookController`.
- Implementasi slice 1: `app/Channels/WahaWhatsAppChannel.php`, `app/Jobs/SendWaMessageJob.php`, `app/Services/WahaClient.php`, `config/waha.php`.
- Implementasi slice 2: `app/Models/TenantWaSession.php`, `app/TenantWaSessionStatus.php`, `app/Http/Controllers/Settings/WhatsAppController.php`, `app/Console/Commands/WahaSyncSessions.php`, `routes/settings.php`, `resources/js/pages/settings/whatsapp.tsx`.
- Migrasi: `2026_10_21_000001_create_wa_messages_table.php` (outbox), `2026_10_21_000002_create_tenant_wa_sessions_table.php`, `2026_10_21_000003_add_session_name_to_wa_messages_table.php`.
- Test: `tests/Feature/WahaGatewayTest.php`, `tests/Feature/WahaTenantSessionTest.php` (isolasi antar tenant, sync, rate limit, Gate), `tests/Unit/WahaClientTest.php`.
- ADR-0001 — tenant scoping; alasan outbox dikecualikan dari pola `BelongsToTenant`.
