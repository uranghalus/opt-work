# Position Pages Implementation Plan
**Per DESIGN.md Master-Data Specs + Impeccable Audit Findings**

---

## 📋 Overview

Transform 4 position pages (`index`, `create`, `show`, `edit`) from generic admin tables to DESIGN.md-compliant master-data surfaces.

**Target State:** Every page follows OptiWorks Operations Workspace design system — Registry Band, Registry Table, responsive card/table hybrid, glass cards, semantic tokens, full accessibility.

---

## 🎯 Page-by-Page Specification

### 1. `positions/index` — Master Data Index

#### Registry Band (P0)
- **Surface:** `panel` stratum, `rounded-xl` (16px), `shadow-raised`, 1px `panel-border` ring
- **Top Rail:** 2px `brand-strong` full width
- **Layout:** `grid-cols-3` at all breakpoints, divided by 1px `panel-border` hairlines
- **Padding:** 14px phone, 20-24px desktop
- **Cells (exactly 3):**
  1. **Total Positions** — metric: 2rem/600/tabular-nums/-0.03em, `panel-ink`; label: 11px/600/uppercase/0.14em tracking, `panel-ink-muted` + 14px Lucide `Briefcase` in `brand-strong`
  2. **Filled Positions** (has employees) — metric same; label "Terisi" + `UserCheck` icon; if 0 → `warning-bright` icon/value, label still names condition
  3. **Optigate Synced** — metric same; label "Optigate Sync" + `RefreshCw` icon

#### Registry Table (P0)
- **Container:** `surface` card, `rounded-lg` (16px), 1px `border`, `shadow-card`
- **Header:** `surface-raised` band, 36-40px tall, 10px uppercase labels, 0.14em tracking, **1px `border-strong` bottom rule**
- **Columns (exactly 4):**

| Column | Spec | Content |
|--------|------|---------|
| **Primary** | `w-[20rem]` | Mono eyebrow: `kode_department` 10px/500/uppercase/0.14em `ink-muted` + "· N posisi" (mid-dot `border-strong`); Name: 15px/600 truncate, link to show page; hover: `brand` rail 2px leading edge + name→`brand` |
| **Owner** | `w-[15rem]` | HOD equivalent: Department avatar (32px) + name 13px/500 + email 11px; if no department → chip `warning-subtle`/`warning-ink`/6px warning dot "Tanpa Department" |
| **Divisi** | `flex-1` | Division names joined " · ", 12.5px `ink-muted`, truncate with `title`; em dash if none |
| **Measure** | `w-[8rem]` right | Count 17px/600/tabular-nums + 64px meter (4px `surface-sunken` track, `brand` fill, width = count/page max, min 4%), `aria-hidden` |

- **Row:** 56-64px, 1px `border` divider, hover wash `surface-raised`, 2px `brand` leading rail on hover/focus
- **Responsive:** `<xl` → card grid (2-col `lg`, 1-col `<lg`) using `PositionCard` component

#### Sticky Filter Bar (P1)
- Search (NIK/name) — `Input` 48px, 20px radius
- Department filter (Select)
- Division filter (Select)
- "Has Employees" filter (Select: all/yes/no)
- Column visibility dropdown (when >4 cols)

#### Skeleton Loading (P1)
- `SkeletonRow` for table (5 rows)
- `Skeleton` cards for mobile card view (3 cards)

#### Empty State (P1)
- Illustrated: `Briefcase` in `brand-soft` circle (size-12)
- Title: "Belum ada position" (body-strong)
- Description: "Posisi menentukan jabatan karyawan dalam cabang. Tambah posisi pertama untuk memulai." (caption, muted)
- CTA: Primary button "Tambah Position" → `create`

#### Pagination Footer (P2)
- Reuse `PaginationFooter` from departments: "X–Y dari Z position", prev/next buttons, page X/Y, `aria-live="polite"`

---

### 2. `positions/create` — Create Form

