# OptiWorks — Design Brief (Pre-Code)

**Product:** Work Management System (WMS)  
**Source of truth:** `PRD.md` v1.0 Draft  
**Visual reference:** Noteflow-style workspace (see §2)  
**Mode:** Operate (task completion over expression)  
**Stack context:** Laravel + Inertia React + Spatie RBAC + multi-cabang  
**Status:** Design authority for MVP UI — confirm before implementation  
**Language:** UI copy in Bahasa Indonesia

## Assumptions (locked — unchanged)

A1–A6 unchanged. A5 still holds: **light theme only in MVP**; the appearance toggle is present in the top bar for consistency with the reference chrome but is disabled with a reason tooltip in MVP.

## 1. Design Principles — 3 Mandatory Rules

### P1. Status is legible in under 2 seconds without color alone

Unchanged. Every Work Order and Daily Work card still shows **status + category + deadline proximity** as a scannable trio: label text, icon, and color token. The `WoCard` puts category first (top badge), status via column + progress bar color, deadline in the due row.

### P2. Role-shaped surfaces, not one overloaded dashboard

Unchanged in spirit, refined in form: the greeting header, module grid, and CTAs are permission-driven. Budi lands on a board of `Menunggu keputusan` and SLA risk. Sari lands on a stacked card list of today's WO + Daily Work. Requester lands on `WO saya`. The board columns are the same five statuses for everyone; only the visible scope changes.

### P3. Photo-and-proof is a first-class path, not an attachment afterthought

Unchanged. `WoCard` surfaces a camera icon with photo count in the meta row so proof is visible from the board.

## 2. Visual Direction

### Mood

**"Ruang Kerja Operasional" (Operations Workspace)** — the calm, rounded, card-based comfort of a modern task workspace applied to serious operational work. Reference: Noteflow dashboard. Soft shadows, 16px card radius, pill tab navigation, greeting header, module grid, pill-shaped badges, segmented progress bars, avatar stacks. Restrained neutrals + one structural teal + small semantic signal set with paired pastel tints.

### References (craft, not clone)

- Noteflow dashboard: shell (icon rail + top bar with pill tabs), greeting header, module grid, board columns, task card anatomy (priority badge, progress bar, due row, avatar stack, meta counts).
- Facility CMMS / work-order tickets: the domain semantics (job number, status stamps, category badges) that ride inside the Noteflow-shaped shell.

### What to avoid

| Avoid                                                      | Why                                                |
| ---------------------------------------------------------- | -------------------------------------------------- |
| Purple / indigo SaaS gradients, neon dark mode, glow edges | Wrong for facility ops                             |
| Warm cream paper + terracotta + display serif              | Editorial lifestyle default; not industrial        |
| Gamified badges, confetti, playful illustration            | Undermines audit/SLA seriousness                   |
| Oversized metric heroes / advanced BI charts               | PRD defers analytics; home = work queue            |
| Pastel status pills without labels                         | Fails P1 under sun and for color vision deficiency |
| Inter / Roboto / system-ui as brand statement              | Invisible commodity                                |
| Pill-shaped primary CTAs                                   | Consumer-app tell; pills are for badges/dots only  |

### Signature interaction

**Deadline pulse strip** on `WoCard` (3px top rule) and `WoListRow` (3px left rail): shifts token `schedule → warning → danger → escalation` with day-count copy ("Telat H+3 · Team Leader"). Motion: 180–220ms ease-out color crossfade only. Respect `prefers-reduced-motion` (instant token swap).

## 3. Design Tokens

### Color strategy

**Restrained + signal overlay + pastel pair.** Neutrals carry 85% of UI; one structural accent (teal) for primary actions and one neutral high-emphasis (ink) for the top-of-board CTA; semantic signal palette for category/SLA only, each with a paired `*-subtle` tint used for badges and column washes.

### Color palette

