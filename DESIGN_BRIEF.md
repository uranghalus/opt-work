# OptiWorks — Design Brief (Pre-Code)

**Product:** Work Management System (WMS)  
**Source of truth:** `PRD.md` v1.0 Draft  
**Mode:** Operate (task completion over expression)  
**Stack context:** Laravel + Inertia React + Spatie RBAC + multi-cabang  
**Status:** Design authority for MVP UI — confirm before implementation  
**Language:** UI copy in Bahasa Indonesia (labels below use Indonesian primary / English in parentheses where helpful for engineering)

---

## Assumptions (locked for this brief — challenge if wrong)

| # | Assumption | Why |
|---|------------|-----|
| A1 | Any authenticated user can create a Work Order (Requester = capability, not exclusive role) | Matches PRD Open Q1 lean toward “everyone can request help”; reduces role friction |
| A2 | Field worker primary device = phone; HOD/DGM primary = desktop | Sari submits photos on-site; Budi assigns/verifies at desk |
| A3 | Super Admin = RBAC + master data; Direksi = read-only cross-department | PRD Open Q11 most likely split |
| A4 | MVP notifications = in-app only (bell + toast + history) | PRD Non-Goal / Open Q7 |
| A5 | Light theme only for MVP | Outdoor glare + fluorescent offices; dark mode deferred |
| A6 | One cabang session at a time; cabang switcher in chrome if multi-tenancy package exposes it | Tenancy exists; Tenant CRUD is v2 |

Open Questions from PRD that **do not** block this brief but must be resolved before build: deadline day ranges (Q2), extend count limit (Q3), Daily Work template ownership (Q4), Team Leader modeling (Q5), inactive HOD routing (Q6).

---

## 1. Design Principles — 3 Mandatory Rules

### P1. Status is legible in under 2 seconds without color alone
Every Work Order and Daily Work item must show **status + category + deadline proximity** as a scannable trio: label text, icon, and color token. Color never carries meaning alone (color-blind field staff, bright sun washout).

**Rationale:** The PRD’s core failure mode is “lost in chat.” The UI’s job is to make *where this job sits in the pipeline* unmistakable — especially Urgent by Accident vs Normal vs SLA escalation.

### P2. Role-shaped surfaces, not one overloaded dashboard
Navigation, home, and primary CTAs are **permission-driven**. Budi (HOD) lands on inbox + SLA risk. Sari (field) lands on “kerja hari ini” (assigned WO + Daily Work). Requester lands on “WO saya.” No persona sees admin chrome they cannot use.

**Rationale:** Six+ roles share one product. A shared mega-dashboard recreates the visibility problem the PRD is solving — noise instead of action.

### P3. Photo-and-proof is a first-class path, not an attachment afterthought
Create WO, submit hasil, and Work Data closing all treat **image capture + short notes** as the main content path: large tap targets, camera-first on mobile, clear before/after slots, upload failure states designed upfront.

**Rationale:** Field completion and HOD verification both depend on evidence. If photo UX is secondary, adoption collapses back to WhatsApp.

---

## 2. Visual Direction

### Mood
**“Papan Distribusi Kerja” (Dispatch Board)** — the feeling of a facility control board and a job ticket clipboard: cool, precise, industrial-operational. Calm neutrals for long shifts; **signal colors** reserved for urgency, SLA breach, and escalation. Dense enough for 20+ open WOs, never cluttered like a marketing SaaS landing page.

Physical scene forcing light mode: Sari outdoors or in plant corridors under harsh light; Budi under cool office fluorescents. Surfaces must stay high-contrast and non-glossy.

### References (craft, not clone)
- Facility CMMS / work-order tickets (job number prominence, status stamps)
- Airport/ops departure boards (scan density, time urgency without decoration)
- Industrial safety signage vocabulary (category → signal color, always with text)
- Notion/Linear only for *interaction clarity* of lists and filters — not their startup aesthetics

