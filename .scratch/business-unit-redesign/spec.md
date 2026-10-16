# Spec: Business Unit Page Redesign

## Objective
Redesign the Business Unit page to detach it from the `setting-layout` and provide a high-impact, modern, mobile-first visual overhaul that distinguishes it from other pages in the app.

## Technical Requirements
- **Layout:** Direct render in `app-sidebar-layout`.
- **UI Pattern:** Replace alphabetical list with a responsive Card Grid.
- **Mobile:** Optimized for 375px (Single column cards).
- **Stack:** Inertia, React, shadcn, Wayfinder.
- **Invariants:**
    - Search/Filter/Pagination must maintain current URL-state behavior.
    - `auth.can` permission gating must remain intact.
    - No changes to backend contracts (Controller/Props).

## Tickets
- [ ] `01-structural-layout-change`: Detach from `setting-layout`, verify sidebar/breadcrumbs.
- [ ] `02-ui-foundation-and-tokens`: Define module signature colors and base card structure.
- [ ] `03-grid-implementation`: Replace list with responsive card grid, handle empty/loading states.
- [ ] `04-filter-and-search-refactor`: Adapt search/status filters to the new visual layout.
- [ ] `05-pagination-and-actions`: Implement new pagination UI and integrate `SyncButton`.
- [ ] `06-qa-and-responsive-verification`: Verify 375px/768px/1280px and functional parity.
