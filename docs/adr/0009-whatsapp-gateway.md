---
status: draft
---

# WhatsApp gateway via WAHA (outbound, slice 1)

opti-work2 (implementasi referensi) mengirim notifikasi WhatsApp lewat WAHA self-hosted: template hardcode yang dipakai-ulang antar kejadian, credential di tabel `settings`, dan webhook tanpa autentikasi. Grilling 2026-10-21 (G1–G7) memutuskan bentuk opti-works: kirim dulu untuk empat event yang seam-nya sudah ada, tanpa command-bot, tanpa halaman settings.

## Decisions

1. **Provider: WAHA (self-hosted HTTP API), session global.** Endpoint `POST /api/sendText` dengan header `X-Api-Key`. Nama session via `WAHA_SESSION` (di-set ke `optiworks`) = satu nomor pengirim untuk semua cabang di MVP. Sender per-tenant = future work.
2. **Secret di env, bukan di database.** `WAHA_URL`, `WAHA_API_KEY`, `WAHA_SESSION` → `config/waha.php`. Tanpa tabel settings, tanpa halaman UI (konsisten dengan `WEB_PORTAL_TOKEN`). `WAHA_URL` kosong = gateway mati total: channel notifikasi menjadi no-op mutlak — nol baris outbox, nol HTTP.
3. **Slice 1 = tepat empat event** yang penerima dan notifikasinya sudah ada: WO baru → HOD (`work_order.created`), reviewed → pemohon (`work_order.reviewed`), assigned → karyawan pelaksana (`work_order.assigned`), eskalasi H+3/5/6 → penerima eskalasi (`work_order.escalated`). Event lain (extend, verify/close, daily work) menyusul bersama fiturnya — `extend_requests` bahkan belum ada di opti-works.
4. **Resolusi nomor: `employees.number` otoritatif (portal), fallback `users.phone`.** Employee di-fetch `withoutGlobalScope('tenant')` — `employee_id` adalah FK langsung milik user tersebut, dan notifikasi bisa dikirim dari konsol tanpa tenant current (command eskalasi). Null/tanpa digit → baris outbox `skipped` dengan alasan, tanpa retry. Normalisasi `08xx → 628xx@c.us`; chatId mentah (`...@c.us`) dipertahankan.
5. **Outbox tabel `wa_messages`.** Satu baris per penerima per event: `pending → sent | failed` (atau `skipped` sejak awal), `attempts`, `last_error`, `sent_at`. Baris ditulis **di dalam transaksi bisnis yang sama** dengan `notify()`; job di-dispatch `afterCommit`. Tabel sengaja **tidak** memakai `BelongsToTenant` (lihat ADR-0001): dibaca by id oleh job yang bisa berjalan tanpa konteks tenant — `tenant_id` disimpan sebagai data.
6. **Job `SendWaMessageJob`: retry 3x backoff `[30, 120]`, guard status `pending` (idempoten), `failed()` → status `failed`.** Idempotency hulu memakai guard one-shot yang sudah ada (`assigned_at`, `escalation_h*_sent_at`) — tanpa unique constraint. Job **mengimplementasikan `NotTenantAware`**: spatie v4 menandai semua job tenant-aware by default dan menghapus job tenant-aware yang tidak membawa `tenantId` saat diproses — dispatch dari command konsol tanpa tenant current akan membuang pesanan kirim. Diunci oleh test `delivers the outbox job when no tenant is current`.
7. **Command-bot inbound ditunda total.** Route webhook tidak dibuat di slice ini (permukaan serang nol). Blueprint opti-work2 memakai verifikasi telepon berbasis LIKE tanpa HMAC/throttle/binding phone↔akun — hardening jadi prasyarat future work.
8. **Diagnostik: `php artisan app:waha-ping {phone?}`.** Mengecek konfigurasi, status session (`GET /api/sessions/{session}` → WORKING/STOPPED/UNREACHABLE/…), dan mengirim pesan test langsung (tanpa outbox). Secret tidak pernah dicetak.

## Consequences

- Empat kelas notifikasi tetap menyimpan saluran `database` + `broadcast`; WA hanya menambah satu channel pada `via()`. Penerima tanpa nomor tetap menerima notifikasi in-app — baris `skipped` hanya catatan diagnosis.
- Template WA per-event dalam Bahasa Indonesia (`toWhatsApp()` per kelas) — tidak memakai ulang template antar kejadian (bug blueprint) dan tidak memuat instruksi balasan perintah (bot ditunda). Link memakai route `work-orders.show`.
- Teks payload in-app (`toArray`) notifikasi eskalasi masih campuran Tiongkok — pre-existing, di luar scope ADR ini (kandidat perbaikan terpisah).
- **Cacat pre-existing terekam saat implementasi:** `notify()` yang berjalan tanpa tenant current membuat job broadcast (tenant-aware default) dilempar `CurrentTenantCouldNotBeDeterminedInTenantAwareJob` dan di-queue-delete oleh spatie saat diproses — berarti jalur command eskalasi di produksi membuang saluran broadcast hari ini. Di luar scope slice ini; tercatat di `TODO.md` untuk keputusan terpisah.
- Outbox berisi isi pesan + nomor telepon — bacaan UI-nya (mis. untuk audit) adalah keputusan terpisah, belum ada di slice ini.
- Amendmen AGENTS.md: WA keluar dari Non-Goals dan masuk tabel Modul MVP; keputusan settled "notifikasi in-app only" dikecualikan untuk WA outbound.

## References

- Grilling 2026-10-21 — Q1–Q7 dan G1–G7 (semua disetujui user; rekomendasi dijalankan).
- `.scratch/wa-gateway/spec.md` dan `.scratch/wa-gateway/issues.md` — spec dan issue slice ini.
- `D:\Laravel Project\opti-work2` — blueprint: `WahaHelper`, `WahaWhatsAppChannel`, `WhatsAppWebhookController`.
- Implementasi: `app/Channels/WahaWhatsAppChannel.php`, `app/Jobs/SendWaMessageJob.php`, `app/Services/WahaClient.php`, `app/Console/Commands/WahaPing.php`, `config/waha.php`.
- `database/migrations/2026_10_21_000001_create_wa_messages_table.php` — outbox.
- Test: `tests/Feature/WahaGatewayTest.php`, `tests/Unit/WahaClientTest.php`.
- ADR-0001 — tenant scoping; alasan outbox dikecualikan dari pola `BelongsToTenant`.