### What to avoid
| Avoid | Why |
|-------|-----|
| Purple / indigo SaaS gradients, neon dark mode, glow edges | Generic AI/SaaS look; wrong for facility ops |
| Warm cream paper + terracotta + display serif | Editorial lifestyle default; not industrial |
| Broadsheet hairlines + dense newspaper columns | Wrong reading pattern for task execution |
| Gamified badges, confetti, playful illustration | Undermines audit/SLA seriousness |
| Card-soup dashboards (stat tiles everywhere) | PRD explicitly defers analytics; home = work queue |
| Soft pastel status pills without labels | Fails P1 under sun and for color vision deficiency |
| Inter / Roboto / system-ui as brand statement | Invisible commodity; no product character |

### Signature interaction
**Deadline pulse strip** on WO rows approaching/past SLA: a thin left rail that shifts token from `schedule` → `warning` → `danger` → `escalation`, with day-count text (“Telat H+3 · Team Leader”). Motion: 180–220ms ease-out color crossfade only; no bounce. Respect `prefers-reduced-motion` (instant token swap).

---

## 3. Design Tokens

### Color strategy
**Restrained + signal overlay:** neutrals carry 85% of UI; one structural accent (teal) for primary actions; semantic signal palette for category/SLA only. Committed color regions are limited to urgency banners and escalation alerts — never decorative washes.

### Color palette

| Token | Hex | Role |
|-------|-----|------|
| `--color-canvas` | `#EEF1F4` | App background — cool concrete gray (not cream `#F4F1EA`) |
| `--color-surface` | `#FFFFFF` | Panels, sheets, list rows |
| `--color-surface-raised` | `#F7F9FB` | Nested wells, table zebra alternate |
| `--color-ink` | `#15202B` | Primary text — blue-slate, softer than pure black outdoors |
| `--color-ink-muted` | `#5B6B7C` | Secondary labels, meta |
| `--color-ink-subtle` | `#8494A7` | Placeholders, disabled hint |
| `--color-border` | `#D5DCE5` | Dividers, input borders |
| `--color-border-strong` | `#A8B4C4` | Focused inactive emphasis |
| `--color-brand` | `#0C6B58` | Primary actions (Assign, Submit, Approve) — deep teal: “execute / clear / go” without hospital-green or startup-blue |
| `--color-brand-hover` | `#095445` | Hover/pressed brand |
| `--color-on-brand` | `#FFFFFF` | Text/icons on brand |
| `--color-info` | `#2A5F8F` | Scheduled / planned / informational |
| `--color-warning` | `#B86E00` | Approaching deadline, needs attention |
| `--color-danger` | `#C0392B` | Urgent by Accident, overdue, reject |
| `--color-escalation` | `#7A1F3D` | DGM/GM escalation tier (wine — distinct from danger red) |
| `--color-success` | `#1F7A4C` | Closed, verified, saved |
| `--color-owner-urgent` | `#8A4B12` | Urgent Request by Owner (amber-brown — distinct from Accident red) |
| `--color-focus-ring` | `#0C6B58` | Keyboard focus (2px + 2px offset) |
| `--color-overlay` | `rgba(21, 32, 43, 0.45)` | Modal scrim |

**Category → token mapping (mandatory)**

| WO Category | Token | Label (always shown) |
|-------------|-------|----------------------|
| Normal | `--color-info` | Normal |
| Urgent by Accident | `--color-danger` | Urgent · Kecelakaan |
| Urgent Request by Owner | `--color-owner-urgent` | Urgent · Owner |

**SLA proximity → token**

| State | Token | Example label |
|-------|-------|---------------|
| On track | `--color-ink-muted` / brand subtle | On track |
| ≤1 day to deadline | `--color-warning` | Deadline besok |
| Overdue H+1–2 | `--color-danger` | Telat |
| Escalation H+3 TL / H+5 HOD / H+6 DGM | `--color-escalation` | Eskalasi · HOD |

### Typography

| Role | Family | Why |
|------|--------|-----|
| UI / body / headings | **Source Sans 3** | Adobe’s UI-oriented humanist sans; excellent Latin for Bahasa Indonesia; slightly more open apertures than Inter → better glance readability outdoors; free, well-hinted, dense-UI friendly |
| Identifiers / timestamps / audit | **Source Code Pro** | Tabular-friendly monospace sibling; WO numbers (`WO-2026-00412`), deadlines, and audit lines scan as data, not prose |

