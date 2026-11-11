# Daily Work Task 8 — Demo Data + Grant + Final Wrap-up

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Daily Work verification demo data (one `pending_verify` instance for HOD inbox review, one `done` instance verified by HOD Budi) to the existing DemoSeeder, run `app:grant-default-permissions`, execute the full verification suite, commit, and push to `feature/daily-work-verification`.

**Architecture:** Extend the existing `DemoSeeder::seedDailyWork()` with two `firstOrCreateDailyWork()` calls — no new seeder class, no new files. The instances are idempotent via the existing natural-key lookup (`employee_id`, `work_date`, `source`, `title`, `template_id`). Then run `php artisan db:seed --class=DemoSeeder`, `php artisan app:grant-default-permissions`, and the full verification suite (tsc, pint, build, pest).

**Tech Stack:** Laravel 12 + PHP 8.4 (Pest), Stancl tenancy single-database, Spatie RBAC

**Spec:** `docs/superpowers/specs/2026-10-29-fr8-daily-work-demo-seeder-design.md`

## Global Constraints

- `DailyWork` model: `proof_notes` (text, nullable), `proof_attachments` (json, nullable), `submitted_at` (timestamp, nullable), `verified_by` (FK users, nullable), `verified_at` (timestamp, nullable), `rejected_by`/`rejected_at`/`rejection_note` (nullable).
- Status is a plain string column (not enum cast) — use string literals `'pending_verify'` / `'done'`.
- `daily_works` has unique `(template_id, work_date)` — lookup uses `whereDate`, so same `work_date` + same `title` + same `employee_id` + same `source` + same `template_id` = same row (no dupe on re-run).
- `firstOrCreateDailyWork(array $lookup, array $values)` — lookup key: `employee_id`, `work_date`, `source`, `title`, `template_id` (conditional); values can include any fillable column.
- All demo users use password `password`. HOD Budi = `budi.hod@appdutamall.com` (`$org['users']['budi']`).
- PHP 8.4, Laravel 12, Pest.

## Review Focus

1. `pending_verify` instance must exist with `proof_notes` filled, `proof_attachments=[]`, `verified_by` null — shows up in HOD verification inbox.
2. `done` instance must exist with `verified_by` = Budi's user ID, `verified_at` filled — demonstrates completed/verified item.
3. Re-running `db:seed --class=DemoSeeder` must not create duplicate rows (idempotency via `firstOrCreateDailyWork`).
4. `daily-work.verify` permission must exist on `hod`, `team_leader`, `admin_tenant` roles after `app:grant-default-permissions`.
5. Full suite green: tsc, pint, build, `php artisan test --compact`.

---

### Task 1: Add Daily Work verification demo data to DemoSeeder

**Files:**
- Modify: `database/seeders/DemoSeeder.php` — `seedDailyWork()` method

**Interfaces:**
- Consumes: `$org['users']['budi']` (HOD Budi user), `$org['employees']['sari']`, `$org['employees']['rudi']`, `$today` (Carbon::today)
- Produces: two new `DailyWork` rows appended to `seedDailyWork()`

- [ ] **Step 1: Add pending_verify instance** — After the existing history loop (after line ~878 `return $sariAdhoc;`), add a `firstOrCreateDailyWork` call:
  - lookup: `employee_id` = `$org['employees']['sari']->getKey()`, `work_date` = `$today`, `source` = `'template'`, `title` = `'Patroli & inspeksi area teknik — verifikasi bukti'`, `template_id` = null
  - values: `description` filled, `location` filled, `status` = `'pending_verify'`, `proof_notes` = `'Sudah melakukan patroli umum dan inspeksi semua area teknik. Berikut bukti foto.'`, `proof_attachments` = `[]`, `submitted_at` = `$today->copy()->setTime(15, 0)`, `done_at` = `$today->copy()->setTime(15, 0)`, `created_by` = null
- [ ] **Step 2: Add done instance** — After the pending_verify instance, add a `firstOrCreateDailyWork` call:
  - lookup: `employee_id` = `$org['employees']['rudi']->getKey()`, `work_date` = `$today->copy()->subDay()`, `source` = `'template'`, `title` = `'Cek level bahan bakar genset — verifikasi selesai'`, `template_id` = null
  - values: `description` filled, `location` filled, `status` = `'done'`, `proof_notes` = `'Level bahan bakar genset normal, tidak ada bocor.', `proof_attachments` = `[]`, `submitted_at` = `$today->copy()->subDay()->setTime(9, 0)`, `done_at` = `$today->copy()->subDay()->setTime(9, 0)`, `verified_by` = `$org['users']['budi']->getKey()`, `verified_at` = `$today->copy()->subDay()->setTime(10, 0)`, `created_by` = `$org['users']['budi']->getKey()`
- [ ] **Step 3: Run seeder to verify** — `php artisan db:seed --class=DemoSeeder` — runs without error
- [ ] **Step 4: Verify instances exist** — query `DailyWork::where('status', 'pending_verify')->first()` has `proof_notes` filled, `proof_attachments=[]`, `verified_by` null; `DailyWork::where('status', 'done')->where('verified_by', $budiId)->first()` exists with `verified_at` filled
- [ ] **Step 5: Commit** — `git add database/seeders/DemoSeeder.php` → `feat(daily-work): demo data verifikasi — pending_verify + done instances`

### Task 2: Grant default permissions

**Files:** none

**Interfaces:**
- Consumes: none (artisan command)
- Produces: `daily-work.verify` permission granted to `hod`, `team_leader`, `admin_tenant` roles

- [ ] **Step 1: Run grant command** — `php artisan app:grant-default-permissions --no-interaction` — output 0 pending / 0 changes
- [ ] **Step 2: Commit** — no code changes; commit only if DemoSeeder was modified (already committed in Task 1). Skip commit unless new changes exist.

### Task 3: Full verification suite

**Files:** none

**Interfaces:**
- Consumes: all changes from Task 1
- Produces: verification results

- [ ] **Step 1: Run tsc** — `npx tsc --noEmit` → exit 0
- [ ] **Step 2: Run pint** — `vendor/bin/pint --dirty --format agent` → clean
- [ ] **Step 3: Run build** — `npm run build` → success
- [ ] **Step 4: Run full test suite** — `php artisan test --compact` → all pass, only pre-existing skips

### Task 4: Commit + push

**Files:** none (all code committed in Task 1)

**Interfaces:**
- Consumes: commit from Task 1
- Produces: branch pushed to origin

- [ ] **Step 1: Push** — `git push origin feature/daily-work-verification`
- [ ] **Step 2: Verify** — confirm branch exists on origin

---

**Plan complete and saved to `docs/superpowers/plans/2026-10-29-daily-work-demo-seeder-task8.md`. Please review the plan. Does it capture what you want?**

I recommend **subagent-driven** execution — dispatching `engineering-senior-developer` to implement each task sequentially, since you explicitly asked to assign this to that agent. The tasks are small and independent enough for a fresh subagent per task, but since they're sequential (data → grant → verify → push), one subagent can carry the full context.
