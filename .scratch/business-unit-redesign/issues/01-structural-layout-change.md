# Issue 01: Structural Layout Change

## Description
Detach the Business Unit index page from the `setting-layout` wrapper and ensure it renders directly inside `app-sidebar-layout`.

## Tasks
- [ ] Remove `setting-layout` from `resources/js/pages/settings/tenants/index.tsx`.
- [ ] Verify that the page still renders correctly within the main application shell.
- [ ] Verify sidebar active state for the "Unit Bisnis" menu item.
- [ ] Verify breadcrumbs (`Pengaturan > Unit Bisnis`) are still present.

## Verification
- Render the page and check for layout regressions.
- Ensure other settings pages still use `setting-layout` and are unaffected.