**Do not use:** Inter, Plus Jakarta Sans, Space Grotesk, DM Sans, Playfair, or handwritten faces — wrong register for ops + listed as commodity AI defaults.

**Type scale** (rem @ 16px root)

| Token | Size | Weight | Line height | Use |
|-------|------|--------|-------------|-----|
| `display` | 28px / 1.75rem | 600 | 1.25 | Screen title (desktop) |
| `title` | 22px / 1.375rem | 600 | 1.3 | Mobile screen title, modal title |
| `heading` | 18px / 1.125rem | 600 | 1.35 | Section headers |
| `body` | 16px / 1rem | 400 | 1.5 | Forms, descriptions |
| `body-strong` | 16px / 1rem | 600 | 1.5 | Row primary label |
| `caption` | 13px / 0.8125rem | 400 | 1.4 | Meta, timestamps |
| `micro` | 12px / 0.75rem | 600 | 1.3 | Badges, overlines (min 12px — never smaller for meaning) |
| `mono` | 13–14px | 500 | 1.4 | WO numbers, IDs |

### Spacing scale (4px base)

`4 · 8 · 12 · 16 · 20 · 24 · 32 · 40 · 48 · 64`

| Token | Value | Typical use |
|-------|-------|-------------|
| `space-1` | 4px | Icon gaps, badge padding y |
| `space-2` | 8px | Compact stack |
| `space-3` | 12px | Input internal |
| `space-4` | 16px | Default component gap |
| `space-5` | 20px | Card/section padding mobile |
| `space-6` | 24px | Section padding desktop |
| `space-8` | 32px | Between major blocks |
| `space-10` | 40px | Page top under header |
| `space-12` | 48px | Rare spacious break |
| `space-16` | 64px | Empty-state vertical |

Touch targets: **min 44×44px**; list row min height **56px** mobile.

### Corner radius

| Token | Value | Use |
|-------|-------|-----|
| `radius-sm` | 4px | Badges, chips, inputs |
| `radius-md` | 8px | Buttons, list containers, sheets |
| `radius-lg` | 12px | Modals, bottom sheets |
| `radius-full` | 999px | **Forbidden for primary actions** — reserved only for notification dot |

Rationale: Soft-enough for modern UI, hard enough to feel industrial/ticket-like. Avoid pill CTAs (consumer app tell).

### Shadows

| Token | Value | Use |
|-------|-------|-----|
| `shadow-none` | none | Default lists (prefer borders over shadow) |
| `shadow-sm` | `0 1px 2px rgba(21,32,43,0.06)` | Raised buttons subtle |
| `shadow-md` | `0 4px 12px rgba(21,32,43,0.10)` | Dropdowns, popovers |
| `shadow-lg` | `0 12px 32px rgba(21,32,43,0.16)` | Modals |

Philosophy: **borders first, shadows sparingly.** Ops UIs that over-shadow feel like consumer cards; tickets sit on a board, they don’t float.

### Motion

| Token | Value |
|-------|-------|
| `duration-fast` | 120ms |
| `duration-base` | 200ms |
| `duration-slow` | 320ms |
| `easing-standard` | `cubic-bezier(0.2, 0.0, 0, 1)` |

Only for: sheet present/dismiss, toast enter, status rail color, skeleton shimmer. No page parallax.

---

## 4. Screen Inventory

### Auth & shell
| ID | Screen | Purpose |
|----|--------|---------|
| S00 | Login | Authenticate; land on role home |
| S01 | App Shell | Sidebar (desktop) / bottom nav (mobile); cabang indicator; bell; profile |
| S02 | Notification Center | Full history, mark read, deep-link to entity |
| S03 | 403 Forbidden | Permission denied with way home |
| S04 | Offline Banner / Queue | Persistent when disconnected |

