---
created: 2026-10-16
tags: [rbac, akun, spatie, wms]
status: done
---

# 0033 — Akun role read surface (role assignment ticket 01)

[[Index]]

## Apa yang dilakukan

- `/workflow` sesi: plan dari TODO.md → grill multi-round → scope dippersempit user: **role assignment pada akun + pembacaan peran yang benar** (WO slice ditunda).
- **Docs**: ADR-0005 `docs/adr/0005-user-role-assignment.md` (surface employee-centric, resource `akun` 4 verb, hanya sentuh Spatie roles, super_admin tidak assignable, pair field_staff⇄karyawan server-side, can flags via Gate); `CONTEXT.md` +istilah **Role Assignment (Penugasan Peran)** + sharpening **Akun** (roles attach to Akun, bukan Karyawan); ADR-0004 route `/settings/roles` → `/hak-akses` diperbaiki.
- **Tickets** di `.scratch/role-assignment/issues/01..03` (01 read surface, 02 sync guarded — blocked by 01, 03 can flags).
- **Ticket 01 selesai (TDD)**: katalog `akun.*` + roleDefaults admin_tenant + per-release grant; allowlist akun.update/create/delete; `EmployeeController::show` resolusi manual (pola ban/WorkOrderController) + payload `akun` ber-gate `akun.read` (tri-state: null / belum-ada-akun / roles); section "Peran Akun" di `employees/show.tsx`; fix `href={-1}` (tsc error warisan WIP sesi 11-10).
- Verifikasi: 277 tests (269 passed, 8 skipped, 0 failed), pint bersih, `npm run build` exit 0.

## File diubah

- `database/seeders/RoleAndPermissionSeeder.php`, `app/Http/Controllers/MasterData/EmployeeController.php`, `resources/js/pages/employees/show.tsx`
- `tests/Feature/Rbac/EmployeeAkunRolesTest.php` (baru), `tests/Feature/GrantDefaultPermissionsTest.php`, `tests/Feature/Rbac/PermissionConsistencyTest.php`
- `CONTEXT.md`, `docs/adr/0004-hak-akses-surface.md`, `docs/adr/0005-user-role-assignment.md`
- `routes/web.php` + hapus `employees/create|edit.tsx` + `index.tsx` + `package-lock.json` = WIP sesi 11-10 (CRUD lokal karyawan dihapus, karyawan portal-synced) — di-commit terpisah.

## Terkait

- `[[Decisions/adr-0005-user-role-assignment]]` (sync vault menyusul)
- `[[Sessions/2026-10/11/1236-employee-ban-akun-sync-saml-logout]]` (WIP yang diteruskan)
- `.scratch/role-assignment/issues/` (ticket 02, 03 belum dikerjakan)
