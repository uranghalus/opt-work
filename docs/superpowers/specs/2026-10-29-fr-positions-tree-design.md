---
created: 2026-10-29
tags: [positions, tree, hierarchy, design-system, ui-spec, frontend, positions-tree]
status: pending-approval
---

# Design Spec — FR-Positions-Tree: Strukture Jabatan Position Hierarchy Tree

## Intent & Why

The "Struktur Jabatan" position hierarchy tree page (`resources/js/pages/positions/tree.tsx`) is orphaned/broken: `Expand All`/`Collapse All` handlers are empty stubs, loading is hardcoded `false`, there are no loading/empty/error states, and detail navigation is hidden behind a hover-only icon instead of being clickable on the position name. A duplicate flat-table view exists at `resources/js/pages/positions/tree/index.tsx` that must be removed.

This spec rewrites `tree.tsx` as a functional, accessible position hierarchy tree and removes the duplicate `tree/index.tsx`. The tree lets users visualize organizational position hierarchy, expand/collapse branches, search while preserving parent path context, and click into position detail pages.

The backend endpoint (`tree` route via Wayfinder) already returns `rootPositions`; this spec covers only the React frontend.

## Goals

1. Working expand/collapse tree: click disclosure to toggle children; row click navigates to `positions/show/{position}`.
2. `Expand All` / `Collapse All` toolbar buttons functional, disabled when searching or empty.
3. Search preserves parent path and auto-expands matches (existing `filterTree` logic kept).
4. Vacancy status visually prominent (icon + text + color badge), not just a small label.
5. Loading, empty, and error states rendered (not hardcoded `loading=false`).
6. Path indicator showing user's current position in the tree (orientation aid from UX research).
7. Persistent detail affordance: position name is clickable, not just an icon-only link.
8. WCAG AA: keyboard-only, touch ≥44px, focus rings, 4.5:1 contrast both themes, color never alone, id-ID locale times, screen-reader labels, descriptive EmptyState.
9. `tsc --noEmit` exit 0, `npm run build` success.

## Non-Goals

- ❌ Create/edit/delete positions from this page (read-only navigation).
- ❌ Drag-and-drop reordering of hierarchy.
- ❌ New icons or custom illustrations — `lucide-react` only.
- ❌ Date filter on toolbar.
- ❌ Tree export / print / image download.
- ❌ Inline edit of position fields.

## Constraints

- Design tokens only — NO raw hex values in components. Use CSS variables from `resources/css/app.css`.
- No emoji icons — `lucide-react` only.
- Touch target ≥44px (`min-h-11`) on every interactive element.
- Light + dark theme support via CSS variables.
- Indonesian copy throughout.
- Per-component file under `resources/js/pages/positions/tree.tsx`.
- Wayfinder route helpers (`tree`, `show as positionsShow`).
- `tsc --noEmit` exit 0, `npm run build` success.
- Responsive breakpoints: 375px, 768px, 1024px, 1440px.

## Design System Token Reference (from `resources/css/app.css`)

| Token | Light | Dark | Usage |
|-------|-------|------|-------|
| `--background` | #F2F4F7 | #121212 | page canvas |
| `--surface` | #FFFFFF | #1E1E1E | card surface |
| `--surface-raised` | #F6F8FA | #252525 | raised surfaces |
| `--ink` | #101418 | #FFFFFF | primary text |
| `--ink-muted` | #5B6470 | #98989D | secondary text |
| `--ink-subtle` | #7A8694 | #7A7A80 | tertiary text |
| `--border` / `--border-border` | #D8DEE6 | #2C2C2E | borders |
| `--shadow-card` | soft | soft-dark | card elevation |
| `--focus-ring` | #007C91 | #00E5FF | focus ring |
| `--radius-sm` | 0.25rem | same | badges, chips |
| `--radius-md` | 0.5rem | same | buttons |
| `--radius-xl` | 1rem | same | cards, containers |
| `--info` / `--info-subtle` | info family | info family | in_progress status |
| `--warning` / `--warning-subtle` / `--warning-ink` | warning family | warning family | pending_verify status |
| `--success` / `--success-subtle` | success family | success family | done status |
| `--destructive` | #D70015 | #FF453A | error states |

Fonts: Outfit (headings), Work Sans (body/UI). Motion: 200–300ms `ease-standard`.

## Status Color Token Mapping (per status)