### Role homes
| ID | Screen | Purpose |
|----|--------|---------|
| H01 | Home · Field (Sari) | Today’s WO assignments + Daily Work checklist |
| H02 | Home · HOD (Budi) | Inbox WO baru, SLA risk, pending verifikasi, extend requests |
| H03 | Home · Requester | WO saya — status tracking |
| H04 | Home · DGM/GM | Escalation queue + schedule-extend approvals |
| H05 | Home · Direksi | Read-only cross-dept SLA snapshot (minimal; not analytics suite) |
| H06 | Home · Super Admin | Shortcuts to RBAC & master data |

### Work Order
| ID | Screen | Purpose |
|----|--------|---------|
| W01 | WO List | Filterable queue (role-scoped) |
| W02 | WO Create | Multi-step: dept → category → details → attachments |
| W03 | WO Detail | Single source of truth: status, people, deadline, timeline, actions |
| W04 | HOD Decision (inline/modal) | Execute now vs schedule (category rules) |
| W05 | Assign Workers | Multi-select employees + confirm |
| W06 | Schedule WO | Pick planning date/range for eligible categories |
| W07 | Submit Hasil | Notes + foto bukti (field) |
| W08 | Verify Hasil | Approve close / request revision (HOD) |
| W09 | Extend Deadline Request | Reason + days (max 3) |
| W10 | Extend Approval | Approve/reject chain |
| W11 | Extend Schedule Request/Approval | Separate flow for work schedule (DGM/GM) |

### Daily Work
| ID | Screen | Purpose |
|----|--------|---------|
| D01 | Daily Work · Today | Template + HOD extras for selected date |
| D02 | Daily Work · Item Detail | Status, lokasi, notes |
| D03 | Daily Work · Add Extra (HOD) | Assign ad-hoc task to employee + date |

### Work Data
| ID | Screen | Purpose |
|----|--------|---------|
| WD01 | Work Data List | Search/filter closed job records |
| WD02 | Work Data Detail | Append-only histori (before/after, kesimpulan, etc.) |

### Admin (Super Admin)
| ID | Screen | Purpose |
|----|--------|---------|
| A01 | Users & Roles | Spatie role/permission assignment |
| A02 | Departments / Divisi | Master data (minimal MVP) |
| A03 | Employees | Link users ↔ karyawan (minimal MVP) |

**Out of MVP UI:** Tenant CRUD, Inventory, Surat, advanced analytics, WA/email settings.

---

## 5. User Flows

### Journey A — Requester creates WO (lintas department)
1. Home H03 or FAB → **Buat Work Order**
2. Pilih department tujuan (required)
3. Pilih kategori → UI branches:
   - Urgent by Accident → hide schedule options forever (FR-1.2)
   - Normal / Owner Urgent → HOD will decide later
4. Isi jenis, prioritas, personel, deskripsi, lampiran gambar
5. Submit → toast sukses + notifikasi ke HOD tujuan
6. Land on W03 (status: menunggu keputusan HOD)

**Primary action:** Kirim Work Order  
**Hierarchy:** Category decision first (affects all downstream), then destination, then evidence/details.

### Journey B — HOD triages & assigns
1. Bell / H02 inbox → W03
2. Decide: **Eksekusi langsung** | **Jadwalkan** (hidden if Accident)
3. If schedule → W06 set tanggal → confirm
4. W05 assign ≥1 karyawan → notifikasi ke assignee
5. Status → ditugaskan / terjadwal

**Primary action:** Assign karyawan (after path decision)  
**Secondary:** Minta info / reject hanya jika product later adds — MVP focuses assign path.

### Journey C — Field executes & submits
1. H01 sees WO in “Ditugaskan ke saya”
2. Open W03 → Start / update progress (status on progress)
3. W07 submit catatan + foto → status menunggu verifikasi
4. If revisi → W03 shows catatan HOD; resubmit without deadline reset (FR-1.8)

**Primary action:** Submit hasil  
**Hierarchy:** What to do → where → proof.

### Journey D — HOD verifies & closes
1. H02 “Menunggu verifikasi” → W08
2. Review photos/notes/timeline
3. Approve → closed + Work Data record generated (FR-3.1)  
   OR Reject + catatan revisi → back to field

**Primary action:** Setujui & tutup

