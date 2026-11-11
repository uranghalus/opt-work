# Positions Tree Frontend — Struktur Jabatan Rewrite

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite `resources/js/pages/positions/tree.tsx` as a functional, accessible position hierarchy tree (Struktur Jabatan) and remove the duplicate flat-table view at `resources/js/pages/positions/tree/index.tsx`. Fix empty expand/collapse handlers, hardcoded loading state, hover-only detail affordance, and missing loading/empty/error states.

**Architecture:** Single `tree.tsx` page with recursive `NodeRow` sub-component. Toolbar has search (debounce 300ms), Expand All / Collapse All (functional), Refresh. Tree renders `role="tree"` with `NodeRow` items. Position name clickable → `positions/show/{position}`. Disclosure button toggles children (≥44px). `router.reload()` for refresh, `router.visit()` for detail navigation. `router.reload({ only: [...] })` not applicable (full page reload needed for tree data changes).

**Tech Stack:** Laravel 12 + PHP 8.4 (Pest), Inertia + React + shadcn/ui (Tailwind), Wayfinder route helpers, lucide-react icons

**Spec:** `docs/superpowers/specs/2026-10-29-fr-positions-tree-design.md`

## Global Constraints

- PHP 8.4, Laravel 12, Pest; Frontend: Inertia + React + shadcn/ui + Tailwind
- Tokens only (surface-raised/info-subtle/success-subtle/warning-subtle/ink/border-border/shadow-card), NO raw hex
- No emoji icons — lucide-react only
- Touch ≥ 44px (`min-h-11`) on every interactive element
- Light + dark theme support via CSS variables; prefers-reduced-motion respected
- Wayfinder route helpers (`tree`, `show as positionsShow`)
- `tsc --noEmit` exit 0; `npm run build` success; `php artisan test --compact` green
- Commit per task, explicit `git add <files>` (no `git add .`); no `--no-verify`

## Review Focus

1. `tree.tsx` and `tree/index.tsx` both claim the `positions/tree` route — one must be removed (keep `tree.tsx`, remove `tree/index.tsx`)
2. `onExpandAll`/`onCollapseAll` are empty stubs (`() => {}`) — must be functional
3. `loading` is hardcoded `false` — must derive from Inertia navigation state
4. Detail link (`ExternalLink`) is `opacity-0` hover-only — must be persistent clickable position name with visible icon on focus/hover
5. Vacancy badge should be prominent (icon + text + color), not small label
6. Path indicator when `initialRoot` is set (from detail page deep-link)
7. No new icons — lucide-react only; no raw hex — tokens only

---

### Task 1: Rewrite `tree.tsx` — functional hierarchy tree

**Files:**
- Create: `resources/js/pages/positions/tree.tsx` (full rewrite, replaces existing)

**Interfaces:**
- Consumes: `rootPositions` + `initialRoot` from Inertia page props; `tenant` via `useRequiredTenant`; `verifyRoute`, `treeIndex` from `@/routes/positions`
- Produces: `PositionTree` component — breadcrumb, sticky toolbar (search + Expand All / Collapse All / Refresh), tree rendering, path indicator bar, empty/loading/error states; hosts `NodeRow` sub-component internally

- [ ] **Step 1: Write the component** — `PositionTree` with `PageProps` interface (`rootPositions: PositionNode[]`, `initialRoot?: string`); `NodeRow` sub-component with `node`, `depth`, `expanded`, `searching`, `tenant`, `onToggle`, `onNavigate` props; `StatusChip`-style vacancy badge; hero progress not applicable; sticky toolbar with search (debounce 300ms) + Expand All / Collapse All + Refresh; `filterTree` preserves parent path; `collectIds` for expand all; error/loading/empty states; `VerificationDialog`-style ConfirmDialog NOT used here (no confirm needed); `router.visit()` for detail navigation; `router.reload()` for refresh
- [ ] **Step 2: Run tsc** — `npx tsc --noEmit` → exit 0
- [ ] **Step 3: Commit** — `git add resources/js/pages/positions/tree.tsx` → `feat(positions): rewrite tree.tsx as functional hierarchy tree`

### Task 2: Remove duplicate `tree/index.tsx`

**Files:**
- Delete: `resources/js/pages/positions/tree/index.tsx`

**Interfaces:**
- Consumes: nothing (deletion only)
- Produces: single source of truth for `positions/tree` route = `tree.tsx`

- [ ] **Step 1: Delete the file** — `git rm resources/js/pages/positions/tree/index.tsx`
- [ ] **Step 2: Run tsc** — `npx tsc --noEmit` → exit 0 (no dangling imports should reference tree/index.tsx)
- [ ] **Step 3: Commit** — `git add resources/js/pages/positions/tree/index.tsx` (deletion staged) → `feat(positions): remove duplicate tree/index.tsx flat table view`

### Task 3: Full verification + commit + push

**Files:** none

**Interfaces:**
- Consumes: all changes from Tasks 1–2
- Produces: verification results + commit + push

- [ ] **Step 1: Run tsc** — `npx tsc --noEmit` → exit 0
- [ ] **Step 2: Run pint** — `vendor/bin/pint --dirty --format agent` → clean (frontend only, should be no-op)
- [ ] **Step 3: Run build** — `npm run build` → success
- [ ] **Step 4: Run full test suite** — `php artisan test --compact` → all pass, only pre-existing skips
- [ ] **Step 5: Commit remaining** — explicit `git add` for any uncommitted files → `feat(positions): tree.tsx hierarchy rewrite + remove duplicate`
- [ ] **Step 6: Push** — `git push origin feature/daily-work-verification`

---

**Plan complete and saved to `docs/superpowers/plans/2026-10-29-positions-tree-frontend.md`. Please review the plan. Does it capture what you want?**

I recommend **Native** execution — all tasks are small, tightly coupled to a single file rewrite plus deletion, and tsc/build/test gates make mistakes cheap to catch. The previous execution method (Native) is preserved.
