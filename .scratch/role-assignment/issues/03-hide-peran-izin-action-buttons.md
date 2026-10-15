# 03: Hide Peran/Izin action buttons without rights

**What to build:** The shared `auth.can` prop exposes the hak-akses action flags (`role.create/update/delete`, `permission.read/create/update/delete`) alongside the existing `role.read`, every flag computed through the Gate so super admin (zero DB permissions by design) still sees everything. The Peran and Izin pages use these flags to hide create/rename/delete/sync action buttons from users who lack the matching permission — the server already rejects them, this closes the UI side.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] Shared `can` prop carries the six new flags, each computed via `can()` (never a raw permission list), following the existing `role.read` pattern and its ADR note
- [ ] Peran page hides create/rename/delete and the permission-sync action without the matching flags; Izin page hides create/rename/delete and batch-create without theirs
- [ ] Super admin (bypass, zero permissions) still sees all action buttons
- [ ] Feature/front-end checks: flags present for admin_tenant, absent for a `role.read`-only user; `npm run build` and tsc green