### Journey E — SLA escalation & extend
1. System marks overdue; notifications at H+3 / H+5 / H+6
2. Queue surfaces on H02 / H04 with escalation badge
3. Authorized role opens W09 → alasan + ≤3 hari
4. Approval chain W10 (TL→HOD or HOD-only)
5. Schedule extend is **separate** journey via W11 (DGM/GM) — never mix copy/UI with deadline extend

### Journey F — Daily Work day
1. H01 / D01 default = hari ini
2. Check off / set proses / selesai + lokasi
3. HOD may D03 add extra task → appears on employee D01 + notification

### Journey G — Audit / Work Data
1. WD01 filter dept + date range
2. WD02 read-only detail — no edit after close

---

## 6. Layout per Screen & Components

### Shared shell (S01)
- **Desktop (≥1024):** Left nav 240px — Home, Work Order, Daily Work, Work Data, Admin (if permitted); top bar: cabang, search WO no., bell, avatar
- **Tablet (768–1023):** Collapsible nav rail icons + labels on expand
- **Mobile (<768):** Bottom nav ≤5: Beranda, WO, Harian, Data, Lainnya; top: title + bell; FAB for Buat WO when permitted

### H01 Home · Field
**Sections (top→bottom):**
1. Header: tanggal + greeting short
2. **Prioritas hari ini** — overdue/urgent WO stack (max emphasis)
3. **Work Order ditugaskan** — `WoListRow` list
4. **Daily Work** — `DailyChecklist`
5. Empty modules collapse (don’t show hollow cards)

**Components:** `PageHeader`, `SectionLabel`, `WoListRow`, `StatusBadge`, `DeadlineRail`, `DailyChecklist`, `EmptyState`

### H02 Home · HOD
**Sections:**
1. **Perlu tindakan** actionable queue (new WO, verify, extend) — primary
2. **Risiko SLA** overdue / approaching
3. **Ringkasan tim** light counts only (3–4 metrics max — not analytics)
4. Quick: Buat tugas harian tambahan

**Components:** `ActionQueue`, `WoListRow`, `StatStrip` (minimal), `Button`

### W02 WO Create
**Layout:** Single-column form, stepper on mobile (4 steps), one long form on desktop with sticky submit.
1. Department + Category (gated fields follow)
2. Detail pekerjaan
3. Prioritas + personel
4. Lampiran
**Components:** `Stepper`, `Select`, `RadioCard` (category), `TextArea`, `FileDropzone` / `CameraCapture`, `FormActions`

### W03 WO Detail
**Layout:**
- **Header band:** WO number (mono) + `StatusBadge` + category + `DeadlineRail`
- **Action bar:** role-conditional primary/secondary (sticky bottom mobile)
- **Tabs or stacked sections:** Ringkasan | Penugasan | Bukti | Riwayat
- **Timeline:** `AuditTimeline` append-only

**Components:** `EntityHeader`, `ActionBar`, `DefinitionList`, `UserChip`, `PhotoGallery`, `AuditTimeline`, `ConfirmDialog`

### W07 Submit Hasil / W08 Verify
Two-column desktop (form | photo preview); stacked mobile. Before/after slots explicit. Verify shows side-by-side evidence + decide buttons.

### D01 Daily Work
Date switcher (prev/today/next) + checklist groups: Rutin | Tambahan. Each row: checkbox/status select, title, lokasi.

### WD01 / WD02
Table desktop / card list mobile; detail is read-only document layout with photo grid.

### A01 RBAC
Split view desktop: role list | permissions matrix. Mobile: sequential drill-down.

---

## 7. Component Library

### Foundations
`Button`, `IconButton`, `Link`, `Input`, `TextArea`, `Select`, `Checkbox`, `Radio`, `RadioCard`, `Switch`, `Label`, `HelperText`, `InlineError`

**Button variants:** `primary` (brand), `secondary` (outline), `ghost`, `danger`, `warning`  
**Button states:** default, hover, active, focus-visible, loading (spinner + disabled), disabled  
**Sizes:** `sm` 32px, `md` 40px, `lg` 48px (mobile primaries use `lg`)