| Token                                                  | Hex                      | Role                                              |
| ------------------------------------------------------ | ------------------------ | ------------------------------------------------- |
| `--color-canvas`                                       | `#EEF1F4`                | App background                                    |
| `--color-surface`                                      | `#FFFFFF`                | Cards, panels, sheets                             |
| `--color-surface-raised`                               | `#F7F9FB`                | Nested wells, module icon squares, alternate rows |
| `--color-surface-sunken`                               | `#E8ECF1`                | Board columns, quiet zones                        |
| `--color-ink`                                          | `#15202B`                | Primary text                                      |
| `--color-ink-muted`                                    | `#5B6B7C`                | Secondary labels, meta                            |
| `--color-ink-subtle`                                   | `#8494A7`                | Placeholders, disabled hints                      |
| `--color-border`                                       | `#D5DCE5`                | Card borders, dividers, input borders             |
| `--color-border-strong`                                | `#A8B4C4`                | Focused inactive emphasis                         |
| `--color-brand`                                        | `#0C6B58`                | Primary actions                                   |
| `--color-brand-hover`                                  | `#095445`                | Hover/pressed brand                               |
| `--color-on-brand`                                     | `#FFFFFF`                | Text/icons on brand                               |
| `--color-info` / `--color-info-subtle`                 | `#2A5F8F` / `#E4EDF6`    | Scheduled / planned / informational               |
| `--color-warning` / `--color-warning-subtle`           | `#B86E00` / `#F7ECDA`    | Approaching deadline, In Progress                 |
| `--color-danger` / `--color-danger-subtle`             | `#C0392B` / `#F7E2DF`    | Urgent by Accident, overdue, reject               |
| `--color-escalation` / `--color-escalation-subtle`     | `#7A1F3D` / `#F2E2E8`    | Escalation tier                                   |
| `--color-success` / `--color-success-subtle`           | `#1F7A4C` / `#DFEFE6`    | Closed, verified, Completed                       |
| `--color-owner-urgent` / `--color-owner-urgent-subtle` | `#8A4B12` / `#F3E5D6`    | Urgent Request by Owner                           |
| `--color-focus-ring`                                   | `#0C6B58`                | Keyboard focus (2px + 2px offset)                 |
| `--color-overlay`                                      | `rgba(21, 32, 43, 0.45)` | Modal scrim                                       |

**Category → token mapping (mandatory)**

| WO Category             | Token                                                  | Label (always shown) |
| ----------------------- | ------------------------------------------------------ | -------------------- |
| Normal                  | `--color-info` + `--color-info-subtle`                 | Normal               |
| Urgent by Accident      | `--color-danger` + `--color-danger-subtle`             | Urgent · Kecelakaan  |
| Urgent Request by Owner | `--color-owner-urgent` + `--color-owner-urgent-subtle` | Urgent · Owner       |

**SLA proximity → token (drives DeadlineRail + due row)**

| State                                 | Token                | Example label  |
| ------------------------------------- | -------------------- | -------------- |
| On track                              | `--color-ink-muted`  | On track       |
| ≤1 day to deadline                    | `--color-warning`    | Deadline besok |
| Overdue H+1–2                         | `--color-danger`     | Telat          |
| Escalation H+3 TL / H+5 HOD / H+6 DGM | `--color-escalation` | Eskalasi · HOD |

**Board column → token (drives the 8px status dot in the column header)**

| Column              | Status set       | Dot token         |
| ------------------- | ---------------- | ----------------- |
| Menunggu keputusan  | `waiting_hod`    | `--color-warning` |
| Terjadwal           | `scheduled`      | `--color-info`    |
| On progress         | `in_progress`    | `--color-brand`   |
| Menunggu verifikasi | `pending_verify` | `--color-info`    |
| Selesai             | `closed`         | `--color-success` |

### Typography

Unchanged families: **Source Sans 3** (UI/body/headings) + **Source Code Pro** (identifiers, timestamps, audit). **Do not use:** Inter, Plus Jakarta Sans, Space Grotesk, DM Sans, Playfair, or handwritten faces.