Position vacancy/status in the tree:

| Status | Label | Icon | Badge bg | Badge text | Border | Token basis |
|--------|-------|------|----------|------------|--------|-------------|
| `pending_verify` | Perlu verifikasi | `Clock3` | `bg-warning-subtle` | `text-warning-ink` | `border-warning/30` | warning family |
| `done` | Terisi | `CheckCircle2` | `bg-success-subtle` | `text-success` | `border-success` | success family |
| `in_progress` | Proses | `CircleDot` | `bg-info-subtle` | `text-info` | `border-info/30` | info family |

Vacant positions (no employees): `bg-danger-subtle text-danger border-danger/30`, label "Kosong", `Users` icon with count 0. Filled: `Users` icon + "{n} terisi", muted text.

## A) Component File List

### 1. `resources/js/pages/positions/tree.tsx` (rewrite — keep path)

**Responsibility:** The Struktur Jabatan page. Renders breadcrumb, sticky toolbar (search + Expand All / Collapse All / Refresh), the tree, path indicator bar, and empty/loading/error states. Hosts the recursive `NodeRow` component internally.

**Props:** None (reads `rootPositions`, `initialRoot` from Inertia page props; `tenant` via `useRequiredTenant`).

**Exact interface:**

```tsx
type DepartmentRef = {
    id: string;
    kode_department: string;
    nama_department: string | null;
};

type DivisionRef = {
    id: string;
    nama_division: string;
};

type PositionNode = {
    id: string;
    nama_position: string;
    level: number | null;
    level_label: string | null;
    is_direct: boolean;
    is_vacant: boolean;
    employees_count: number;
    department?: DepartmentRef | null;
    division?: DivisionRef | null;
    children: PositionNode[];
};

type PageProps = {
    rootPositions: PositionNode[];
    initialRoot?: string | null;
};
```

**Layout responsibility:**
- `Head title="Struktur Jabatan"`, layout breadcrumbs `[{title:'Data Master'}, {title:'Position'}, {title:'Struktur Jabatan'}]`
- Sticky toolbar: search Input (debounce 300ms) + Expand All button + Collapse All button + Refresh button
- Path indicator bar: when `initialRoot` set, shows "Menampilkan struktur dari {position}" with a "Tampilkan seluruh struktur" close button
- Tree: recursive `ul[role="tree"]` with `NodeRow` children
- EmptyState: "Belum ada struktur jabatan" (no positions)
- EmptyState: "Tidak ada jabatan '{query}'" (search no match)
- Skeleton loading states
- Error state: flash error message + retry via Refresh button

**Sub-component: `NodeRow`** (internal, not exported)
```tsx
interface NodeRowProps {
    node: PositionNode;
    depth: number;
    expanded: Set<string>;
    searching: boolean;
    tenant: string;
    onToggle: (id: string) => void;
    onNavigate: (id: string) => void;
}
```
Anatomy: disclosure button (≥44px) + position name link + vacancy badge + department/division meta + children `ul` when expanded.

### 2. `resources/js/pages/positions/tree/index.tsx` (REMOVE — delete file)

**Reason:** Duplicate flat-table view. It renders a non-hierarchical table, has empty `onExpandAll`/`onCollapseAll` handlers (`() => {}`), hardcoded `loading={false}`, and conflicts with `tree.tsx` (both claim the "tree" route responsibility).

**Action:** `git rm resources/js/pages/positions/tree/index.tsx`. Any route reference to `tree` from this index must point to `tree.tsx` instead.

## B) Tree Node Anatomy

### At-glance state (collapsed row)

```
┌ Disclosure [▶]  Nama Posisi    [Level L2]  [Kosong ▼]  Dept.Kode · Divisi   [ExternalLink]
│                                                       └── clickable → positions/show/{id}
└ children (indented, vertical guide line)  — visible only when expanded
```

**Glance fields (left → right):**
1. Disclosure button: `ChevronRight`/`ChevronDown` + optional spacer dot for leaf nodes. `aria-expanded`, `aria-label`, ≥44px touch target.
2. Position name: `text-sm font-medium text-ink` — **clickable link** to `positions/show/{position}` (persistent affordance, not hover-only).
3. Level badge: optional outline badge showing `level_label` if present.
4. Vacancy badge: prominent — icon + text + color. "Kosong" if `is_vacant`/`employees_count===0`; "{n} terisi" if filled.
5. Meta line (small): `kode_department · nama_department` + `· nama_division` if present, or "Tanpa department".
6. External link icon: clickable, `aria-label="Buka detail {nama_position}"`, visible on focus/hover (not hidden by opacity).