#### Layout (P0)
- Full-width form in **glass card**: `bg-surface/70` + `border-border` + `backdrop-blur-xl` + `shadow-glass`, `rounded-2xl` (24px)
- Mobile: single column, 16px padding; Desktop: single column, 24px padding, max-w-2xl centered
- Sticky submit action (mobile: bottom bar; desktop: bottom of form)

#### Fields (P0 — Data Integrity)
| Field | Type | Validation | Notes |
|-------|------|------------|-------|
| **Nama Position** | `Input` | required, max:255, unique per tenant | 48px, 20px radius, `surface-raised`, 2px teal focus ring |
| **Department** | `Select` | required, exists:departments | Searchable, loads from `/departments?per_page=100` |
| **Division** | `Select` | required, exists:divisions | Filtered by selected department |
| **Optigate Position ID** | `Input` | optional, max:36, unique per tenant | Mono font, helper text "ID dari Optigate untuk sinkronisasi" |

#### Error Handling (P0)
- **FormErrorSummary** at top on submit error: focusable `role="alert"`, `tabIndex={-1}`, links to each invalid field via `href="#field-id"`
- Inline errors below each field (existing `InputError` component)
- Move focus to summary on submit error
- Validate on blur for all fields (P2)

#### Buttons
- **Primary:** "Simpan Position" — teal gradient, 48px mobile / 40px desktop, 20px radius
- **Secondary:** "Batal" — ghost, links to `index`

#### Success Feedback (P3)
- Toast "Position berhasil dibuat" + redirect to `show` with preserved scroll

---

### 3. `positions/show` — Detail Page

#### Layout (P1 — Full Width, Left-Aligned)
- **NO `max-w-xl` centering** — full content column (1400px max, 32px lg padding)
- **Registry Band** at top (same spec as index, values for this position):
  1. **1** (Total — always 1 for detail)
  2. **Filled** (1 if has employees, 0 if not) → warning-bright if 0
  3. **Optigate Synced** (1 if `optigate_position_id` set, 0 if not)
- **Section Heading Band** (shell-rendered): module chip + h1 (position name) + description (department/division context) + actions (Edit | Delete dropdown)

