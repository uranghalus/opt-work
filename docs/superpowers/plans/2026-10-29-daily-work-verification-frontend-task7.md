# Daily Work HOD/TL "Perlu Verifikasi" Frontend (Task 7)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the HOD/TL verification inbox page ("Perlu Verifikasi") plus the shared approve/reject dialogs, wire them into the team page and navigation, gated by `daily-work.verify`. Karyawan submit bukti kerja landing in `pending_verify`; HOD/TL opens this page, reviews each submission one at a time, and either tandai selesai (approve → `done`) or kembali (reject → `in_progress` with required rejection note).

**Architecture:** Shared `VerificationDialog` hosted by `verification.tsx` inbox and linked from `team.tsx`; approve flows through `ConfirmDialog` → `daily-work.verify` POST → `done` + `verified_by/at`; reject flows through `RejectDialog` (required note) → `daily-work.reject` POST → `in_progress` + `rejected_by/at/rejection_note`; photos retained on reject; `router.reload({ only: ['verificationQueue'] })` after each decision; processing state disables buttons (409 double-submit prevention). Nav item "Perlu Verifikasi" gated `daily-work.verify`, icon `BadgeCheck`, tone `ops`, after Daily Work.

**Tech Stack:** Laravel 12 + PHP 8.4 (Pest), Inertia + React + shadcn/ui (Tailwind), Wayfinder `--with-form`, Spatie RBAC, sonner toast

**Spec:** `docs/superpowers/specs/2026-10-29-fr7-hod-verification-frontend-design.md`

## Global Constraints

- PHP 8.4, Laravel 12, Pest; Frontend: Inertia + React + shadcn/ui + Tailwind
- Tokens only (surface-raised/info-subtle/success-subtle/warning-subtle/ink/border-border/shadow-card), NO raw hex
- No emoji icons — lucide-react only
- Touch ≥ 44px (`min-h-11`) on every interactive element
- Light + dark theme support via CSS variables; prefers-reduced-motion respected
- Wayfinder regen WAJIB `php artisan wayfinder:generate --with-form`
- `tsc --noEmit` exit 0; `npm run build` success; `php artisan test --compact` green
- Commit per task, explicit `git add <files>` (no `git add .`); no `--no-verify`

## Review Focus

1. `pending_verify` is the only status in the verification queue; `pending`/`done` do not appear as queue items — cards must not assume other statuses.
2. `VerificationQueueItem.verification.rejected` + `rejection_note` present when item was previously returned; dialog must show context without crashing on nulls.
3. Approve is one-tap + ConfirmDialog (common case); reject is two-tap + required note (exception) — neither may fire twice (processing flag + 409 guard).
4. Reject keeps `proof_attachments` (state `in_progress` + rejection columns); photos never deleted.
5. Nav item gated `daily-work.verify` (not `daily-work.read`); `BadgeCheck` icon, `ops` tone, after Daily Work.
6. `router.reload({ only: ['verificationQueue'] })` after approve/reject so queue reflects new state.

---

### Task 1: `verification-dialog.tsx` — shared approve/reject review modal

**Files:**
- Create: `resources/js/pages/daily-work/-components/verification-dialog.tsx`

**Interfaces:**
- Consumes: `verification`/`reject` wayfinder routes (`@/routes/daily-work`), `ConfirmationDialog` from `@/components/confirm-dialog`, `StatusChip`/`RejectionCallout` from `-components/status`, `toast` from sonner
- Produces: `VerificationDialog({ item, open, onOpenChange, onApprove, onReject, processing })` — read-only header (karyawan name, title, pending_verify status chip, submitted_at id-ID), proof notes read-only, location with MapPin, photo inline grid thumbnails + count badge + click → `dailyWorkAttachment` proxy URL `target="_blank" rel="noopener"`, footer "Tandai telah selesai" primary → opens ConfirmDialog for approve; "Kembali" destructive outline → opens RejectDialog

- [ ] **Step 1: Write the component** — props `item: VerificationQueueItem, open: boolean, onOpenChange, onApprove: (id) => void, onReject: (id, note) => void, processing: boolean`; Dialog/DialogContent/DialogHeader/DialogTitle/DialogDescription/DialogFooter shadcn; header with employee name + title + StatusChip + submitted_at; proof notes read-only if present; location if present; photo inline grid `grid-cols-2 sm:grid-cols-3 gap-2` thumbnails `h-20 w-20 rounded-md object-cover` with count badge overlay; click handler `window.open(dailyWorkAttachment({tenant, dailyWork: item.id, index}).url, '_blank')`; footer primary "Tandai telah selesai" (`min-h-11`, disabled when processing) → `ConfirmDialog.open({...})` calling `onApprove(id)`; destructive outline "Kembali" (`min-h-11`, disabled when processing) → `RejectDialog.open(...)`
- [ ] **Step 2: Run tsc** — `npx tsc --noEmit` → exit 0
- [ ] **Step 3: Commit** — `git add resources/js/pages/daily-work/-components/verification-dialog.tsx` → `feat(daily-work): verification dialog (approve/reject entry)`

### Task 2: `reject-dialog.tsx` — required-note reject dialog

**Files:**
- Create: `resources/js/pages/daily-work/-components/reject-dialog.tsx`

**Interfaces:**
- Consumes: `reject` wayfinder route, `toast` from sonner
- Produces: `RejectDialog({ item, open, onOpenChange, onReject, processing })` — constructive title "Kembalikan bukti kerja?", description "Tuliskan alasan agar ${employee.nama_employee} dapat memperbaiki bukti ini.", required `rejection_note` textarea (`*`, `aria-required`, inline `role="alert"` error when empty), footer "Batal" outline + "Kembali" destructive (disabled while processing or note empty)

