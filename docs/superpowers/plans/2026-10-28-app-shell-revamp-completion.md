# App-Shell Revamp Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Commit the completed app-shell revamp (Option A) on branch `feature/daily-work-verification` with all test failures resolved, then push to origin.

**Architecture:** No new runtime code is introduced. The plan fixes 4 test failures caused by the `daily-work.verify` can-flag (added to `HandleInertiaRequests::share()` and `resources/js/types/auth.ts`) that was not accounted for in existing test expectations, then commits everything and pushes to a feature branch.

**Tech Stack:** Laravel 12 + PHP 8.4 (Pest), Inertia + React + shadcn (Vite build), Spatie RBAC

**Spec:** `docs/superpowers/specs/2026-10-28-app-shell-revamp-completion-design.md`

## Global Constraints

- PHP 8.4, Laravel 12, Pest (not PHPUnit)
- Frontend: Inertia + React + shadcn/ui; token app only (no raw hex); Wayfinder `--with-form`
- After modifying PHP: `vendor/bin/pint --dirty --format agent`
- Test runner: `vendor/bin/pest <file> --compact`; final: `php artisan test --compact`
- `tsc --noEmit` exit 0; `npm run build` success
- Commit per task, explicit `git add <files>` (no `git add .`); no `--no-verify`

## Review Focus

1. `daily-work.verify` can-flag present in `HandleInertiaRequests::share()` output — expected by tests that gate the Daily Work verification inbox page.
2. `GateTest` expected can-flag arrays must include `'daily-work.verify'` to match the real `auth.can` payload shape.
3. `DailyWorkVerificationInboxTest` expects `$response->json('props.auth.can')` to return valid JSON — the inbox route must serialize shared page props correctly.
4. Shell has no dangling references to deleted header files (`app-header.tsx`, `icon-rail.tsx`, `app-header-layout.tsx`, `fullcalendar-timeline.css`).

---

### Task 1: Fix DailyWorkVerificationInboxTest JSON response

**Files:**
- Modify: `tests/Feature/DailyWorkVerificationInboxTest.php:219-248`

**Interfaces:**
- Consumes: route `daily-work.verification` (`GET /{tenant}/daily-work/verification`) returning Inertia page with `props.auth.can`
- Produces: fixed test assertion that reads `auth.can['daily-work.verify']` correctly

- [ ] **Step 1: Inspect the failure** — run `vendor/bin/pest tests/Feature/DailyWorkVerificationInboxTest.php --compact` and capture the exact "Invalid JSON" error body
- [ ] **Step 2: Determine root cause** — check whether the route returns a non-Inertia response, a redirect, or a JSON parse failure on `props.auth.can`
- [ ] **Step 3: Fix the test** — either correct the assertion path or fix the backend so the route serializes `auth.can` as valid JSON
- [ ] **Step 4: Run test** — `vendor/bin/pest tests/Feature/DailyWorkVerificationInboxTest.php --compact` → PASS
- [ ] **Step 5: Commit** — `git add tests/Feature/DailyWorkVerificationInboxTest.php` → `fix(test): daily-work verify can flag assertion in inbox test`

### Task 2: Add daily-work.verify flag to GateTest expectations

**Files:**
- Modify: `tests/Feature/Rbac/GateTest.php:76-102`
- Modify: `tests/Feature/Rbac/GateTest.php:113-139`
- Modify: `tests/Feature/Rbac/GateTest.php:148-165+`

**Interfaces:**
- Consumes: `HandleInertiaRequests::share()` `auth.can` payload (already includes `daily-work.verify`)
- Produces: updated expected arrays matching actual output

- [ ] **Step 1: Update "full hak-akses flag set" test** — add `'daily-work.verify' => false` after `'daily-work.delete' => false` in the expected array (line ~98)
- [ ] **Step 2: Update "read-only viewer" test** — add `'daily-work.verify' => false` after `'daily-work.delete' => false` (line ~135)
- [ ] **Step 3: Update "super admin holding zero permissions" test** — add `'daily-work.verify' => true` after `'daily-work.delete' => true` (line ~165 area)
- [ ] **Step 4: Run GateTest** — `vendor/bin/pest tests/Feature/Rbac/GateTest.php --compact` → PASS
- [ ] **Step 5: Commit** — `git add tests/Feature/Rbac/GateTest.php` → `fix(test): include daily-work.verify flag in GateTest expectations`

### Task 3: Run full verification suite

**Files:** none

**Interfaces:**
- Consumes: all source code as modified by Tasks 1–2
- Produces: verification results (tsc, build, test suite)

- [ ] **Step 1: Run tsc** — `npx tsc --noEmit` → exit 0
- [ ] **Step 2: Run pint** — `vendor/bin/pint --dirty --format agent` → clean
- [ ] **Step 3: Run build** — `npm run build` → success
- [ ] **Step 4: Run full test suite** — `php artisan test --compact` → all pass, only pre-existing skips
- [ ] **Step 5: No commit** (verification only)

### Task 4: Commit all changes

**Files:** all modified/untracked files from the app-shell revamp + Daily Work verification backend

**Interfaces:**
- Consumes: working tree state on `feature/daily-work-verification`
- Produces: a single commit on the feature branch

- [ ] **Step 1: Stage explicit files** — `git add` for each changed file (shell components, controllers, middleware, routes, auth.ts, types, tests, design docs, etc.) — no `git add .`
- [ ] **Step 2: Commit** — `git commit -m "feat(app-shell): complete header/sidebar revamp + daily-work verify flag"` 
- [ ] **Step 3: Verify clean tree** — `git status` → no uncommitted changes

### Task 5: Push to origin

**Files:** none

**Interfaces:**
- Consumes: commit from Task 4 on `feature/daily-work-verification`
- Produces: branch pushed to `origin/feature/daily-work-verification`

- [ ] **Step 1: Push** — `git push origin feature/daily-work-verification`
- [ ] **Step 2: Verify** — confirm branch exists on origin (e.g., `git branch -r | grep feature/daily-work-verification`)

---

**Plan complete and saved to `docs/superpowers/plans/2026-10-28-app-shell-revamp-completion.md`. Please review the plan. Does it capture what you want?**