#### 5/7 Split Content (P1)
```
┌─────────────────────────────────────────────────────────────────────┐
│ LEFT (5/12) — Position Card                          RIGHT (7/12) │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ PositionCard (rounded-2xl, shadow-card, p-6)                   │ │
│ │ - Icon: Briefcase in brand-soft square (48px, rounded-lg)      │ │
│ │ - Title: position.nama_position (heading)                       │ │
│ │ - Badges row: Category (Normal/Accident/Owner) + Status        │ │
│ │ - Meta: Department (badge), Division (badge), Optigate ID (mono)│ │
│ │ - Actions: Edit (primary) | Delete (destructive, confirm)      │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ Employees Holding This Position                                 │ │
│ │ Headcount: count + 64px meter (link to filtered employees)     │ │
│ │ Virtualized list: EmployeeCard (avatar, name, NIK, status)     │ │
│ │ Empty: "Belum ada karyawan di posisi ini" + CTA "Tambah"       │ │
│ └─────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

#### PositionCard Component (Reusable)
- Matches `WoCard` anatomy: icon square → title → badges → meta → divider → actions
- Glass surface: `bg-surface/70` + `border` + `backdrop-blur-xl` + `shadow-glass`

#### Employee List (Right Panel)
- Link `employees_count` → `employees.index?position_id={id}` with `Headcount` meter
- Virtualized list (react-window) for performance
- Each row: avatar (32px), name (body-strong), NIK (mono), status badge
- Click → `employees.show`

---

### 4. `positions/edit` — Edit Form

#### Layout (P0)
- Same glass card as create
- Pre-filled with current values
- **Delete Action** in header actions (destructive, confirm dialog)

#### Fields (P0)
- Same as create, all pre-filled
- Department/Division selects show current selection
- Optigate ID editable

#### Error Handling (P0)
- Same FormErrorSummary + inline validation on blur

#### Buttons
- **Primary:** "Simpan Perubahan" (teal gradient)
- **Secondary:** "Batal" (ghost, links to `show`)
- **Destructive:** "Hapus Position" (danger, confirm dialog) — in header dropdown

---

## 🧩 Reusable Components to Create/Extract

| Component | Source | Location |
|-----------|--------|----------|
| `RegistryBand` | Departments index (exists) | `@/components/registry/RegistryBand.tsx` — **extend for positions** |
| `PositionCard` | New (based on `WoCard`) | `@/components/positions/PositionCard.tsx` |
| `PositionRow` | New (based on `DepartmentRow`) | `@/components/positions/PositionRow.tsx` |
| `PositionCardMobile` | New (for <xl responsive) | `@/components/positions/PositionCardMobile.tsx` |
| `FormErrorSummary` | New | `@/components/forms/FormErrorSummary.tsx` |
| `Headcount` | Departments index (exists) | `@/components/registry/Headcount.tsx` — **reuse** |
| `PaginationFooter` | Departments index (exists) | `@/components/registry/PaginationFooter.tsx` — **reuse** |
| `SkeletonRow` | Employees index (exists) | `@/components/ui/SkeletonRow.tsx` — **reuse** |
| `EmptyState` | New (pattern) | `@/components/ui/EmptyState.tsx` |
| `FilterBar` | Employees index (exists) | `@/components/registry/FilterBar.tsx` — **adapt** |

---

## 🎨 Token Migration Checklist

Replace all shadcn tokens → OptiWorks semantic tokens:

| Current (shadcn) | Target (OptiWorks) |
|------------------|---------------------|
| `text-muted-foreground` | `text-ink-muted` / `text-panel-ink-muted` (dark) |
| `bg-card` | `bg-surface` |
| `border-border` | `border-border` (same) |
| `text-muted-foreground` | `text-ink-muted` |
| `hover:bg-muted/50` | `hover:bg-surface-raised` |
| `focus-visible:ring-ring` | `focus-visible:ring-focus-ring` (teal) |
| `bg-destructive` | `bg-danger` |
| `text-destructive` | `text-danger` |

**Dark mode verification required for every change.**

---

## ♿ Accessibility Checklist (Per Page)

### Index
- [ ] Registry Band: metric values have `tabular-nums`, labels have tracking
- [ ] Table: `scope="col"` on all headers, `aria-sort` on sortable
- [ ] Row: `tabIndex={0}` for keyboard, `brand` rail on focus
- [ ] Filter bar: labels on all selects, search has `aria-label`
- [ ] Pagination: `aria-live="polite"`, buttons have `aria-label`
- [ ] Empty state: CTA has accessible name
- [ ] Skeleton: `aria-busy="true"` on container

### Create/Edit
- [ ] FormErrorSummary: `role="alert"`, `tabIndex={-1}`, focus on submit error
- [ ] Each field: `aria-describedby` → error id, `aria-invalid` on error
- [ ] Required fields: `aria-required="true"`, visual indicator
- [ ] Selects: `aria-label` or associated `<Label>`
- [ ] Buttons: `focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2`
- [ ] Disabled state: `aria-disabled`, visible reason

### Show
- [ ] Registry Band: same as index
- [ ] PositionCard: heading hierarchy (h2), badges have text labels
- [ ] Employee list: each row keyboard accessible, link to show
- [ ] Actions: Edit button, Delete button in dropdown with `aria-expanded`
- [ ] Delete confirm dialog: `role="dialog"`, `aria-modal="true"`, focus trap

---

## 📱 Responsive Breakpoints (Per DESIGN.md)

| Breakpoint | Index | Create/Edit | Show |
|------------|-------|-------------|------|
| `< 768px` (mobile) | 1-col cards | Single column, sticky bottom bar | Stacked (PositionCard above Employees) |
| `768-1023px` (tablet) | 2-col cards | Single column, centered max-w-2xl | 5/7 split (or stacked if narrow) |
| `1024-1279px` (lg) | 2-col cards | Centered max-w-2xl | 5/7 split |
| `≥1280px` (xl) | 4-col table | Centered max-w-2xl | 5/7 split |

---

## 🔧 Backend Changes Required

### PositionController
```php
// store/update validation
'nama_position' => ['required', 'string', 'max:255', Rule::unique('positions')->where('tenant_id', tenant()->id)],
'department_id' => ['required', 'exists:departments,id'],
'division_id' => ['required', 'exists:divisions,id'],
'optigate_position_id' => ['nullable', 'string', 'max:36', Rule::unique('positions')->where('tenant_id', tenant()->id)],