### Feedback
`Toast`, `Banner`, `InlineAlert`, `EmptyState`, `Skeleton`, `Spinner`, `ProgressBar` (upload)

### Data display
`StatusBadge` — variants: draft, waiting_hod, scheduled, assigned, in_progress, pending_verify, revision, closed, overdue, escalated  
`CategoryBadge` — normal | accident | owner  
`DeadlineRail` — on_track | due_soon | overdue | escalated  
`WoListRow` — densified row with rail + title + meta + badges  
`UserChip`, `DeptChip`  
`PhotoThumb` / `PhotoGallery`  
`AuditTimeline`  
`StatStrip` (max 4)  
`DataTable` + `FilterBar`

### Navigation & overlay
`AppSidebar`, `BottomNav`, `TopBar`, `Tabs`, `Breadcrumb` (desktop detail only)  
`Modal`, `Drawer` / `BottomSheet`, `DropdownMenu`, `ConfirmDialog`  
`NotificationBell`, `NotificationPanel`

### Domain composites
`WoCreateForm`, `AssignPicker` (search + multi-select employees), `SchedulePicker`, `ExtendRequestForm`, `VerifyPanel`, `DailyChecklist`, `PermissionGate` (hide vs disable — prefer hide nav; disable with tooltip on in-page forbidden actions)

### Variant rules
- Destructive actions always `ConfirmDialog`
- Permission-denied controls: hide in nav; on deep link show S03
- Loading buttons lock double-submit on Assign / Submit / Approve

---

## 8. States — Key Screens

| Screen | Empty | Loading | Error | Success | Offline |
|--------|-------|---------|-------|---------|---------|
| H01 Field Home | Illustration-free empty: “Belum ada tugas hari ini” + secondary hint | Skeleton rows (3+3) | InlineAlert + Retry | Toast on status update | Top `Banner` sticky; read cache; queue mutations |
| H02 HOD Home | “Tidak ada yang perlu diproses” (positive empty) | Skeleton action queue | Retry per section | Toast after assign/verify | Same banner; disable assign |
| W01 WO List | Empty + CTA Buat WO if allowed | Table skeleton | Full-page error + retry | — | Cached list + banner |
| W02 Create | — | Submit button loading | Field errors + toast on 500 | Toast + navigate W03 | Block submit; save draft local **optional MVP stretch** — if not built, clear message “Perlu koneksi untuk mengirim” |
| W03 Detail | — | Header+body skeleton | Error with back | Toast on action | Read-only cached; actions disabled |
| W07 Submit | Empty photo slots with dashed capture CTA | Upload progress per file | Size/type validation; retry failed uploads | Toast; status → pending verify | Queue upload; show “Menunggu koneksi” |
| W08 Verify | — | — | Conflict if already closed | Toast + closed | Disable approve |
| D01 Daily | “Template harian belum diatur” (HOD CTA if permitted) | Checklist skeleton | Retry | Row success check animation (reduced-motion: instant) | Local toggle queue |
| WD01 | No results for filters (distinguish from global empty) | Table skeleton | Retry | — | Cached |
| S02 Notifications | “Belum ada notifikasi” | List skeleton | Retry | Mark-read optimistic | Show stored; realtime paused label |
| S00 Login | — | Button loading | Inline credentials error | Redirect home | Form allowed; show connection error on submit fail |

**Global offline pattern:** persistent `Banner` under TopBar: “Anda offline. Perubahan akan dikirim saat online.” Mutation buttons that cannot queue → disabled + reason.

---

## 9. Responsive Behavior

| Breakpoint | Width | Behavior |
|------------|-------|----------|
| Mobile | &lt;768px | Bottom nav; sticky ActionBar; full-width rows; camera capture preferred; filters in bottom sheet; single column |
| Tablet | 768–1023px | Rail nav; lists remain full width; create form 1 col centered max 640px; verify can split 50/50 |
| Desktop | ≥1024px | Sidebar; content max ~1200px; WO detail 2-col (main + side meta); tables for WO/Work Data; hover affordances OK but never hover-only |

