# 05: Tenant switcher

**What to build:** Let a user who may operate across branches move between them without re-login. The switcher's data source already exists — `HandleInertiaRequests.php:56-62` builds a `tenants` prop that **nothing currently reads**. The `TenantChip` in the sidebar already renders a `ChevronDown` affordance that does nothing.

**Blocked by:** 02 (`TenantAccess` seam), 03 (real branch names), 04 (branch records exist to switch between).

**Status:** ready-for-agent

- [ ] Switcher lists only branches the user may operate, and only active ones
- [ ] Switching navigates without re-login and without stale tenant context
- [ ] Collection routes preserve the user's place; record routes land on the branch index
- [ ] Single-branch users never see the switcher
- [ ] Collapsed-sidebar behaviour is unchanged
- [ ] A user cannot switch into a branch they may not operate — enforced server-side

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

## Collapsed sidebar

`TenantChip` already has a collapsed variant that hides the label and
`ChevronDown`. In collapsed mode a dropdown of branches is unusable — **show the
current branch as a tooltip** (`title` attribute) rather than a dropdown trigger.

## Verification

- A user with 2+ operable branches sees the switcher; a user with 1 does not.
- Switching `/hq/work-orders` → `/plant-1/work-orders` preserves the page.
- Switching from `/hq/divisions/{id}` lands on `/plant-1/divisions`.
- Hand-typing a forbidden `/{tenant}/…` URL returns 403 (server-side test).
- Collapsed rail: no layout shift, tooltip present, dropdown not triggerable.
- `npm run types:check` clean.