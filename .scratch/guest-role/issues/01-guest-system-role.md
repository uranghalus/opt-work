# 01: Role `guest` sebagai system role ke-10

**What to build:** `guest` masuk `RoleAndPermissionSeeder::SYSTEM_ROLES`,
punya `is_system=1`, **nol permission** (role murni penanda state), dan
terdaftar di `roleDefaults()` sebagai `[]`.

**Status:** done 2026-10-17

- [x] `SYSTEM_ROLES` tambah `guest` setelah `viewer`
- [x] `roleDefaults()['guest'] = []` — nol permission
- [x] `RoleAndPermissionSeederTest`: "ten canonical system roles" + `guest`
      is_system + 0 permission
- [x] `PermissionConsistencyTest`: tak ada entri `guest` (bukan permission)
- [x] `CONTEXT.md`: "sembilan" → "sepuluh" + glossary `Guest`
- [x] `SyncAkunRolesRequest` whitelist ikut (guest masuk SYSTEM_ROLES minus
      super_admin) — efek samping disetujui: bisa assign guest manual