**Type scale** (rem @ 16px root)

| Token         | Size             | Weight | Line height | Use                                |
| ------------- | ---------------- | ------ | ----------- | ---------------------------------- |
| `display`     | 32px / 2rem      | 600    | 1.2         | Greeting header name line          |
| `title`       | 22px / 1.375rem  | 600    | 1.3         | Card titles, modal titles          |
| `heading`     | 18px / 1.125rem  | 600    | 1.35        | Section and column headers         |
| `body`        | 16px / 1rem      | 400    | 1.5         | Forms, descriptions                |
| `body-strong` | 16px / 1rem      | 600    | 1.5         | Card primary label                 |
| `caption`     | 13px / 0.8125rem | 400    | 1.4         | Meta, due rows, subtitles          |
| `micro`       | 12px / 0.75rem   | 600    | 1.3         | Badges, overlines                  |
| `mono`        | 13–14px          | 500    | 1.4         | WO numbers, IDs, counts, due dates |

### Spacing scale (4px base)

`4 · 8 · 12 · 16 · 20 · 24 · 32 · 40 · 48 · 64`

Touch targets: **min 44×44px**; list row min height **56px** mobile; card min height **auto** (content-driven).

### Corner radius

| Token         | Value | Use                                                                 |
| ------------- | ----- | ------------------------------------------------------------------- |
| `radius-xs`   | 4px   | Badges, chips, inputs                                               |
| `radius-sm`   | 8px   | Buttons, small controls                                             |
| `radius-md`   | 12px  | Tab pill container, list containers, module icon squares            |
| `radius-lg`   | 16px  | Cards, kanban columns, panels                                       |
| `radius-xl`   | 20px  | Modals, bottom sheets                                               |
| `radius-full` | 999px | **Badges, dots, avatar rings only.** Forbidden for primary actions. |

### Shadows

| Token            | Value                                                          | Use                         |
| ---------------- | -------------------------------------------------------------- | --------------------------- |
| `shadow-none`    | none                                                           | Rows inside a card          |
| `shadow-card`    | `0 1px 2px rgba(21,32,43,0.04), 0 0 0 1px rgba(21,32,43,0.04)` | Default card                |
| `shadow-raised`  | `0 1px 2px rgba(21,32,43,0.06)`                                | Hover lift, raised controls |
| `shadow-popover` | `0 4px 12px rgba(21,32,43,0.10)`                               | Dropdowns, popovers         |
| `shadow-modal`   | `0 12px 32px rgba(21,32,43,0.16)`                              | Modals, bottom sheets       |

Philosophy: **cards allowed, shadows restrained.** The card halo (border + whisper shadow) is the default surface; heavier shadows are for overlays only.

### Motion

| Token             | Value                          |
| ----------------- | ------------------------------ |
| `duration-fast`   | 120ms                          |
| `duration-base`   | 200ms                          |
| `duration-slow`   | 320ms                          |
| `easing-standard` | `cubic-bezier(0.2, 0.0, 0, 1)` |

Only for: sheet present/dismiss, toast enter, status rail color, card hover lift, board column drop settle, skeleton shimmer. No page parallax.

## 4. Screen Inventory

### Auth & shell

| ID  | Screen                 | Purpose                                                                                 |
| --- | ---------------------- | --------------------------------------------------------------------------------------- |
| S00 | Login                  | Authenticate; land on role home                                                         |
| S01 | App Shell              | Left icon rail + top bar (pill tabs, appearance toggle, bell, avatar); cabang indicator |
| S02 | Notification Center    | Full history, mark read, deep-link to entity                                            |
| S03 | 403 Forbidden          | Permission denied with way home                                                         |
| S04 | Offline Banner / Queue | Persistent when disconnected                                                            |

### Role homes

