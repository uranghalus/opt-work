---
created: 2026-10-29
tags: [daily-work, verification, hod-tl, design-system, ui-spec, frontend]
status: pending-approval
---

# Design Spec — FR-7: Daily Work HOD/TL "Perlu Verifikasi" Verification Frontend

## Intent & Why

Task 7 delivers the HOD/TL verification inbox ("Perlu Verifikasi") for Daily Work. Karyawan submit bukti kerja (notes + 1–3 foto) which lands in status `pending_verify`. The HOD/TL opens this page, reviews each submission one at a time, and either tandai selesai (approve → `done`) or kembali (reject → `in_progress` with required rejection note). The flow is single-task focused: one card at a time, one decision per item, no date filter, no bulk actions — deliberately minimizing decision fatigue.

The backend endpoints (Task 5) are ready: `daily-work.verification` (GET queue), `daily-work.verify` (POST), `daily-work.reject` (POST), `daily-work.attachment` (GET proxy URL). This spec covers only the React frontend.

## Goals

1. HOD/TL sees a cross-date queue of all `pending_verify` items within their verifiable scope, newest `submitted_at` first.
2. One-tap approve via ConfirmDialog; deliberate reject via required-note dialog.
3. Photo review inline: thumbnails + count badge, click opens proxy URL in new tab.
4. Processing states disable all buttons; double-submit 409 prevention; focus returns to inbox after decision.
5. `router.reload({ only: ['verificationQueue'] })` after approve/reject so the queue reflects the new state.
6. Nav item "Perlu Verifikasi" gated `daily-work.verify`, icon `BadgeCheck`, tone `ops`, positioned after Daily Work. Breadcrumbs: Daily Work › Perlu Verifikasi.
7. WCAG AA: keyboard-only, touch ≥44px, focus rings, 4.5:1 contrast both themes, color never alone, id-ID locale times, screen-reader labels, EmptyState descriptive.
8. `tsc --noEmit` exit 0, `npm run build` success, test suite green.

## Non-Goals

- ❌ Date filter on the verification queue (antrean lintas tanggal per backend).
- ❌ Bulk approve/reject.
- ❌ Editing karyawan, location, or notes from the verification page (read-only review).
- ❌ WhatsApp outbound from the frontend (handled by backend/notifications).
- ❌ New icons or custom illustrations — reuse `lucide-react` only.

## Constraints

- Design tokens only — NO raw hex values in components. Use CSS variables from `resources/css/app.css`.
- No emoji icons — `lucide-react` only.
- Touch target ≥44px (`min-h-11`) on every interactive element.
- Light + dark theme support via CSS variables.
- Indonesian copy throughout.
- Per-component files under `resources/js/pages/daily-work/` and `resources/js/components/`.
- Wayfinder `--with-form` route helpers.
- `pint` for PHP formatting (backend unchanged — frontend only), `tsc --noEmit` exit 0, `npm run build` success.

## Design System Token Reference (from `resources/css/app.css`)

| Token | Light | Dark | Usage |
|-------|-------|------|-------|
| `--primary` | brand | brand | primary buttons, focus ring |
| `--primary-foreground` | white | ink | primary button text |
| `--surface` | #FFFFFF | #1E1E1E | card surface |
| `--surface-raised` | #F6F8FA | #252525 | raised surfaces, input backgrounds |
| `--ink` | #101418 | #FFFFFF | primary text |
| `--ink-muted` | #5B6470 | #98989D | secondary text |
| `--ink-subtle` | #7A8694 | #7A7A80 | tertiary text |
| `--border` / `--border-border` | #D8DEE6 | #2C2C2E | card borders, input borders |
| `--shadow-card` | soft | soft-dark | card elevation |
| `--info` / `--info-subtle` | info | info | in-progress status |
| `--warning` / `--warning-subtle` / `--warning-ink` | warning | warning | pending_verify status |
| `--success` / `--success-subtle` | success | success | done status |
| `--destructive` | #D70015 | #FF453A | reject / destructive actions |
| `--focus-ring` | brand | brand | visible focus indicator |
| `--radius-sm` | 0.25rem | same | badges, chips |
| `--radius-md` | 0.5rem | same | buttons |
| `--radius-xl` | 1rem | same | cards, dialogs |

Fonts: Outfit (headings), Work Sans (body/UI). Motion: 200–300ms `ease-standard`.

## Status Color Token Mapping (per status)

Only these statuses appear on the verification page:

| Status | Label | Icon | Badge bg | Badge text | Rail | Token basis |
|--------|-------|------|----------|------------|------|-------------|
| `pending_verify` | Perlu verifikasi HOD/TL | `Clock3` | `bg-warning-subtle` | `text-warning-ink` | `border-warning` | warning family |
| `done` | Telah selesai | `CheckCircle2` | `bg-success-subtle` | `text-success` | `border-success` | success family |
| `in_progress` | Proses | `CircleDot` | `bg-info-subtle` | `text-info` | `border-info` | info family |

`pending` does not appear in the verification queue (only `pending_verify`), but `StatusChip` from `status.tsx` handles all four.

## A) Component File List

### 1. `resources/js/pages/daily-work/verification.tsx` (rewrite)
Replaces the current placeholder. Responsibility: the HOD/TL verification inbox page. Renders breadcrumb, filter bar, card list, pagination, empty/loading/error states, and hosts `VerificationDialog` + `RejectDialog`.

**Props:** None (reads `verificationQueue`, `employees` from Inertia page props; `tenant` via `useRequiredTenant`).

**Exact interface:**
```tsx
type VerificationQueueItem = {
    id: string;
    work_date: string;
    title: string;
    description: string | null;
    location: string | null;
    status: 'pending_verify';
    submitted_at: string | null;
    done_at: string | null;
    source: 'template' | 'adhoc';
    template_id: string | null;
    proof_notes: string | null;
    proof_attachments: string[];
    employee: { id: string; nama_employee: string };
    verification: {
        verified_by_name: string | null;
        verified_at: string | null;
        rejected: boolean;
        rejection_note: string | null;
    };
};

type PageProps = {
    verificationQueue: Pagination & { data: VerificationQueueItem[] };
    employees: { id: string; nama_employee: string }[];
};
```

**Layout responsibility:**
- `Head title="Perlu Verifikasi"`, layout breadcrumbs `[{title:'Daily Work', href:index}, {title:'Perlu Verifikasi'}]`
- Filter bar: employee Select (≤200 options, label "Karyawan"), search Input (debounce 300ms, placeholder "Cari tugas…"), NO date filter
- Card list: at-a-glance hierarchy cards (karyawan name, title, status chip, submitted_at, source badge, location, photo thumbnails + count badge)
- Pagination (prev/next + "Halaman X dari Y")
- EmptyState: "Tidak ada tugas menunggu verifikasi"
- Skeleton loading states
- `VerificationDialog` and `RejectDialog` hosted here, opened per selected item

### 2. `resources/js/pages/daily-work/-components/verification-dialog.tsx` (new)
Responsibility: read-only review modal for a single `pending_verify` item. Two footer actions: "Tandai telah selesai" (primary → opens `ConfirmDialog`) and "Kembali" (destructive outline → opens `RejectDialog`).

**Props:**
```tsx
type VerificationDialogProps = {
    item: VerificationQueueItem;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onApprove: (id: string) => void;
    onReject: (id: string, note: string) => void;
    processing: boolean;        // true while approve/reject in flight
};
```

**Anatomy (top → bottom):**
1. Header: karyawan name (`employee.nama_employee`) + title + status chip (`pending_verify`) + `submitted_at` formatted id-ID
2. Proof notes: read-only, if `proof_notes` present
3. Location: if `location` present, with `MapPin` icon
4. Photo inline grid: thumbnails (max 3), count badge overlay if >1 visible, click → `dailyWorkAttachment` proxy URL with `target="_blank" rel="noopener"`
5. Footer: "Tandai telah selesai" (primary, `min-h-11`) → ConfirmDialog; "Kembali" (destructive outline, `min-h-11`) → RejectDialog

### 3. `resources/js/pages/daily-work/-components/reject-dialog.tsx` (new)
Responsibility: required-note reject dialog. Constructive framing ("Tolong diperbaiki karena…"), note textarea required (*), inline error `role="alert"`, photos retained on reject (state stays `in_progress` with `rejected_at`/`rejection_note`).

**Props:**
```tsx
type RejectDialogProps = {
    item: VerificationQueueItem;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onReject: (id: string, note: string) => void;
    processing: boolean;
};
```

### 4. `resources/js/pages/daily-work/team.tsx` (modify)
Responsibility: add a "Verifikasi" action on items with status `pending_verify` that opens the verification flow. Currently shows "Selesaikan ulang" for `pending_verify`. After this change, `pending_verify` items show a read-only state + a verification entry point (linked to `daily-work.verification` route), and the `onComplete` callback no longer opens `ProofDialog` for `pending_verify` items — instead navigation to the verification page.

