---
created: 2026-10-29
tags: [daily-work, demo-seeder, design-doc]
status: pending-approval
---

# Design Doc — Task 8: Daily Work Demo Data + Grant + Final Wrap-up

## Intent & Why

Task 8 wraps up the Daily Work verification feature (FR-6). The backend (Tasks 1–5) and karyawan frontend (Tasks 6–7) are complete and pushed. Task 8 adds demo data so the HOD verification inbox ("Perlu Verifikasi") has something to show in a fresh demo database: one `pending_verify` instance (proof submitted, awaiting HOD verification) and one `done` instance (verified by HOD Budi). Then runs `app:grant-default-permissions`, the full verification suite, commits, and pushes.

**Approach A (chosen):** Extend the existing `DemoSeeder::seedDailyWork()` with two `firstOrCreateDailyWork` calls — no new seeder class, no new files, follows the existing idempotent pattern.

## Goals

1. Add one `DailyWork` instance with `status='pending_verify'`, `proof_notes` filled, `proof_attachments=[]` (proxy 404 safe for demo), `submitted_at` filled — demonstrates the HOD verification inbox with a reviewable item.
2. Add one `DailyWork` instance with `status='done'`, `verified_by` = HOD Budi (`budi.hod@appdutamall.com`), `verified_at` filled — demonstrates a completed/verified item.
3. All instances idempotent via `firstOrCreateDailyWork` — safe to re-run `db:seed`.
4. Run `app:grant-default-permissions` so `daily-work.verify` exists on HOD/TL/admin_tenant roles.
5. Full verification suite green: tsc, pint, build, `php artisan test --compact`.
6. Commit `DemoSeeder.php` + `TODO.md`, push to `feature/daily-work-verification`.

## Non-Goals

- ❌ New seeder class (Approach B rejected — over-engineered for demo data)
- ❌ New notification events for demo data (notifications are separate; existing notification seeder covers WO events)
- ❌ Changing any existing demo data
- ❌ Frontend code changes (Tasks 6–7 already complete)

## Constraints

- `DailyWork` model: `proof_notes` (text, nullable), `proof_attachments` (json, nullable), `submitted_at` (timestamp, nullable), `verified_by` (FK users, nullable), `verified_at` (timestamp, nullable), `rejected_by`/`rejected_at`/`rejection_note` (nullable).
- `firstOrCreateDailyWork` lookup key: `employee_id`, `work_date`, `source`, `title`, `template_id` (conditional). Values can include any fillable column.
- Status is a plain string column (not enum cast) — use string literals `'pending_verify'` / `'done'`.
- `daily_works` has unique `(template_id, work_date)` — lookup uses `whereDate`, so same `work_date` + same `title` + same `employee_id` + same `source` + same `template_id` = same row (no dupe on re-run).
- All demo users use password `password`. HOD Budi = `budi.hod@appdutamall.com` (`$org['users']['budi']`).
- PHP 8.4, Laravel 12, Pest.

## Approaches considered

| Approach | What it does | Tradeoff |
|----------|-------------|----------|
| A — Extend DemoSeeder (chosen) | Add 2 `firstOrCreateDailyWork` calls to `seedDailyWork` | Smallest diff, single seeder, idempotent, no new files |
| B — New standalone seeder | New `DailyWorkVerificationDemoSeeder` class | More files, separate versioning, over-engineered |

## What changes

**`database/seeders/DemoSeeder.php`** — `seedDailyWork()`: append two `firstOrCreateDailyWork` calls after the existing history loop:

1. `pending_verify`: Sari's employee, today's date, title "Patroli & inspeksi area teknik — verifikasi bukti", source `template`, `template_id=null`, `proof_notes` filled, `proof_attachments=[]`, `submitted_at`/`done_at` = today 15:00, `created_by` = null (karyawan self-submit).
2. `done`: Rudi's employee, yesterday's date, title "Cek level bahan bakar genset — verifikasi selesai", source `template`, `template_id=null`, `proof_notes` filled, `proof_attachments=[]`, `submitted_at`/`done_at` = yesterday 09:00, `verified_by` = Budi's user ID, `verified_at` = yesterday 10:00, `created_by` = Budi's user ID.

**`TODO.md`** — Mark Task 8 complete.

## Verification criteria (Definition of Done)

- `php artisan db:seed --class=DemoSeeder` runs without error on fresh DB
- `pending_verify` instance exists with `proof_notes` filled, `proof_attachments=[]`, `verified_by` null
- `done` instance exists with `verified_by` = Budi's user ID, `verified_at` filled
- `tsc --noEmit` exit 0
- `npm run build` success
- `php artisan test --compact` all pass
- Commit created on `feature/daily-work-verification`, pushed to origin

---

**Pending your approval.** Once approved, I'll invoke `writing-plans` and dispatch `engineering-senior-developer` to implement TDD task-by-task.
