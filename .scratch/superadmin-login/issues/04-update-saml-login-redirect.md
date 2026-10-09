# 04: Update SAML Login Redirect Logic

**What to build:** Modify `SamlController::acs()` to redirect superadmins to `/admin` instead of `dashboard`, and tenant users to their `/{tenant}/dashboard`.

**Blocked by:** 01 (needs `/admin` route to exist)

**Status:** ready-for-agent

- [ ] In `SamlController::acs()`, after `loginUser()`:
  - If `$user->is_super_admin` → `redirect()->route('admin.dashboard')`
  - Else if `$user->tenant_id` → `redirect()->route('dashboard', ['tenant' => $user->tenant_id])`
  - Else → redirect to tenant selector (or `/admin` with message)