// index: add stats for Registry Band
$stats = [
    'total' => Position::query()->count(),
    'filled' => Position::query()->has('employees')->count(),
    'synced' => Position::query()->whereNotNull('optigate_position_id')->count(),
];

// show: load employees for right panel
$position->load(['department', 'division', 'employees' => fn($q) => $q->with('user')->limit(50)]);
```

### Routes
- No new routes needed (existing resource routes cover all)
- Add filter params to `index`: `?search=&department_id=&division_id=&has_employees=`

---

## 📦 Implementation Sequence

### Phase 1: Foundation (P0)
1. **Create reusable components:** `FormErrorSummary`, `EmptyState`, `PositionCard`, `PositionRow`, `PositionCardMobile`
2. **Migrate tokens** in existing position pages (find/replace shadcn → OptiWorks)
3. **Add RegistryBand to index** with 3 metrics
4. **Rebuild Registry Table** to 4-column spec
5. **Add Department/Division/Optigate fields** to create/edit forms + validation
6. **Add FormErrorSummary** to create/edit

### Phase 2: Responsive & States (P1)
7. **Implement responsive card/table hybrid** (xl table, lg 2-col, <lg 1-col)
8. **Add FilterBar** (search, department, division, has_employees)
9. **Add SkeletonRow** loading states
10. **Add illustrated EmptyState** with CTA
11. **Add PaginationFooter** with aria-live
12. **Redesign Show page** — full width, Registry Band, 5/7 split, PositionCard, Employee list

### Phase 3: Polish (P2-P3)
13. **Inline validation on blur** (create/edit)
14. **Column visibility dropdown** (when >4 cols)
15. **Sortable columns** with `aria-sort` indicators
16. **Toast notifications** for CRUD success
17. **Delete confirm dialog** on show/edit
18. **Dark mode verification** — all pages, all components
19. **Extract components** to `@/components/positions/`

### Phase 4: Final
20. **$impeccable polish** — final quality pass
21. **Re-run audit** — target score ≥14/20 (Good)

---

## ✅ Definition of Done per Page

### Index
- [ ] Registry Band renders with 3 correct metrics
- [ ] Registry Table matches 4-column spec exactly
- [ ] Responsive: cards <xl, table ≥xl
- [ ] Filter bar works (search, department, division, has_employees)
- [ ] Skeleton loading on navigation
- [ ] Empty state illustrated + CTA
- [ ] Pagination footer with aria-live
- [ ] All tokens semantic, dark mode verified
- [ ] Accessibility: scope, aria-sort, focus-visible, keyboard nav

### Create
- [ ] All 4 fields present with correct validation
- [ ] FormErrorSummary on submit error (focusable, linked)
- [ ] Inline validation on blur
- [ ] Glass card styling, 48px inputs, 20px radius
- [ ] Focus rings on all interactives
- [ ] Toast on success
- [ ] Dark mode verified

### Show
- [ ] Full width, left-aligned (no max-w-xl)
- [ ] Registry Band with position-specific metrics
- [ ] 5/7 split: PositionCard | Employee list
- [ ] PositionCard matches WoCard anatomy
- [ ] Employee count links to filtered employees index
- [ ] Header actions: Edit | Delete dropdown
- [ ] Dark mode verified

### Edit
- [ ] All 4 fields pre-filled
- [ ] FormErrorSummary + inline validation
- [ ] Delete in header actions (destructive, confirm)
- [ ] Glass card styling
- [ ] Toast on success
- [ ] Dark mode verified

---

## 🚀 Start Command

Ready to begin **Phase 1**. First task: Create reusable components.

**Shall I start with `FormErrorSummary` component?**