**Field-critical mobile rules**
- Primary CTA thumb-zone (bottom sticky)
- Photo capture uses `capture="environment"` where supported
- No horizontal scroll; badges wrap
- HOD-heavy screens (RBAC matrix, wide tables) may show “Buka di desktop untuk edit penuh” for complex admin on phone — acceptable for Super Admin only

**Density**
- Mobile: comfortable (56px rows)
- Desktop HOD queues: compact (48px) to process volume

---

## 10. Accessibility

### Contrast (WCAG 2.2 AA minimum)
| Pair | Requirement | Token check |
|------|-------------|-------------|
| Body text on canvas/surface | ≥4.5:1 | `ink` `#15202B` on `#FFFFFF` / `#EEF1F4` ✓ |
| Muted text | ≥4.5:1 for any required reading | Avoid `ink-subtle` for critical status |
| Brand button | ≥4.5:1 | White on `#0C6B58` ✓ |
| Danger / warning badges | Text+icon; if light tint backgrounds, use dark text variants | Provide `*-subtle` bg + dark fg pairs in implementation |
| Focus ring | 3:1 against adjacent colors | Teal ring on white/gray |

Never rely on red vs green alone for pass/fail — always include text (“Ditolak”, “Disetujui”).

### Focus order
1. Skip link → Main content  
2. TopBar (cabang → search → bell → profile)  
3. Nav (sidebar or bottom — bottom nav is landmark `navigation`)  
4. Page header / filters  
5. Primary list / form fields in visual order  
6. Sticky ActionBar controls last in DOM but `position` fixed — keep them in tab order after main actions via DOM placement at end of `<main>`  

Modals: focus trap; initial focus on title or first field; Escape closes; restore focus to opener.

### Keyboard
| Control | Keys |
|---------|------|
| Global | `/` focuses WO search (desktop) |
| Lists | `j`/`k` optional stretch — otherwise Tab + Enter |
| Checkbox Daily Work | Space toggles |
| Dialog | Esc closes; Enter submits focused primary if type=submit |
| Menu | Arrows + Esc |

All icon-only buttons: accessible name (`aria-label`).

### ARIA requirements
- `StatusBadge`: text node required; `aria-label` includes full phrase e.g. “Status: Menunggu verifikasi”
- Live regions: `aria-live="polite"` on Toast; `assertive` only for SLA breach banners newly appeared
- Notification bell: `aria-label="Notifikasi, {n} belum dibaca"`
- Stepper: `aria-current="step"`
- Tabs: `tablist` / `tab` / `tabpanel` pattern
- Progress uploads: `role="progressbar"` valuemin/valuemax/valuenow
- Tables: `<th scope>` ; sortable columns announce state
- Permission errors: page `h1` “Akses ditolak”
- Images: alt describing bukti (“Foto sesudah perbaikan panel listrik”); decorative icons `aria-hidden`

### Other
- `prefers-reduced-motion`: disable shimmer/pulse; instant status changes
- Hit area ≥44px
- Form errors: `aria-invalid` + `aria-describedby` pointing to error id; focus first invalid on submit
- Bahasa Indonesia: consistent terminology (Work Order kept as “Work Order” or “WO” — pick one product term; **recommend “Work Order” full + “WO” in dense lists**)

---

## Selected Direction Summary (for confirmation)

| Dimension | Decision |
|-----------|----------|
| World | Dispatch Board — cool industrial ops, signal urgency |
| Color | Restrained neutrals + teal action + semantic hazard/SLA palette |
| Type | Source Sans 3 + Source Code Pro |
| Home thesis | Role-shaped work queue, not analytics dashboard |
| Mobile | Field-first for Sari; admin complexity may defer to desktop |
| Signature | DeadlineRail status strip on WO rows |

---

## Explicit Anti-Goals (UI)

- No Inventory / Surat / Tenant management screens in MVP  
- No WhatsApp/email preference centers until channel confirmed  
- No advanced BI charts  
- No dark mode in MVP  
- No gamification  

---

## Next step

**Confirm or correct** this brief (especially Assumptions A1–A6 and the Dispatch Board direction). After confirmation, implementation may begin from tokens → shell → WO flows; no UI code should precede that confirmation.