### Detail page (positions/show/{position})

Navigates away from tree. Shows full position detail: hero header, category chip, department/division, employee count, tabs (Overview/Karyawan/Hierarki/Detail Teknis), parent position card, children cards, "Lihat Pohon Hierarki Lengkap" link back to tree with `initialRoot` set.

### Path indicator

When deep-linked from a detail page (`initialRoot`), a banner above the tree reads: "Menampilkan struktur dari {position}" with a close button to reset to full tree. This prevents users losing orientation.

## C) Interaction Model

### Expand / Collapse

- Click disclosure icon (≥44px touch target) toggles children of that node.
- `Enter`/`Space` on disclosure button toggles (keyboard).
- `Tab` moves through tree items in DOM order.
- Focus stays on disclosure button after toggle.
- Disclosure disabled while searching (all match paths auto-expanded).

### Detail navigation

- Clicking position name (or external link icon) navigates to `positions/show/{position}` via `router.visit`, NOT toggle.
- Disclosure click and name click are separate hit targets — no conflict.
- After detail navigation + browser back, focus returns to the tree node that was clicked.

### Expand All / Collapse All

- Toolbar buttons. `Expand All`: sets `expanded` to all node IDs. `Collapse All`: sets `expanded` to root node IDs only.
- Disabled when `searching` (search auto-expands matches) or `isEmpty`.
- Both buttons functional — no empty stubs.

### Search

- Input `type="search"`, debounce 300ms (useEffect timer pattern from `positions/index.tsx`).
- `filterTree` keeps full parent path to matches; matches auto-expanded (`effectiveExpanded` = all collected IDs while searching).
- Search no match → EmptyState "Tidak ada jabatan '{query}'".
- Clear search (SearchX button when query present) resets expanded state to defaults.

### Refresh

- Toolbar Refresh button calls `router.reload()` to re-fetch tree from backend.
- Shows `RefreshCw` with `animate-spin` while navigating.

### Loading / Empty / Error

- **Loading:** `navigating` true → skeleton tree rows (3 placeholder nodes), `aria-busy="true"`.
- **Empty (no positions):** EmptyState icon `ListTree`, title "Belum ada struktur jabatan", description about OptiGate sync.
- **Empty (search no match):** EmptyState icon `SearchX`, title "Tidak ada jabatan '{query}'", description suggests other keywords.
- **Error:** flash error banner + Refresh button to retry.

## D) Page Layout

```
┌─────────────────────────────────────────────────────────────┐
│ Data Master › Position › Struktur Jabatan           [badge] │
│ Hierarki jabatan organisasi — perluas cabang untuk melihat  │
│ bawahan, kekosongan, dan penempatan.                        │
├─────────────────────────────────────────────────────────────┤
│ [Cari jabatan…🔍]  [Perluas semua] [Ciutkan] [Refresh↻]   │  ← sticky toolbar
├─────────────────────────────────────────────────────────────┤
│ [Menampilkan struktur dari Manager Operas] [✕ Tampilkan   │  ← path indicator (when initialRoot)
│ seluruh struktur]                                         │
├─────────────────────────────────────────────────────────────┤
│ [▶] Direksi                                         [↗]   │
│   [▶] HRD                                         [↗]   │
│     [▶] Manager Rekrutmen                         [↗]   │
│       [▼] Recruiter                            [👤 2 terisi][↗]
│         [▶] Staff Recruiter                      [↗]   │
│                                                         │
│ [EmptyState / Skeleton / Tree]                          │
└─────────────────────────────────────────────────────────────┘
```

### Responsive behavior

- **375px (mobile):** Full-width tree, toolbar stacks vertically (search full width, buttons wrap), indentation `sm:ml-7` reduced to `ml-3`, font sizes `text-sm`.
- **768px (tablet):** Toolbar single row, tree indentation `sm:ml-7 sm:pl-2`, max-width container `max-w-5xl`.
- **1024px (desktop):** Full toolbar row, tree indentation `lg:ml-7 lg:pl-2`, container `max-w-5xl`, padding `lg:px-8`.
- **1440px (large desktop):** Same as desktop, wider container, tree may use `max-w-6xl`.