| ID  | Screen              | Purpose                                                          |
| --- | ------------------- | ---------------------------------------------------------------- |
| H01 | Home · Field (Sari) | Greeting + module grid + stacked `WoCard` list + Daily checklist |
| H02 | Home · HOD (Budi)   | Greeting + module grid + board (default) / list view + SLA risk  |
| H03 | Home · Requester    | Greeting + module grid + `WO saya` list                          |
| H04 | Home · DGM/GM       | Greeting + escalation queue card list                            |
| H05 | Home · Direksi      | Read-only cross-dept SLA snapshot (deferred)                     |
| H06 | Home · Super Admin  | Module grid of RBAC & master data shortcuts                      |

### Work Order

| ID  | Screen                           | Purpose                                             |
| --- | -------------------------------- | --------------------------------------------------- |
| W01 | WO List / Board                  | Filterable queue; Board/List toggle                 |
| W02 | WO Create                        | Multi-step: dept → category → details → attachments |
| W03 | WO Detail                        | Single source of truth in a card                    |
| W04 | HOD Decision (inline/modal)      | Execute now vs schedule                             |
| W05 | Assign Workers                   | Multi-select employees + confirm                    |
| W06 | Schedule WO                      | Pick planning date/range for eligible categories    |
| W07 | Submit Hasil                     | Notes + foto bukti (field)                          |
| W08 | Verify Hasil                     | Approve close / request revision                    |
| W09 | Extend Deadline Request          | Reason + days (max 3)                               |
| W10 | Extend Approval                  | Approve/reject chain                                |
| W11 | Extend Schedule Request/Approval | Separate flow (DGM/GM)                              |

### Daily Work

| ID  | Screen                       | Purpose                                      |
| --- | ---------------------------- | -------------------------------------------- |
| D01 | Daily Work · Today           | Template + HOD extras as a stacked card list |
| D02 | Daily Work · Item Detail     | Status, lokasi, notes                        |
| D03 | Daily Work · Add Extra (HOD) | Assign ad-hoc task                           |

### Work Data

| ID   | Screen           | Purpose                          |
| ---- | ---------------- | -------------------------------- |
| WD01 | Work Data List   | Search/filter closed job records |
| WD02 | Work Data Detail | Append-only history              |

### Admin (Super Admin)

| ID  | Screen               | Purpose                           |
| --- | -------------------- | --------------------------------- |
| A01 | Users & Roles        | Spatie role/permission assignment |
| A02 | Departments / Divisi | Master data                       |
| A03 | Employees            | Link users ↔ karyawan             |

**Out of MVP UI:** Tenant CRUD, Inventory, Surat, advanced analytics, WA/email settings, dark mode.

## 5. User Flows

Unchanged in sequence (A–G). Visual surface updates only:

- **Journey A (Requester creates WO):** FAB → **Buat Work Order**; form in a card; category `RadioCard`s; photo slots first-class.
- **Journey B (HOD triages & assigns):** board column `Menunggu keputusan` → `WoCard` → W03 detail in a card → decision → `AssignPicker`.
- **Journey C (Field executes):** H01 stacked card list → W03 → W07 submit with before/after slots.
- **Journey D (HOD verifies):** H02 `Menunggu verifikasi` column → W08 side-by-side evidence.
- **Journey E (SLA escalation & extend):** overdue `WoCard` shows `Telat H+n · Tier` in the due row; escalation uses the escalation token.
- **Journey F (Daily Work day):** D01 stacked list with segmented progress per item.
- **Journey G (Audit):** WD01 table / WD02 read-only document layout.

## 6. Layout per Screen

### Shared shell (S01)

- **Desktop (≥1024):** 64px floating left icon rail (Beranda, WO, Harian, Data, Admin if permitted; bottom Bantuan, Keluar) + 64px sticky top bar (logo left, pill-tab section nav center, appearance toggle + bell + avatar right). Content max ~1200–1280px.
- **Tablet (768–1023):** rail collapses to hamburger sheet; top bar keeps logo, bell, avatar; board scrolls inside its own region only.
- **Mobile (<768):** bottom nav (Beranda, WO, Harian, Data, Lainnya); top bar keeps title + bell; sticky `+ Buat Work Order` at bottom of content column.

