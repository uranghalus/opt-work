# 02: Assign & revoke roles on the linked Akun (guarded)

**What to build:** An admin with `akun.update` opens the Karyawan page of a karyawan with a linked Akun and changes that Akun's roles in one self-contained dialog (multi-select of existing roles, sync semantics: the saved set replaces the current set). The server refuses to grant the `super_admin` role, auto-attaches `karyawan` whenever `field_staff` is granted, and refuses to revoke `karyawan` from a `field_staff` holder with an explanatory error. Assignment never touches `is_super_admin`, `is_hod`, or the department HOD/manager links. The action button is hidden without `akun.update`. A role change is live for that user on their next request (pivot is not cached — verified by test).

**Blocked by:** 01 (See the linked Akun's roles on the Karyawan page).

**Status:** ready-for-agent

- [ ] Role-sync endpoint gated on `akun.update` in the controller (route ungated, per convention); super admin bypass passes
- [ ] `super_admin` is rejected server-side with a clear validation error, regardless of who asks
- [ ] Granting `field_staff` results in both `field_staff` and `karyawan` being held; revoking `karyawan` while `field_staff` is held fails with an explanatory message; a successful sync leaves exactly the requested set (plus the auto-pair)
- [ ] `is_super_admin`, `is_hod`, and `departments.hod_user_id`/`manager_user_id` are byte-identical before/after a sync (asserted in tests)
- [ ] Feature test proves a freshly assigned role is recognized by the Gate on a subsequent request (no logout/login needed)
- [ ] Dialog follows the self-contained heading-band pattern (SyncButton/Employees style), lists all roles except `super_admin`, shows dirty-state + error toasts; hidden when the viewer lacks `akun.update`
- [ ] `vendor/bin/pint --dirty`, affected Pest tests green, `npm run build` green