### Toolbar spec

- Search Input: `type="search"`, `aria-label="Cari jabatan"`, placeholder "Cari jabatan…", `className="h-11 pl-9"`, Search icon leading, SearchX clear button when query present.
- Expand All: `Button variant="outline" size="sm"`, icon `ListTree` + label "Perluas semua", disabled when searching/empty.
- Collapse All: `Button variant="outline" size="sm"`, icon `Minimize2` + label "Ciutkan", disabled when searching/empty.
- Refresh: `Button variant="outline" size="sm"`, icon `RefreshCw` (spin while navigating), label "Refresh".
- All toolbar buttons `min-h-11` (≥44px), `focusRing` applied.

## E) Accessibility Notes (A1–A10)

| ID | Requirement | Implementation |
|----|-------------|----------------|
| A1 | Keyboard-only navigation | All interactive elements (disclosure, name link, toolbar buttons, search) reachable via Tab; `Enter`/`Space` toggle disclosure; `Escape` not applicable (no modal on this page) |
| A2 | Touch targets ≥44px | All buttons `min-h-11` (≥44px); disclosure button `size-11` (≥44px); name link full row height |
| A3 | Color never alone | Vacancy/status always icon + text + color; badges have icons (Clock3/CheckCircle2/CircleDot/Users); never color-only meaning |
| A4 | Visible focus rings | `focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2` on all buttons/links/inputs |
| A5 | Reduced motion | `motion-reduce:transition-none`; spinner disabled under `prefers-reduced-motion`; expand/collapse transitions 200–300ms respect reduced motion |
| A6 | Contrast 4.5:1 both themes | `ink`/`ink-muted` on `surface`/`surface-raised` meet 4.5:1 light and dark; `warning-ink` on `warning-subtle` verified |
| A7 | Locale-aware id-ID times | Any dates use `toLocaleDateString('id-ID')`; times use `toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' })` + "WITA" |
| A8 | Screen reader labels | `aria-label` on disclosure (`Perluas {nama}` / `Ciutkan {nama}`), name link (`Buka detail {nama}`), toolbar buttons; `role="tree"`, `role="group"` on children `ul`, `aria-label` on tree; `aria-live="polite"` not needed (no pagination on tree) |
| A9 | Search accessible | `<label className="sr-only">Cari jabatan</label>`, `type="search"`, clear button `aria-label="Bersihkan pencarian"` |
| A10 | EmptyState descriptive | Title + description explain the empty state (e.g., "Belum ada struktur jabatan — Jabatan dan hierarkinya dibangun dari sinkronisasi OptiGate. Jalankan sinkronisasi Position untuk mengisinya."); no vague "nothing here" |

## F) Handoff Checklist for Implementer

- [ ] Rewrite `resources/js/pages/positions/tree.tsx` with exact props interface above
- [ ] Implement functional `onExpandAll` (sets all node IDs expanded) and `onCollapseAll` (sets root IDs only)
- [ ] Implement `loading`/`navigating` state via Inertia `router` visit events (not hardcoded `false`)
- [ ] Add error state: flash error banner + Refresh retry
- [ ] Make position name clickable link to `positions/show/{position}` (persistent, not hover-only icon)
- [ ] Keep external link icon but make it always visible on focus/hover with `aria-label`
- [ ] Implement vacancy badge: icon + text + color (prominent, not small label)
- [ ] Add path indicator bar when `initialRoot` is set
- [ ] Implement search debounce 300ms with `filterTree` path preservation (existing logic kept)
- [ ] Auto-expand all match paths while searching
- [ ] Add Skeleton loading states for tree rows
- [ ] Add EmptyState for no positions and for search no-match
- [ ] Remove `resources/js/pages/positions/tree/index.tsx` (duplicate flat table)
- [ ] Verify no route references point to removed `tree/index.tsx`
- [ ] All interactive elements `min-h-11`; no raw hex; no emoji icons
- [ ] Indonesian copy throughout
- [ ] Light + dark theme via CSS variables only
- [ ] Responsive at 375px / 768px / 1024px / 1440px
- [ ] `tsc --noEmit` exit 0
- [ ] `npm run build` success

---

**Pending your approval.** Once approved, I'll invoke `writing-plans` and execute TDD task-by-task. Corrections welcome before approval.