- [ ] **Step 1: Write the component** — props `item, open, onOpenChange, onReject: (id, note) => void, processing`; Dialog with DialogHeader/DialogTitle/DialogDescription/DialogFooter; textarea required max 2000 with char counter; inline error `role="alert"` when empty; footer "Batal" + "Kembali" destructive; sonner toast.success("Bukti dikembalikan kepada karyawan.") on success, toast.error on failure
- [ ] **Step 2: Run tsc** — `npx tsc --noEmit` → exit 0
- [ ] **Step 3: Commit** — `git add resources/js/pages/daily-work/-components/reject-dialog.tsx` → `feat(daily-work): reject dialog with required note`

### Task 3: `verification.tsx` — inbox page rewrite

**Files:**
- Modify: `resources/js/pages/daily-work/verification.tsx` (replace placeholder)

**Interfaces:**
- Consumes: `verificationQueue` + `employees` page props (backend Task 5), `verification`/`verify`/`reject`/`attachment` wayfinder routes, `VerificationDialog`/`RejectDialog` (Tasks 1–2), `StatusChip`/`StatusFilterChip`/`RejectionCallout` (Task 6 `status.tsx`)
- Produces: breadcrumb `Daily Work › Perlu Verifikasi`; filter bar (employee Select ≤200 + search Input debounce 300ms, NO date filter); card list at-a-glance hierarchy; pagination; EmptyState "Tidak ada tugas menunggu verifikasi"; Skeleton loading; hosts VerificationDialog + RejectDialog; `router.reload({ only: ['verificationQueue'] })` after decide

- [ ] **Step 1: Rewrite verification.tsx** — Head title; layout breadcrumbs [{Daily Work, href index}, {Perlu Verifikasi}]; filter bar employee Select (≤200) + search debounce 300ms; card list with karyawan name + title + StatusChip (pending_verify) + submitted_at id-ID + source badge (Rutin/Tambah) + location + photo thumbnails + count badge + footer "Tandai telah selesai"/"Kembali"; pagination prev/next + "Halaman X dari Y" aria-live polite; EmptyState title "Tidak ada tugas menunggu verifikasi" description "Semua bukti kerja tim sudah diverifikasi atau belum ada yang disubmit."; 3 Skeleton cards when navigating aria-busy true; hosted dialogs; reload queue + toast after decide
- [ ] **Step 2: Run tsc** — `npx tsc --noEmit` → exit 0
- [ ] **Step 3: Commit** — `git add resources/js/pages/daily-work/verification.tsx` → `feat(daily-work): HOD verification inbox page`

### Task 4: `team.tsx` modify — route pending_verify to verification page

**Files:**
- Modify: `resources/js/pages/daily-work/team.tsx`

**Interfaces:**
- Consumes: `verification` wayfinder route (`@/routes/daily-work`)
- Produces: items with status `pending_verify` show read-only "Menunggu verifikasi HOD/TL" + link/chip "Lihat antrean verifikasi" → `verification({ tenant })`; `onComplete` (ProofDialog) only fires for `pending`/`in_progress`; shared `StatusChip` from `-components/status.tsx`

- [ ] **Step 1: Modify team.tsx** — import `verification` route; for `isPendingVerify` replace "Selesaikan ulang" button with read-only state + navigation chip to verification page; `onComplete` guarded to `pending`/`in_progress`
- [ ] **Step 2: Run tsc** — `npx tsc --noEmit` → exit 0
- [ ] **Step 3: Commit** — `git add resources/js/pages/daily-work/team.tsx` → `feat(daily-work): team page routes pending_verify to verification page`

### Task 5: Nav gating — "Perlu Verifikasi" item in nav-items

**Files:**
- Modify: `resources/js/components/nav-items.tsx`

**Interfaces:**
- Consumes: `can('daily-work.verify')` flag (already in HandleInertiaRequests/auth.ts from Task 5)
- Produces: nav item "Perlu Verifikasi" (shortTitle "Verifikasi", icon `BadgeCheck`, tone `ops`) in ops group after Daily Work item, gated `auth?.can?.['daily-work.verify']`, href `verification({ tenant })`

- [ ] **Step 1: Modify nav-items.tsx** — add import `verification as dailyWorkVerification`; add `canReadDailyWork`-style boolean `canVerifyDailyWork` to NavContext; destructure in navGroups; insert tenantItem("Perlu Verifikasi", BadgeCheck, ops) gated by `canVerifyDailyWork` after Daily Work item
- [ ] **Step 2: Run tsc** — `npx tsc --noEmit` → exit 0
- [ ] **Step 3: Commit** — `git add resources/js/components/nav-items.tsx` → `feat(daily-work): nav item Perlu Verifikasi gated daily-work.verify`

### Task 6: Full verification + commit + push

**Files:** none

**Interfaces:**
- Consumes: all changes from Tasks 1–5
- Produces: verification results + commit + push

- [ ] **Step 1: Run tsc** — `npx tsc --noEmit` → exit 0
- [ ] **Step 2: Run build** — `npm run build` → success
- [ ] **Step 3: Run full test suite** — `php artisan test --compact` → all pass, only pre-existing skips
- [ ] **Step 4: Commit remaining** — explicit `git add` for any uncommitted files → `feat(daily-work): HOD verification inbox + nav + team routing`
- [ ] **Step 5: Push** — `git push origin feature/daily-work-verification`

---

**Plan complete and saved to `docs/superpowers/plans/2026-10-29-daily-work-verification-frontend-task7.md`. Please review the plan. Does it capture what you want?**

I recommend **Native** execution (preserved from your previous choice) — all tasks are small, tightly coupled to the same tsc/build/test gates, and a shipped mistake is cheap to catch with `tsc` + the full suite.
