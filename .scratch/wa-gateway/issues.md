# Issues — WhatsApp Gateway slice 1

Urutan pengerjaan (fundation → events → escalation → diagnostics). Satu issue = satu seam, bisa di-commit terpisah.

## 01 — Fondasi: config + WahaClient + outbox migration + model

- [ ] `config/waha.php` (url, api_key, session, timeout) + `.env.example` entries
- [ ] Migration `create_wa_messages_table`: uuid PK, `tenant_id` string FK, `work_order_id` FK nullable, `recipient_user_id` FK nullable, `event`, `phone`, `chat_id`, `payload`, `status` (pending/sent/failed/skipped), `attempts`, `last_error`, `sent_at`, timestamps; index `(status)`, `(work_order_id)`
- [ ] `App\Models\WaMessage` (HasUuids, casts; TANPA BelongsToTenant — alasan di spec)
- [ ] `App\Services\WahaClient`: `send()` (throw on failure), `normalizeChatId()`, `sessionStatus()`, `configured()`
- [ ] Test unit normalisasi chatId

## 02 — Channel + job outbox

- [ ] `App\Jobs\SendWaMessageJob` (tries=3, backoff, afterCommit, guard status pending, failed() → status failed)
- [ ] `App\Channels\WahaWhatsAppChannel` (no-op tanpa config; tulis outbox dalam transaksi; dispatch job afterCommit; skipped saat nomor kosong)
- [ ] Test: unconfigured → nol baris + nol HTTP; configured → baris sent + payload benar; tanpa nomor → skipped; HTTP 500 → retry + failed()

## 03 — Empat event notifikasi

- [ ] `WorkOrderCreated` / `WorkOrderReviewed` / `WorkOrderAssigned` / `WorkOrderDeadlineEscalated`: `via()` += channel, `toWhatsApp()` template per event (Bahasa, link detail), `waEvent()`
- [ ] `User::routeNotificationForWahaWhatsApp()`: employee.number (unscoped) → phone
- [ ] Test per event: penerima, chatId ternormalisasi, isi teks, event name di outbox

## 04 — Eskalasi via command + diagnostics + dokumen

- [ ] Verifikasi `app:check-deadline-escalation` menghasilkan baris outbox per penerima (test command-level)
- [ ] `app:waha-ping {phone?}`: cek config, session status, test send; test exit codes
- [ ] ADR-0009 + amendmen AGENTS.md (Non-Goals + tabel Modul MVP)
- [ ] pint + full suite
