# Spec — WhatsApp Gateway (slice 1: outbound)

Sumber keputusan: grill 2026-10-21 (Q1–Q7 + G1–G7, semua disetujui user).
Blueprint: `D:\Laravel Project\opti-work2` (WAHA) — dipetakan di sesi ini; tidak
di-copy mentah-mentah, hanya pola yang disetujui.

## Keputusan (settled, jangan di-re-litigate)

| # | Keputusan |
|---|---|
| G1 | Provider **WAHA** (self-hosted, HTTP API, `X-Api-Key`, `POST /api/sendText`). Session **global** (satu nomor), nama session via `WAHA_SESSION` — opti-works dapat session sendiri (mis. `optiworks`), bukan berbagi dengan opti-work2. Per-tenant = future work. |
| G2 | Secret di **env** (`WAHA_URL`, `WAHA_API_KEY`, `WAHA_SESSION`) → `config/waha.php`. Tanpa tabel settings, tanpa halaman UI. Diagnostic: artisan `app:waha-ping {phone?}`. |
| G3 | Slice 1 = **tepat 4 event** yang seam-nya sudah ada: WO baru → HOD, reviewed → requester, assigned → karyawan, eskalasi H+3/5/6 → TL/HOD/DGM. Event lain (extend, verified/closed, daily work) menyusul bersama fiturnya. Extend **belum ada** di opti-works. |
| G4 | Resolusi nomor: `user->employee->number` (portal, otoritatif) **fallback** `user->phone`. Employee di-fetch `withoutGlobalScope('tenant')` (FK direct — aman; command eskalasi jalan tanpa tenant current). Null → baris outbox `skipped`, tanpa retry. |
| G5 | **Outbox table `wa_messages`** (pending/sent/failed/skipped, attempts, last_error). Job queue `$tries=3`, `$backoff=[30,120]`, `afterCommit`. Idempotency via guard hulu one-shot (`assigned_at`, `escalation_h*__sent_at`) — tanpa unique constraint. |
| G6 | Command-bot inbound **tidak ikut slice ini** — route webhook bahkan tidak dibuat (permukaan serang nol). Future work: hardening (HMAC, throttle, binding phone↔akun verified). |
| G7 | AGENTS.md Non-Goals di-amend (WA keluar, email tetap Non-Goals); ADR-0009 ditulis. |

## Desain

- `config/waha.php`: `url`, `api_key`, `session`, `timeout` (default 15s, connect 3s).
- `App\Services\WahaClient` — satu-satunya tempat HTTP keluar: `send(chatId, text)`
  (`->throw()`, tanpa auto-retry — retry = job), `normalizeChatId(phone)`
  (`08xx→628xx@c.us`, hormati chatId yang sudah mengandung `@`), `sessionStatus()`.
- `App\Channels\WahaWhatsAppChannel` — via() selalu menyertakan channel; `send()`
  no-op saat `WAHA_URL` kosong (lingkungan dev tanpa WAHA = nol baris outbox).
  Tulis baris outbox **di dalam transaksi bisnis yang sama** (rollback = pesan
  batal), lalu dispatch job after-commit.
- `App\Jobs\SendWaMessageJob` — reload baris by id, guard status `pending`
  (idempoten), normalisasi, kirim, tulis `sent`; gagal → `last_error` + rethrow
  (retry queue); percobaan terakhir → `failed()` menandai `failed`.
- Notifikasi (`WorkOrderCreated/Reviewed/Assigned/DeadlineEscalated`):
  `via()` += channel; `toWhatsApp(): string` (template per event — **tidak** memakai
  ulang template antar event, bug blueprint); `waEvent(): string` = value `type`
  in-app (`work_order.created`, dst). Link detail =
  `route('work-orders.show', [tenant code, id])`.
- Outbox **tidak** ber-`BelongsToTenant`: tabel infrastruktur (seperti
  `notifications`), `tenant_id` disimpan sebagai data; dibaca by id dari queue
  yang bisa jalan tanpa konteks tenant.
- `app:waha-ping {phone?}` — cek config + status session + kirim pesan test
  langsung (tanpa outbox).

## Acceptance criteria

1. Tanpa `WAHA_URL`: notifikasi lama tetap jalan persis seperti sebelumnya
   (regresi suite existing hijau), nol baris `wa_messages`, nol HTTP.
2. Dengan WAHA dikonfigurasi (Http::fake): create WO → 1 baris `sent`
   (event `work_order.created`), `POST /api/sendText` memuat `chatId`
   `628xx@c.us`, header `X-Api-Key`, session = config; teks memuat nomor WO.
3. Review → requester baris `work_order.reviewed`; assign → tiap karyawan
   tertaut baris `work_order.assigned`; eskalasi `app:check-deadline-escalation`
   → baris `work_order.escalated` per penerima (TL/HOD/DGM), teks `ESKALASI H+`.
4. Penerima tanpa nomor → baris `skipped` + alasan, tanpa HTTP.
5. `Http::fake` 500 → job rethrow (retry), `last_error` terisi; `failed()` →
   status `failed`.
6. Normalisasi: `0812…` → `62812…@c.us`; chatId mentah `628…@c.us` dipertahankan.
7. `app:waha-ping` tanpa config → pesan jelas, exit code error; dengan config →
   status session; dengan phone → pesan test terkirim.
8. pint bersih; suite full tanpa regresi.

## Non-goals slice ini

Inbound bot, halaman settings UI, per-tenant sender, template dari DB,
audit_logs (terpisah, keputusan round 1), fix teks Tiongkok di notifikasi
in-app eskalasi (pre-existing, di luar scope).
