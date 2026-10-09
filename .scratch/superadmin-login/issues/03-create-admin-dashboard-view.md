# 03: Create Admin Dashboard View

**What to build:** The React view for the admin dashboard showing tenant cards with key metrics and "Switch to Tenant" actions.

**Blocked by:** 02 (needs controller data structure)

**Status:** ready-for-agent

- [ ] Create `resources/js/pages/admin/dashboard.tsx`
- [ ] Display tenant grid with name, code, status, employee count, open WOs
- [ ] "Switch to Tenant" button per card using `router.get('/tenant/switch-url/{tenant}')`
- [ ] Follow DESIGN.md styling (card-based, Source Sans 3, proper spacing)