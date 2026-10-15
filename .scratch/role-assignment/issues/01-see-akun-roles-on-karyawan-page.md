# 01: See the linked Akun's roles on the Karyawan page

**What to build:** An admin opens a Karyawan who has a linked Akun and sees that Akun's current roles, read-only. A Karyawan without a linked Akun shows a clear "belum ada akun" state instead. Users without the new `akun.read` permission see neither. The `akun` permission resource enters the catalogue with its four CRUD verbs; `admin_tenant` receives its defaults through the per-release grant step; the two verbs not yet exercised by code (`akun.create`, `akun.delete`) get forward-looking allowlist entries with a "gated when Akun CRUD lands" comment so the honesty test stays green.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] `akun.read|create|update|delete` exist in the seeded catalogue; release-grant command grants them to `admin_tenant` idempotently and its tests cover the addition
- [ ] Honesty-test allowlist carries `akun.create` + `akun.delete` with the forward-looking comment; `akun.read`/`akun.update` are NOT allowlisted (they are gated in this ticket's code / ticket 02)
- [ ] Karyawan show payload includes the linked Akun's role names only when the viewer holds `akun.read` (super admin passes via bypass); omitted cleanly when no Akun is linked
- [ ] Karyawan show page renders the roles read-only, or the "belum ada akun" state when no Akun is linked
- [ ] Feature tests: payload visible with `akun.read`, hidden without, super-admin bypass works, no-akun case returns a null/absent Akun
- [ ] `vendor/bin/pint --dirty`, affected Pest tests green, `npm run build` green
