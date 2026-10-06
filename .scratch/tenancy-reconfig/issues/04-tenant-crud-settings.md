# 04: Tenant CRUD at `/settings/tenants`

**What to build:** A central, non-tenant-prefixed CRUD for branch records — create, view, edit, archive/delete. Lives at `/settings/tenants`, mirroring the existing `settings/roles` precedent, and is reachable only by users who may operate across branches (`is_super_admin`; later also `tenant-operator`).

Route-loading order is already safe: `routes/tenant.php` registers inside an `$app->booted()` callback, so central routes are matched first and `/settings/tenants` can never be captured by `/{tenant}/*`.

**Blocked by:** 03 (real columns), 02 (`TenantAccess` seam).

**Status:** done

- [x] CRUD at `/settings/tenants` with index / show / create / edit / destroy
- [x] Only users permitted to operate across branches reach it; others get 403
- [x] Branch `id` is generated as a URL-safe slug and is immutable after creation
- [x] Inactive branches are hidden from pickers but still resolvable by direct URL
- [x] Deleting a branch that has data is refused
- [x] Mirrors the house CRUD pattern exactly

---

## Access

`TenantAccess::canOperateAnyBranch($user)` — `is_super_admin` today. Middleware
group-level on the route group. When RBAC returns this becomes `tenant-operator`.

**Branch admins manage their own branch's data, not the branch record itself.**
A branch admin with `is_super_admin = false` cannot reach this page.

## Field semantics

| Field | Rule |
|---|---|
| `id` (slug) | Auto-derived from `code` if `code` is set, else from `name`. Lowercase, `[a-z0-9-]`, immutable after create. |
| `name` | Required, human display name. Editable. |
| `code` | Optional short code. Unique. Editable. |
| `is_active` | Toggle. Inactive → hidden from pickers, but `/{tenant}/…` still resolves so historical links work. |

`id` immutability is deliberate: it is the primary key and appears in every
`tenant_id` FK. Renaming is a display concern; the slug is identity.

## Delete policy

Refuse with a clear message when the branch has any work orders, employees,
departments, divisions, or positions — otherwise you'd orphan tenant-scoped rows
via `nullOnDelete` and produce a branch that half-works. This mirrors the
existing `RoleController::destroy` guard (`RoleController.php:61-65`).

Archiving (`is_active = false`) is the supported path for retiring a branch.

## House pattern to mirror

Controller — `app/Http/Controllers/MasterData/DivisionController.php` is canonical:
- No constructor DI.
- `index`: `Model::query()->…->paginate(10)->withQueryString()`.
- `store`/`update`: inline `$request->validate([...])`, **no FormRequests** anywhere
  in `MasterData`. Redirect with `->with('success', '… berhasil dibuat.')`.
- `destroy`: no server-side confirm, redirect back to index.
- `use Inertia\Inertia;` and `Inertia\Response` — **no facade alias import**.

Pages — `resources/js/pages/divisions/*`:
- `index.tsx` sets `.layout = { breadcrumbs, title, description, actions }` where
  `actions` is a local component reading `usePage().props`.
- `create.tsx` / `edit.tsx` do **not** set `.layout`; they render their own
  `<Heading>` from `@/components/heading`.
- Forms use `{...TenantController.store.form({ tenant: activeTenant ?? '' })}` —
  the Wayfinder `.form()` variant (`formVariants: true` in `vite.config.ts`).
- Every field block: `<div className="grid gap-2">` + `<Label>` + control +
  `<InputError className="mt-1" message={errors.x} />`.
- Submit buttons carry `data-test="create-tenant-button"` etc.
- `show.tsx` is a read-only `<dl>` of `border-b px-4 py-3` rows, `?? '—'` for nulls.

Delete confirmation: no reusable pattern exists. `settings/roles.tsx` uses
`window.confirm` + `router.delete`; `components/delete-user.tsx` uses a shadcn
`Dialog` with a password field (too heavy here). **Use the `Dialog` structure from
`delete-user.tsx` without the password step** — a branch delete deserves a real
confirm, and this avoids introducing a third style.

Flash: `->with('success', …)` **and** render the `<Alert role="status">` block
locally as `settings/roles.tsx:47-52` does. Note that master-data index pages
currently ignore `flash.success` entirely, so without this block the confirmation
is invisible.

## Route registration

`routes/settings.php`, replacing the removed roles group:
```php
Route::prefix('settings/tenants')->name('tenants.')->middleware('tenant.platform')->group(…)
```
Register a `tenant.platform` alias in `bootstrap/app.php` pointing at a thin
middleware wrapping `TenantAccess` — same shape as the existing
`ensure.tenant.access` alias.

## Verification

- Feature tests: guest → redirect; branch admin → 403; super-admin → 200;
  create → slug generated and URL-reachable; duplicate slug/code rejected;
  delete refused when data exists; inactive hidden from the list but still resolvable.
- `npm run types:check` clean after Wayfinder regeneration.