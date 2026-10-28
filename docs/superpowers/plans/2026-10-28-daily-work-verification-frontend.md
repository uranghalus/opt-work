# Daily Work Verification Frontend — Task 6 (Karyawan Redesign)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the karyawan Daily Work `index.tsx` page with a hero progress bar, status-chip-as-filter, and a "Selesaikan" CTA that opens a proof dialog (catatan + 1–3 foto) submitting to `daily-work.submit`. Add `summary` payload + `pending_verify` status support to `DailyWorkController::index`. This is Task 6 of the approved Daily Work verification plan (`.scratch/daily-work-verification/plan.md`); backend Tasks 1–5 are complete.

**Architecture:** Follow the existing page structure (date nav prev/next/today, search debounce 300ms, EmptyState, Skeleton, server-side pagination) and add: `-components/status.tsx` (STATUS_LABELS/STATUS_STYLES/StatusChip/StatusFilterChip/RejectionCallout/PROOF_MAX), `-components/proof-dialog.tsx` (catatan textarea + file upload clamp 1–3 + object-URL preview + multipart submit), `index.tsx` redesign (hero progress bar "X/Y selesai", chip-filter row replacing status Select, card rail with 3px status rail + status-based CTAs), and `DailyWorkController::index` adds `summary` + `pending_verify` in status validation. Tokens only (no raw hex), touch ≥ 44px, light + dark modes.

**Tech Stack:** Laravel 12 + PHP 8.4 (Pest), Inertia + React + shadcn/ui (Tailwind), Wayfinder `--with-form`, Spatie RBAC

**Spec:** `.scratch/daily-work-verification/plan.md` (Task 6) + `.scratch/daily-work-verification/design.md` (approved 2026-10-27); design specs from design-ui-designer + design-ux-architect agents

## Global Constraints

- PHP 8.4, Laravel 12, Pest; Frontend: Inertia + React + shadcn/ui + Tailwind
- Token app only (surface-raised/info-subtle/success-subtle/warning-subtle/ink scale), NO raw hex
- Touch ≥ 44px (`min-h-11`); light AND dark modes readable by design; prefers-reduced-motion respected
- Wayfinder regen WAJIB `php artisan wayfinder:generate --with-form` before tsc
- After PHP changes: `vendor/bin/pint --dirty --format agent`
- `tsc --noEmit` exit 0; `npm run build` success; `php artisan test --compact` green
- Commit per task, explicit `git add <files>` (no `git add .`); no `--no-verify`

## Review Focus

1. `status` validation must include `pending_verify` (controller) — otherwise filter chips for pending_verify break the query.
2. `DailyWorkItem` TS type must add `pending_verify` to the status union + new optional fields (`proof_notes`, `proof_attachments`, `verified_by_name`, `rejection_note`, `submitted_at`) — otherwise tsc breaks on `index.tsx`.
3. `summary` payload `{all, pending, in_progress, pending_verify, done}` drives hero progress bar — divide-by-zero guard when `all === 0`.
4. Status never shown by color alone — every status shows icon + text label + color token (WCAG 1.4.1).
5. `pending_verify` items are read-only unless rejected (then `Selesaikan ulang` reopens ProofDialog); `done` items show verifier name + thumbnail, no CTA.
6. Wayfinder `submit` route must exist (`daily-work.submit` from Task 2) — ProofDialog uses `submit.url({tenant, dailyWork})`.

---

### Task 1: `status.tsx` — shared status primitives (labels, chips, callout)

**Files:**
- Create: `resources/js/pages/daily-work/-components/status.tsx`

**Interfaces:**
- Consumes: nothing new (existing `DailyWorkItem` type updated in Task 3)
- Produces: `STATUS_LABELS`, `STATUS_STYLES`, `STATUS_ORDER`, `PROOF_MAX`, `StatusChip`, `StatusFilterChip`, `RejectionCallout`

- [ ] **Step 1: Write the component** — `STATUS_LABELS` (4: pending→Belum, in_progress→Proses, pending_verify→Perlu verifikasi HOD/TL, done→Telah selesai); `STATUS_STYLES` (icon Circle/CircleDot/Clock3/CheckCircle2 + token classes per status); `StatusChip({item, size?})` read-only icon+label+verified_by_name; `StatusFilterChip({value, label, active, onPress})` role="button" aria-pressed min-h-11 whitespace-nowrap; `RejectionCallout({item})` warning-tinted callout with rejection note; `PROOF_MAX = 3`. Tokens only (bg-surface-raised/bg-info-subtle/bg-success-subtle/bg-warning-subtle, text-ink*/text-info*/text-success*/text-warning-ink, border-border/border-info/border-success/border-warning), no raw hex.
- [ ] **Step 2: Run tsc** — `npx tsc --noEmit` → exit 0 (file imports lucide-react icons + cn; no DailyWorkItem import yet → may need `any` or import from index; keep standalone to avoid circular dep).
- [ ] **Step 3: Commit** — `git add resources/js/pages/daily-work/-components/status.tsx` → `feat(daily-work): status components (labels, chips, callout)`

### Task 2: `proof-dialog.tsx` — proof submission dialog

