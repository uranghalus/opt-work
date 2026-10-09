# 01: Add Central Admin Route Group

**What to build:** A new route group at `/admin` (outside the `{tenant}` prefix) accessible only to superadmins/executives. This will serve as the central admin dashboard and tenant management area.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Add `Route::middleware(['auth', 'role:superadmin|executive'])->prefix('admin')->group(...)` in `routes/web.php`
- [ ] Add `Route::get('/', [Admin\DashboardController::class, 'index'])->name('admin.dashboard')`
- [ ] Add tenant management routes: `Route::resource('tenants', Admin\TenantController::class)`
- [ ] Verify route registration with `php artisan route:list`

---

# 02: Create Admin Dashboard Controller

**What to build:** A controller that renders the central admin dashboard showing cross-tenant KPIs and quick actions for superadmins.

**Blocked by:** 01 (needs admin route group)

**Status:** ready-for-agent

- [ ] Create `app/Http/Controllers/Admin/DashboardController.php`
- [ ] `index()` method: fetch all tenants, compute cross-tenant stats using `$tenant->run()`
- [ ] Return Inertia view `admin/dashboard` with tenant list and stats
- [ ] Add "Switch to Tenant" buttons using signed URLs

---

# 03: Create Admin Dashboard View

**What to build:** The React view for the admin dashboard showing tenant cards with key metrics and "Switch to Tenant" actions.

**Blocked by:** 02 (needs controller data structure)

**Status:** ready-for-agent

- [ ] Create `resources/js/pages/admin/dashboard.tsx`
- [ ] Display tenant grid with name, code, status, employee count, open WOs
- [ ] "Switch to Tenant" button per card using `router.get('/tenant/switch-url/{tenant}')`
- [ ] Follow DESIGN.md styling (card-based, Source Sans 3, proper spacing)

---

# 04: Update SAML Login Redirect Logic

**What to build:** Modify `SamlController::acs()` to redirect superadmins to `/admin` instead of `dashboard`, and tenant users to their `/{tenant}/dashboard`.

**Blocked by:** 01 (needs `/admin` route to exist)

**Status:** ready-for-agent

- [ ] In `SamlController::acs()`, after `loginUser()`:
  - If `$user->is_super_admin` → `redirect()->route('admin.dashboard')`
  - Else if `$user->tenant_id` → `redirect()->route('dashboard', ['tenant' => $user->tenant_id])`
  - Else → redirect to tenant selector (or `/admin` with message)

---

# 04b: Handle Users Without Tenant (Edge Case)

**What to build:** Handle superadmin with no home tenant - redirect to `/admin` with tenant list.

**Blocked by:** 04 (same controller change)

**Status:** ready-for-agent

- [ ] If superadmin has no `tenant_id` and no operable tenants → show message in `/admin`
- [ ] If superadmin has operable tenants → show tenant selector in `/admin`

---

# 05: Create Tenant Selector Component

**What to build:** A reusable React component for superadmins to select/switch tenants, used in admin dashboard and potentially as a standalone page.

**Blocked by:** 02 (needs controller to pass tenant list)

**Status:** ready-for-agent

- [ ] Create `resources/js/components/admin/TenantSelector.tsx`
- [ ] Accept `tenants` prop and `onSelect` callback
- [ ] Card grid layout with tenant name, code, status badge
- [ ] "Enter" button calls `router.get('/tenant/switch-url/{id}')` then navigates
- [ ] Follow DESIGN.md: card-based, Source Sans 3, proper spacing

---

# 06: Add Tenant Selector to Admin Dashboard

**What to build:** Integrate the TenantSelector into the admin dashboard view.

**Blocked by:** 03, 05 (needs both view and component)

**Status:** ready-for-agent

- [ ] Import `TenantSelector` in `resources/js/pages/admin/dashboard.tsx`
- [ ] Pass `tenants` from controller and `onSelect` handler using router

---

# 07: Tests for Superadmin Login Flow

**What to build:** Feature tests covering the superadmin login and tenant switching flow.

**Blocked by:** 01-06 (needs all implementation done)

**Status:** ready-for-agent

- [ ] Test: Superadmin logs in → redirected to `/admin`
- [ ] Test: Superadmin sees tenant list in `/admin`
- [ ] Test: Superadmin clicks "Switch" → lands on `/{tenant}/dashboard`
- [ ] Test: Tenant admin logs in → redirected to `/{their_tenant}/dashboard`
- [ ] Test: Superadmin without home tenant sees tenant selector