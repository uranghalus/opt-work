# Follow-up: align `app:sync-departments` & `app:sync-employees` with the Optigate sync pattern

**Status:** Not started (logged 2026-10-10)
**Origin:** Phase 4 tenant-sync rewrite (see `TODO.md` — "Optigate sync" section)

## Why

`app:sync-tenants` was rewritten (2026-10-10) to the prompt spec: `optigate_company_id`
mapping, `code` slug, pagination, deactivate/reactivate, retries, landlord-side
(no `Tenant::current()` dependency), mocked feature test.

The sibling commands still carry the legacy pattern:

- `SyncDepartments` / `SyncEmployees` abort when no current tenant exists
  (`--tenant=` option exists but nothing schedules them)
- no pagination on `GET /api/departments` / company users endpoints
- no retries; no idempotency test; no deactivation semantics

They were intentionally left untouched per the "don't change anything unrelated"
constraint of the tenant-sync task.

## Suggested shape (when picked up)

- Departments/employees are per-company data: iterate `Tenant` (from the tenant
  sync's results) instead of depending on a current tenant.
- Reuse a shared Optigate HTTP client (pagination + retry + 401/403 handling)
  instead of copying the fetch loop.
- Their API endpoints: `GET /api/companies/{company}/departments`,
  `GET /api/companies/{company}/users` (see `optigate-complete-api-docs.md`).