**Files:**
- Create: `resources/js/pages/daily-work/-components/proof-dialog.tsx`

**Interfaces:**
- Consumes: `submit` wayfinder route (`@/routes/daily-work`, from backend Task 2), `STATUS_STYLES`/`PROOF_MAX` from Task 1
- Produces: `ProofDialog({selected, open, onOpenChange})` — catatan textarea required max 2000 + char counter; file input image/* clamp 1–3 with object-URL preview grid + remove per thumb; per-field `role="alert"` errors; `form.post(submit.url(...))` multipart; loading/disabled states; success toast + close + parent reload

- [ ] **Step 1: Write the component** — props `{selected: DailyWorkItem|null, open, onOpenChange}`; textarea (required, max 2000, char counter "X/2000"); hidden file input `accept="image/*"` triggered by browse button; preview grid object-URL thumbnails max-w-full object-fit cover with × remove; validation errors role="alert" per field (belum ada foto / terlalu banyak / ukuran > 2MB / format tidak didukung); footer "Batal" ghost + "Kirim bukti" primary indigo min-h-11 disabled while submitting/invalid; submitting state shows spinner "Mengirim…", fields disabled, no close until resolve; success → sonner toast "Bukti dikirim — menunggu verifikasi HOD.", onOpenChange(false).
- [ ] **Step 2: Run tsc** — `npx tsc --noEmit` → exit 0.
- [ ] **Step 3: Commit** — `git add resources/js/pages/daily-work/-components/proof-dialog.tsx` → `feat(daily-work): proof dialog (catatan + 1–3 foto) for submit`

### Task 3: `index.tsx` redesign + controller `summary` payload

**Files:**
- Modify: `resources/js/pages/daily-work/index.tsx` (full redesign per spec)
- Modify: `app/Http/Controllers/DailyWorkController.php` (index: add `summary` payload + `pending_verify` in status validation)

**Interfaces:**
- Consumes: `summary` payload from controller; `submit`/`update`/`attachment` wayfinder routes; `StatusChip`/`StatusFilterChip`/`RejectionCallout`/`ProofDialog` from Tasks 1–2
- Produces: redesigned index with hero progress bar `{done}/{all} selesai` segmented bar, chip-filter row (status chips as filter toggle, flex-wrap, +n disclosure), card rail with 3px status rail + status-based CTAs (Selesaikan → ProofDialog; pending_verify read-only + Selesaikan ulang; done → verifier name + thumbnail), RejectionCallout, empty state copy, preserve date nav/search/pagination/Skeleton from Task 4

- [ ] **Step 1: Update DailyWorkItem TS type** — add `'pending_verify'` to status union + optional fields `proof_notes?: string|null`, `proof_attachments?: string[]`, `verified_by_name?: string|null`, `rejection_note?: string|null`, `submitted_at?: string|null`.
- [ ] **Step 2: Rewrite index.tsx** — hero progress bar (done/all segmented bar, guard all===0), replace status `<Select>` with `StatusFilterChip` row (`all` + 4 statuses, aria-pressed, flex-wrap, +n disclosure), card rail with left 3px status rail + header row (title + StatusChip right) + meta row (source badge + location + done_at) + action bar (Selesaikan primary → ProofDialog when pending/in_progress; read-only + Selesaikan ulang when pending_verify+rejected; done → verifier name + first-photo thumb), ProofDialog controlled state, preserve date nav/search/pagination/Skeleton.
- [ ] **Step 3: Update controller index** — add `summary` counts per status (single count query) to Inertia payload; add `'pending_verify'` to status validation `in:pending,in_progress,done`.
- [ ] **Step 4: Run tsc** — `npx tsc --noEmit` → exit 0.
- [ ] **Step 5: Wayfinder regen** — `php artisan wayfinder:generate --with-form` (needed for `submit.url` type).
- [ ] **Step 6: Commit** — `git add resources/js/pages/daily-work/index.tsx app/Http/Controllers/DailyWorkController.php` → `feat(daily-work): karyawan index redesign — hero progress + chip filter + proof dialog`

### Task 4: Full verification + commit + push

**Files:** none

**Interfaces:**
- Consumes: all changes from Tasks 1–3
- Produces: verification results + commit + push

- [ ] **Step 1: Run tsc** — `npx tsc --noEmit` → exit 0.
- [ ] **Step 2: Run pint** — `vendor/bin/pint --dirty --format agent` → clean.
- [ ] **Step 3: Run build** — `npm run build` → success.
- [ ] **Step 4: Run full test suite** — `php artisan test --compact` → all pass, only pre-existing skips.
- [ ] **Step 5: Commit remaining** — explicit `git add` for any uncommitted files → `chore(daily-work): finalize Task 6 frontend`.
- [ ] **Step 6: Push** — `git push origin feature/daily-work-verification`.

---

**Plan complete and saved to `docs/superpowers/plans/2026-10-28-daily-work-verification-frontend.md`. Please review the plan. Does it capture what you want?**

I recommend **Native** execution — all tasks are small, tightly coupled to the same test suite + tsc/build gates, and a shipped mistake is cheap to catch with `tsc` + full suite. Previous execution method (Native) is preserved.