**Changes:**
- Import `verification` route from `@/routes/daily-work`
- For `isPendingVerify`: replace "Selesaikan ulang" button with read-only "Menunggu verifikasi HOD/TL" + link/chip to "Lihat antrean verifikasi" → `verification({ tenant })`
- `onComplete` only fires for `pending` / `in_progress` (opens ProofDialog)
- Status chip uses shared `StatusChip` from `-components/status.tsx` (already imported)

### 5. `resources/js/components/nav-items.tsx` (modify)
Responsibility: add "Perlu Verifikasi" nav item.

**Changes:**
- Add import `{ verification as dailyWorkVerification } from '@/routes/daily-work'`
- In `ops` group items, after `Daily Work` (`dailyWorkIndex`), add:
```tsx
...(canReadDailyWork
    ? [
          ...(dailyWorkIndex item),
          tenantItem(
              {
                  title: 'Perlu Verifikasi',
                  shortTitle: 'Verifikasi',
                  icon: BadgeCheck,
                  tone: 'ops',
              },
              tenant,
              (t) => dailyWorkVerification({ tenant: t }),
          ),
      ]
    : [...])
```
- Gate: `canReadDailyWork` (HOD/TL who can read daily work). If `daily-work.verify` permission specifically is needed, use `auth?.can?.['daily-work.verify']` — spec says gated `daily-work.verify`.
- Icon: `BadgeCheck` from `lucide-react`
- Tone: `ops` (after Daily Work in the ops group)
- `shortTitle: 'Verifikasi'` for bottom nav

### 6. `resources/js/components/app-sidebar.tsx` (modify)
No structural change needed — it renders `navGroups(navContext(props))` which reads from `nav-items.tsx`. The new "Perlu Verifikasi" item appears automatically once added to `navGroups`. Document only.

### 7. `resources/js/components/bottom-nav.tsx` (modify)
No structural change — `mobileNavItems(context)` slices `ops` group items (≤4). "Perlu Verifikasi" appears if it fits in the first 4 non-disabled ops items. Document only; no code change unless ordering requires adjustment.

### 8. `resources/js/components/icon-rail.tsx` (modify / note)
No `icon-rail.tsx` exists in the codebase. The collapsed sidebar rail is `SidebarRail` inside `sidebar.tsx`. No change needed — the nav item appears when expanded. Document as N/A.

## B) VerificationDialog Anatomy (top → bottom)

```
┌─────────────────────────────────────┐
│  [X]                                │  ← Dialog.Close (sr-only "Tutup")
│                                     │
│  Budi Santoso                       │  ← karyawan name, text-lg font-semibold text-ink
│  Perbaikan AC kantor lantai 2       │  ← title, text-base font-semibold text-ink
│  [⏰] Perlu verifikasi HOD/TL        │  ← status chip: Clock3 + label, warning-subtle
│  Disubmit 14 Okt 2026, 08:30 WITA   │  ← submitted_at id-ID, text-sm text-ink-muted
│                                     │
│  Catatan:                           │  ← "Catatan" label (text-xs font-medium text-ink-muted)
│  AC mati total sejak pagi,          │  ← proof_notes read-only, text-sm text-ink
│  coba reset breaker tidak berhasil. │
│                                     │
│  Lokasi: Ruang Server Lantai 2      │  ← location + MapPin icon, if present
│                                     │
│  [foto1] [foto2]  +1                │  ← photo grid: thumbnails h-20 w-20,
│                                      │     count badge overlay, click→proxy URL
│                                     │  ← new tab target="_blank" rel="noopener"
│                                     │
│  [Tandai telah selesai] [Kembali]   │  ← Footer: primary + destructive outline
└─────────────────────────────────────┘
```