### H01 Home · Field

1. Greeting header
2. (Optional) Info banner
3. Module grid (2 col mobile, 4 col desktop): `WO saya`, `Hari ini`, `Riwayat`
4. **Prioritas hari ini** — overdue/urgent `WoCard` stack
5. **Work Order ditugaskan** — `WoCard` list
6. **Daily Work** — `DailyChecklist` with segmented progress per item
7. Empty modules collapse

### H02 Home · HOD

1. Greeting header
2. Info banner (only if a policy/system notice applies)
3. Module grid: `Perlu tindakan`, `Menunggu verifikasi`, `Extend`, `Tim`
4. **ViewSwitcher** row: `Board` (default) · `List` · `Timeline` · `Kalender`; CTAs right (`+ Buat Work Order` neutral emphasis)
5. **Board**: 5 columns by status; `WoCard` per item
6. **Risiko SLA** strip below the board — compact `WoListRow` items only

### W02 WO Create

Single-column card form; stepper on mobile (4 steps); sticky submit on desktop.

### W03 WO Detail

Card layout: header band (WO number mono + StatusBadge + CategoryBadge + DeadlineRail), action bar (sticky bottom mobile), tabs `Ringkasan | Penugasan | Bukti | Riwayat`, `AuditTimeline` append-only.

### W07 Submit Hasil / W08 Verify

Two-column desktop (form | photo preview); stacked mobile. Before/after explicit. Verify shows side-by-side evidence + explicit decide buttons.

### D01 Daily Work

Date switcher (prev/today/next) + checklist groups `Rutin` | `Tambahan`. Each item is a compact card row: status toggle, title, lokasi, segmented progress.

### WD01 / WD02

Table desktop / card list mobile; detail is a read-only document layout with a photo grid.

### A01 RBAC

Split view desktop: role list | permissions matrix. Mobile: sequential drill-down with "Buka di desktop untuk edit penuh" hint.

## 7. Component Library

### Foundations

`Button`, `IconButton`, `Link`, `Input`, `TextArea`, `Select`, `Checkbox`, `Radio`, `RadioCard`, `Switch`, `Label`, `HelperText`, `InlineError`

**Button variants:** `primary` (brand), `neutral-emphasis` (ink), `secondary` (outline), `ghost`, `danger`, `warning`  
**Button states:** default, hover, active, focus-visible, loading, disabled  
**Sizes:** `sm` 32px, `md` 40px, `lg` 48px (mobile primaries use `lg`)

### Shell

`IconRail`, `TopBar`, `PillTabNav`, `BottomNav`, `AppearanceToggle`, `NotificationBell`, `UserMenu`

### Home composites

`GreetingHeader`, `InfoBanner`, `ModuleGrid`, `ModuleCard`, `ViewSwitcher`

### Feedback

`Toast`, `Banner`, `InlineAlert`, `EmptyState`, `Skeleton`, `Spinner`, `ProgressBar` (segmented and continuous), `UploadProgress`

### Data display

`StatusBadge` — variants: draft, waiting_hod, scheduled, assigned, in_progress, pending_verify, revision, closed, overdue, escalated  
`CategoryBadge` — normal | accident | owner  
`DeadlineRail` — on_track | due_soon | overdue | escalated  
`WoCard` — board/list card with priority badge, title, subtitle, progress, due row, avatar stack, meta counts  
`WoListRow` — dense 48px row for desktop HOD list view  
`KanbanColumn` — column header (dot + label + count + ⋮), body, drop target  
`AvatarStack` — assigned workers (max 4 + `+n`) with ring in `surface`  
`UserChip`, `DeptChip`  
`PhotoThumb` / `PhotoGallery`  
`AuditTimeline`  
`DataTable` + `FilterBar`

### Navigation & overlay

`Modal`, `Drawer` / `BottomSheet`, `DropdownMenu`, `ConfirmDialog`, `NotificationPanel`

