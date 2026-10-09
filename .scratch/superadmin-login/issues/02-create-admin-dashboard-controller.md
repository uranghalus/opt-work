# 02: Create Admin Dashboard Controller

**What to build:** A controller that renders the central admin dashboard showing cross-tenant KPIs and quick actions for superadmins.

**Blocked by:** 01 (needs admin route group)

**Status:** ready-for-agent

- [ ] Create `app/Http/Controllers/Admin/DashboardController.php`
- [ ] `index()` method: fetch all tenants, compute cross-tenant stats using `$tenant->run()`
- [ ] Return Inertia view `admin/dashboard` with tenant list and stats
- [ ] Add "Switch to Tenant" buttons using signed URLs