### Photo inline grid spec
- Each thumbnail: `h-20 w-20 rounded-md object-cover border border-border`
- Grid: `grid grid-cols-2 sm:grid-cols-3 gap-2`
- Count badge: if `proof_attachments.length > 3` (won't happen, max 3) or overlay `+N` on last thumbnail when more than 1
- Click handler: `window.open(dailyWorkAttachment({ tenant, dailyWork: item.id, index: idx }).url, '_blank')` with `rel="noopener"`
- Empty alt: `alt="Bukti foto ${index + 1}"`

### Footer button spec
- "Tandai telah selesai": `variant="default"` (primary), `min-h-11`, disabled when `processing`
- "Kembali": `variant="destructive"` or `variant="outline"` destructive, `min-h-11`, disabled when `processing`

## C) Decision Flow State Machine

```
IDLE (queue displayed)
  │
  ├─► User clicks "Tandai telah selesai" on card/dialog
  │     ▼ ConfirmDialog opens (title: "Tandai telah selesai?",
  │     description: "Bukti kerja ${item.title} oleh ${item.employee.nama_employee}
  │     akan ditandai selesai dan tidak dapat dikembalikan.",
  │     confirmLabel: "Ya, tandai selesai", cancelLabel: "Batal")
  │     ▼ User confirms → verify POST (daily-work.verify)
  │     │   processing=true (both buttons disabled)
  │     │   ├─ 200 → status done, verified_by/at set
  │     │   │   → router.reload({ only: ['verificationQueue'] })
  │     │   │   → toast.success("Daily Work ditandai telah selesai.")
  │     │   │   → dialog closes, focus returns to inbox card list
  │     │   └─ 409 (already decided) → toast.error("Tugas sudah diproses.")
  │     │       → router.reload({ only: ['verificationQueue'] })
  │     │   └─ 403/500 → toast.error("Gagal menyetujui.")
  │     │       → processing=false, buttons re-enabled
  │     ▼ processing=false
  │
  ├─► User clicks "Kembali" on card/dialog
  │     ▼ RejectDialog opens (title: "Kembalikan bukti kerja?",
  │     description: "Tuliskan alasan agar ${item.employee.nama_employee}
  │     dapat memperbaiki bukti ini.",
  │     textarea required *, inline error role="alert" if empty)
  │     ▼ User submits note → reject POST (daily-work.reject)
  │     │   processing=true (submit disabled)
  │     │   ├─ 200 → status in_progress, rejected_by/at + rejection_note set
  │     │   │   → photos RETAINED (proof_attachments unchanged)
  │     │   │   → router.reload({ only: ['verificationQueue'] })
  │     │   │   → toast.success("Bukti dikembalikan kepada karyawan.")
  │     │   │   → dialog closes, focus returns to inbox
  │     │   └─ 422 → inline errors shown, submit stays enabled
  │     │   └─ 500 → toast.error("Gagal mengembalikan bukti.")
  │     ▼ processing=false
  │
  └─► User clicks photo thumbnail
        ▼ dailyWorkAttachment proxy URL opens in new tab (target="_blank")
```

### Double-submit / 409 prevention
- `processing` flag set on `onConfirm`/`onReject` before the POST
- Both footer buttons disabled while `processing`
- If server returns 409 (item already decided by another verifier): show `toast.error("Tugas sudah diproses oleh verifier lain.")`, reload queue, close dialog
- Inertia `router.post` with `onFinish` resets `processing=false`

### Focus return
- After approve/reject completes, dialog closes (`onOpenChange(false)`)
- Focus returns to the verification queue card list (first card or the card at the same position)
- `router.reload({ only: ['verificationQueue'] })` preserves scroll via `preserveScroll: true`

## D) Inbox Page Layout (`verification.tsx`)

```
┌─────────────────────────────────────────────────────────┐
│  Daily Work › Perlu Verifikasi        [badge count]     │  ← breadcrumbs + page title
│  Antrean bukti kerja yang menunggu verifikasi HOD/TL    │  ← description
├─────────────────────────────────────────────────────────┤
│  [Karyawan: ▼ Semua (≤200)]  [Cari tugas…🔍 debounce 300ms]  │  ← filter bar, NO date filter
│                                                         │
│  [Kartu 1]  [Kartu 2]  [Kartu 3]  ...                 │  ← card list, gap-3
│                                                         │
│  [Sebelumnya]  Halaman 1 dari 5  [Berikutnya]         │  ← pagination
│                                                         │
│  [EmptyState: Tidak ada tugas menunggu verifikasi]    │  ← when queue empty
└─────────────────────────────────────────────────────────┘
```

### Filter bar spec
- Employee Select: `SelectTrigger` with `SelectValue` placeholder "Semua karyawan", `SelectContent` with `SelectItem` per employee (≤200 from backend). Label "Karyawan".
- Search Input: `type="search"`, `aria-label="Cari judul tugas"`, placeholder "Cari tugas…", `className="h-11 pl-9"`, debounce 300ms (useEffect timer pattern from `index.tsx`)
- NO date filter — queue is lintas tanggal
- Both filters trigger `router.get(verification({ tenant }), { search, employee_id, page: '1' })` with `preserveState: true`, `preserveScroll: true`, `replace: true`

### Card list at-a-glance hierarchy (per card)
```
┌─────────────────────────────────────────────────┐
│  Budi Santoso                    [⏰] Perlu verifikasi │
│  Perbaikan AC kantor lantai 2                       │
│  ● Rutin  📍 Ruang Server  Disubmit 08:30 WITA      │
│  [foto1] [foto2]  +0                                │
│  [Tandai telah selesai] [Kembali]                   │
└─────────────────────────────────────────────────┘
```
- Karyawan name: `text-base font-semibold text-ink`
- Title: `text-lg font-semibold text-ink` (or `text-base` depending on card density)
- Status chip: shared `StatusChip` from `-components/status.tsx` with `item={{ status: 'pending_verify', verified_by_name: null }}`
- `submitted_at`: `text-sm text-ink-muted`, formatted id-ID ("Disubmit 14 Okt 2026, 08:30 WITA")
- Source badge: `adhoc` → `bg-owner-urgent-subtle text-owner-urgent` + `Sparkles` "Tugas tambahan"; `template` → `bg-info-subtle text-info` + `RefreshCw` "Rutin"
- Location: `MapPin` icon + text, if present
- Photo thumbnails: inline grid (same as dialog), count badge
- Footer actions: "Tandai telah selesai" (primary) + "Kembali" (destructive outline), both `min-h-11`

### Pagination spec
- Prev/Next buttons `variant="outline" size="sm"`, disabled at boundaries
- "Halaman {current_page} dari {last_page}" `aria-live="polite"`
- `aria-label="Paginasi antrean verifikasi"`

### EmptyState spec
- Icon: `CalendarClock` (or `Inbox` if available)
- Title: "Tidak ada tugas menunggu verifikasi"
- Description: "Semua bukti kerja tim sudah diverifikasi atau belum ada yang disubmit."
- No action (no CTA to create — this is a review-only queue)

### Skeleton spec
- When `navigating`: 3 skeleton cards `h-32 w-full rounded-xl`, `aria-busy="true"`
- Reuse `Skeleton` from `@/components/ui/skeleton`

## E) Accessibility Notes (A1–A10)

| ID | Requirement | Implementation |
|----|-------------|----------------|
| A1 | Keyboard-only navigation | All interactive elements reachable via Tab; dialogs trap focus; `Dialog.Close` closes on Escape |
| A2 | Touch targets ≥44px | All buttons `min-h-11` (≥44px); photo thumbnails `h-20 w-20` (32px) — if tappable, wrap in button ≥44px or make the card row tappable |
| A3 | Color never alone | Status always icon + text + color; `StatusChip` from `status.tsx`; badges have icons |
| A4 | Visible focus rings | `focus-visible:ring-ring focus-visible:ring-[3px] focus-visible:ring-offset-1` on all buttons/inputs/selects |
| A5 | Reduced motion | `motion-reduce:transition-none`; dialog animations respect `prefers-reduced-motion` |
| A6 | Contrast 4.5:1 both themes | `ink`/`ink-muted` on `surface`/`surface-raised` meet 4.5:1 in light and dark; `warning-ink` on `warning-subtle` verified |
| A7 | Locale-aware id-ID times | `toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })`; `toLocaleDateString('id-ID')` for dates |
| A8 | Screen reader labels | `aria-label` on icon buttons; `aria-live="polite"` on pagination; `role="alert"` on inline errors; `sr-only` for decorative icons |
| A9 | Required field indicators | `*` on required fields; `aria-required="true"` on reject note textarea |
| A10 | EmptyState descriptive | Title + description describe the empty state; no vague "nothing here" |

## F) Handoff Checklist for Implementer

- [ ] Create `resources/js/pages/daily-work/-components/verification-dialog.tsx` with exact props
- [ ] Create `resources/js/pages/daily-work/-components/reject-dialog.tsx` with exact props
- [ ] Rewrite `resources/js/pages/daily-work/verification.tsx` (replace placeholder)
- [ ] Modify `resources/js/pages/daily-work/team.tsx` — route `pending_verify` to verification page
- [ ] Modify `resources/js/components/nav-items.tsx` — add "Perlu Verifikasi" item (BadgeCheck, tone ops, after Daily Work, gated `daily-work.verify`)
- [ ] Verify `app-sidebar.tsx` and `bottom-nav.tsx` render the new item (no code change if automatic)
- [ ] Import `verification`, `verify`, `reject`, `attachment` routes from `@/routes/daily-work` (Wayfinder --with-form)
- [ ] Use `router.reload({ only: ['verificationQueue'] })` after approve/reject
- [ ] Use `toast.success` / `toast.error` from sonner for all outcomes
- [ ] All buttons `min-h-11`; no raw hex; no emoji icons
- [ ] Indonesian copy throughout
- [ ] `tsc --noEmit` exit 0
- [ ] `npm run build` success
- [ ] Test suite green

---

**Pending your approval.** Once approved, I'll invoke `writing-plans` and execute TDD task-by-task. Corrections welcome before approval.
