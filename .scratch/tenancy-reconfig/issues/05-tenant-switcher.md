# 05: Tenant switcher

**What to build:** Let a user who may operate across branches move between them without re-login. The switcher's data source already exists — `HandleInertiaRequests.php:56-62` builds a `tenants` prop that **nothing currently reads**. The `TenantChip` in the sidebar already renders a `ChevronDown` affordance that does nothing.

**Blocked by:** 02 (`TenantAccess` seam), 03 (real branch names), 04 (branch records exist to switch between).

**Status:** done

- [x] Switcher lists only branches the user may operate, and only active ones
- [x] Switching navigates without re-login and without stale tenant context
- [x] Collection routes preserve the user's place; record routes land on the branch index
- [x] Single-branch users never see the switcher
- [x] Collapsed-sidebar behaviour is unchanged
- [x] A user cannot switch into a branch they may not operate — enforced server-side
- [x] `npm run types:check` clean and `npm run build` succeeds

---

## Where it lives

Two placements, one shared component:
1. **Sidebar `TenantChip`** (`app-sidebar.tsx:192-226`) — currently a `<span>`
   that renders a dead `ChevronDown`. Becomes a `DropdownMenu` around a
   `<button>`, preserving every `group-data-[collapsible=icon]:*` class.
2. **`/dashboard` top bar** — the central cross-branch landing page. A user with
   one branch is redirected straight to `/{tenant}/…` on login and never sees it.

## The authorization invariant (not optional)

The switcher is a UI affordance; `EnsureTenantAccess` is the boundary. Hiding a
branch from the list is **not** enforcement. Every target URL must independently
pass the `TenantAccess` check in `EnsureTenantAccess`, or a user who hand-types
`/plant-1/work-orders` reads another branch's data. Add a feature test that
does exactly that.

## Switching semantics

Path-based tenancy means switching is a URL rewrite. Use `router.visit` with the
new path — a full Inertia visit, so `Tenancy::initialize()` runs and all tenant
state is rebuilt. **Do not mutate `activeTenant` client-side**; it is derived
server-side from `tenant()?->getTenantKey() ?? $user->tenant_id`.

Decide the destination from the route name at click time:
- **Collection route** (`divisions.index`, `work-orders.index`, …) → preserve the
  path: `/hq/work-orders` → `/plant-1/work-orders`.
- **Record route** (`divisions.show`, `work-orders.show`, …) → drop the ID segment
  and land on that branch's index: `/hq/divisions/abc-123` → `/plant-1/divisions`.

Rationale: a branch-scoped ID has no meaning in another branch, so preserving it
would 404. Deciding client-side avoids any 404-detection round trip.

## Where the branch list comes from

Do **not** build a new endpoint. `HandleInertiaRequests` already ships a `tenants`
prop — add a type declaration in `resources/js/types/global.d.ts` (it currently
falls through the index signature) and filter it through
`TenantAccess::operableTenants()` rather than `Tenant::all()`.

Note the current code leaks **every** branch to every authenticated user. Once the
switcher exists that stops being harmless.

## Other places that must not disagree

Four components render branch context and must stay in sync or you get two truths:
- `app-sidebar.tsx` `TenantChip` (this ticket)
- `app-header.tsx:358-366` — renders the raw slug
- `bottom-nav.tsx:152-159` — `Cabang aktif: {activeTenant}`
- `app-sidebar-header.tsx:74` — uses `activeTenant` only to enable/disable search

Ticket 03 gives them real names. All four should display the name, keep the slug
for URLs.

**Verified at implementation time:** this list was already stale. Ticket 03 had resolved
the names everywhere, so by the time this ticket was picked up `app-header.tsx` and
`bottom-nav.tsx` were already reading `tenants.find(...)?.name ?? activeTenant`. The only
real work here was `TenantChip`, which was a dead `<span>` with a `ChevronDown` that did
nothing — it is now gone, replaced by `BranchSwitcher`, and `app-header.tsx` reuses the
same component for its dashboard placement rather than growing a second implementation.

## Collapsed sidebar

`TenantChip` already has a collapsed variant that hides the label and
`ChevronDown`. In collapsed mode a dropdown of branches is unusable — **show the
current branch as a tooltip** (`title` attribute) rather than a dropdown trigger.

## Verification

Backend — `tests/Feature/BranchSwitcherTest.php`, 8 tests:

- a branch user is offered exactly their own branch;
- a super admin is offered every branch, with `id`/`name`/`code`/`is_active`;
- `is_active` is reported correctly so the UI can hide archived branches;
- an archived branch is still reachable by direct URL (archiving is not deletion);
- **a branch user hand-typing `/plant-1/departments` gets 403**, and the same for a
  write — with an assertion that the row did not land in the other branch either;
- a user with no home branch is refused everywhere;
- a super admin moves between branches freely.

Frontend — `npm run types:check` clean, `npm run build` succeeds, and
`branch-switcher.tsx` passes the repo linter.

### Deviation: path depth, not the route name

The ticket says to decide the destination from the route name. The implementation
decides from the **path shape** instead, in `branchDestination()`. The route list is
fully regular — `/{tenant}/{collection}`, `/create`, `/{id}`, `/{id}/edit`,
`work-orders/{workOrder}/attachments/{index}` — so the depth after the collection segment
is exactly equivalent, needs no route-name registry on the client, and keeps working for
the nested `attachments` route that a name-suffix rule would miss.

### What is NOT verified

- **No browser QA.** The repo has no Playwright or any JS test runner, so the collapsed
  rail, the menu, and the actual navigation were not exercised in a real browser. The
  collapsed behaviour is implemented (`useSidebar().state === 'collapsed'` suppresses the
  trigger and leaves the `title` tooltip) but is unproven visually. Adding a test runner
  is a dependency change and needs approval.
- `branchDestination()` has no unit test for the same reason. It is small and pure, so it
  is the first thing worth covering once a runner exists.

### Existing-test update

`tests/Feature/DashboardTest.php` pinned the exact shape of the `tenants` prop; it gained
`is_active` and was updated. That is the one place the prop contract is asserted twice.