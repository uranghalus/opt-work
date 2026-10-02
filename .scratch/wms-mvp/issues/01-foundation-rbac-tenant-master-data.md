# 01: Fondasi RBAC + tenant scoping + master data

**What to build:** Admin mengelola master data (divisions, departments, positions, employees) dalam satu cabang aktif dengan RBAC Spatie dan isolasi data per cabang via stancl/tenancy (tenancyforlaravel.com) mode single-database — identifikasi tenant via path `/{tenant}/...`. Role baseline di-seed sesuai referensi opti-work2 (`super_admin`, `admin_tenant`, `general_manager`, `deputy_general_manager`, `hod`, `team_leader`, `karyawan`, `field_staff`, `viewer`). Catatan: bagian "users (dengan assign role)" dipindah ke ticket 10.

**Blocked by:** None (can start immediately)

**Status:** claimed

- [x] Tenant scoping via stancl/tenancy single-database (`BelongsToTenant`, path identification)
- [x] Master data schema Laravel-conventional (divisions, departments, positions, employees + tenant_id)
- [x] Role seeder 9 role baseline + permission set dari referensi opti-work2
- [x] Middleware permission per route + `ensure.tenant.access` (isolation boundary)
- [x] CRUD pages master data (index/create/edit/show) dengan token desain Dispatch Board
- [x] Isolasi data teruji: data cabang A tidak bocor ke cabang B; 403 untuk user tanpa linkage; 404 untuk route binding lintas cabang
