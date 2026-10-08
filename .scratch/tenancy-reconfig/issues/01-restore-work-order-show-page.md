# 01: Restore the missing work-order detail page

**What to build:** `routes/tenant.php:75` renders `Inertia::render('work-orders/show', …)` but `resources/js/pages/work-orders/show.tsx` does not exist. Inertia's Vite plugin auto-resolves the page component, so every click on a work order row throws a module-resolution error — a 500. The route works today only because no test exercises it.

This is a pre-existing crash, unrelated to RBAC or tenancy. It lands first so the suite is trustworthy before the larger refactors.

**Blocked by:** None.

**Status:** functionally done; one convention question left (see Verification)

- [x] `resources/js/pages/work-orders/show.tsx` exists and renders the WO
- [x] Shows at minimum: `nomor_wo`, `title`, `description`, `category`, `requester`, `targetDepartment`, `requested_schedule_date`, `created_at`
- [x] Attachments render as images from the tenant-scoped storage path
- [ ] Sets `.layout` (breadcrumbs/title/description) like other `index.tsx` pages
- [x] A feature test asserts `GET /{tenant}/work-orders/{wo}` returns 200 for a user in that branch

---

## The `.layout` criterion is a convention question, not a defect

Re-audited when ticket 05 was picked up. Everything functional is in place: the page exists,
all eight fields render (the department arrives as `target_department`, snake_case, not
`targetDepartment` — the criterion's spelling is wrong), attachments render through
`showAttachment.url({ tenant, workOrder, index })` against the tenant-scoped storage path, and
`WorkOrderTest` covers the route with 15 passing assertions. The 500 that motivated this ticket
is gone.

The one unmet criterion is that the page does not set `.layout`. It renders its own `<Heading>`
instead — consistent with `create.tsx` and `edit.tsx`.

**But the page this ticket says to mirror does not set it either.** `resources/js/pages/divisions/show.tsx`
has no `.layout`, and neither do the `departments`, `employees`, or `positions` show pages.
The pages that do set `.layout` are every `index.tsx`, `dashboard`, and
`settings/tenants/show.tsx`.

So the criterion describes an intent that was never applied consistently, and matching the
named mirror would mean *not* satisfying it. That needs a human decision:

1. Make every master-data `show.tsx` set `.layout` — consistent with `index.tsx` and
   `settings/tenants/show.tsx`, but touches 5 pages.
2. Leave them rendering their own `<Heading>` — smaller diff, but then the criterion should be
   struck rather than left unchecked.

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