### Domain composites

`WoCreateForm`, `AssignPicker`, `SchedulePicker`, `ExtendRequestForm`, `VerifyPanel`, `DailyChecklist`, `PermissionGate` (hide in nav; disable with tooltip on in-page forbidden actions)

### Variant rules

- Destructive actions always `ConfirmDialog`
- Permission-denied controls: hide in nav; on deep link show S03
- Loading buttons lock double-submit on Assign / Submit / Approve
- At most one `neutral-emphasis` button per view (the page's top action)
- Pill shape (`radius-full`) reserved for badges, dots, avatar rings — never buttons

## 8. States — Key Screens

Unchanged matrix. Additions:

- **Board columns** have a per-column empty state: "+ Tidak ada item" caption in `ink-muted`, no illustration.
- **ModuleCard** with count 0 uses `surface-raised` icon square and `ink-subtle` count; card remains clickable if the module has a list view.
- **WoCard** during upload shows an inline `UploadProgress` on the meta row.

## 9. Responsive Behavior

| Breakpoint | Width      | Behavior                                                                                                                                 |
| ---------- | ---------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| Mobile     | <768px     | Bottom nav; sticky `+ Buat WO`; full-width `WoCard` stack (no columns); camera capture preferred; filters in bottom sheet; single column |
| Tablet     | 768–1023px | Rail collapses to hamburger sheet; board scrolls horizontally inside its own region; create form 1 col centered max 640px; verify 50/50  |
| Desktop    | ≥1024px    | Icon rail + top bar; content max ~1200–1280px; WO detail 2-col; board 5 columns; list view uses dense rows                               |

**Field-critical mobile rules** unchanged. **Density:** mobile comfortable (56px rows / card min-height auto); desktop HOD list compact (48px rows).

## 10. Accessibility

Unchanged. Additions:

- `ModuleCard` icon squares: decorative tinted background `aria-hidden`, meaningful icon carries the label.
- `KanbanColumn` uses `role="region"` with `aria-labelledby` pointing to the column header text (dot + count announced as part of the label).
- `ProgressBar` (segmented): `role="progressbar"` with `aria-valuemin=0`, `aria-valuemax={total}`, `aria-valuenow={done}`; visible label "Progress n/m" also present.
- `AvatarStack`: `aria-label="Ditugaskan ke {n} karyawan"`; overflow `+n` announced.
- Board drag-and-drop must have a keyboard fallback: `Move to…` menu on each `WoCard`.

## Selected Direction Summary (for confirmation)

| Dimension    | Decision                                                                              |
| ------------ | ------------------------------------------------------------------------------------- |
| World        | Operations Workspace — Noteflow-style shell + facility ops semantics                  |
| Shell        | 64px icon rail + 64px top bar with pill-tab nav                                       |
| Home thesis  | Greeting → info banner → module grid → view switcher → board/list                     |
| Board        | 5 columns by WO status set; `WoCard` per item                                         |
| Card anatomy | Category badge · title · subtitle · progress · due row · avatar stack · meta counts   |
| Color        | Restrained neutrals + teal action + ink emphasis + semantic signals with pastel pairs |
| Type         | Source Sans 3 + Source Code Pro                                                       |
| Mobile       | Field-first for Sari; HOD complexity defers to desktop                                |
| Signature    | DeadlineRail token on card top rule / row left rail                                   |

## Explicit Anti-Goals (UI)

- No Inventory / Surat / Tenant management screens in MVP
- No WhatsApp/email preference centers until channel confirmed
- No advanced BI charts
- No dark mode in MVP (appearance toggle present but disabled with reason)
- No gamification
- No pill-shaped primary CTAs

## Next step

**Confirm or correct** this brief — especially the shift from "Dispatch Board / no card-soup" to "Operations Workspace / Noteflow-style board + cards". After confirmation, implementation may begin from tokens → shell → board → WO flows; no UI code should precede that confirmation.
