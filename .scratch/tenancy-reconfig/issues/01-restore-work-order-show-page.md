# 01: Restore the missing work-order detail page

**What to build:** `routes/tenant.php:75` renders `Inertia::render('work-orders/show', …)` but `resources/js/pages/work-orders/show.tsx` does not exist. Inertia's Vite plugin auto-resolves the page component, so every click on a work order row throws a module-resolution error — a 500. The route works today only because no test exercises it.

This is a pre-existing crash, unrelated to RBAC or tenancy. It lands first so the suite is trustworthy before the larger refactors.

**Blocked by:** None.

**Status:** ready-for-agent

- [ ] `resources/js/pages/work-orders/show.tsx` exists and renders the WO
- [ ] Shows at minimum: `nomor_wo`, `title`, `description`, `category`, `requester`, `targetDepartment`, `requested_schedule_date`, `created_at`
- [ ] Attachments render as images from the tenant-scoped storage path
- [ ] Sets `.layout` (breadcrumbs/title/description) like other `index.tsx` pages
- [ ] A feature test asserts `GET /{tenant}/work-orders/{wo}` returns 200 for a user in that branch

---

## Implementation notes

Mirror `resources/js/pages/divisions/show.tsx` (55 lines) — a read-only `<dl>` of
`border-b px-4 py-3` rows, `font-mono text-sm` for codes, `?? '—'` for nulls.
The index page links rows to `show({ tenant, workOrder: id })`, so the page must
accept that prop name.

`WorkOrderController::show()` (`app/Http/Controllers/WorkOrderController.php:95-100`)
already eager-loads `['requester', 'targetDepartment']`, so no controller change
is needed. The prop arrives as `workOrder`.

**No ownership check here.** Per the settled decision, tenant-level isolation is
the rule: `EnsureTenantAccess` blocks cross-branch reads, and within a branch any
authenticated user may read any WO. Do not add a per-user filter here.

## Verification

- `php artisan test --filter=WorkOrderTest` — new test green, existing 10 still green.
- `npm run types:check` — clean.