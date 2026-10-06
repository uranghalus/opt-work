# 03: Promote tenant branch fields to dedicated columns

**What to build:** The `tenants` table has four columns — `id`, timestamps, `data` JSON — and no `name`, `code`, or `is_active`. Branch identity lives in the URL, so tickets 04 and 05 need real, queryable columns. Today they cannot have them, because **`TenantSeeder` is silently broken**: it passes `['data' => [...]]`, but `VirtualColumn` strips the `data` attribute on `creating` and replaces it with the remaining virtual attributes, which are empty. Every seeded branch has `data = '[]'`, which is why `HandleInertiaRequests.php:59` needs its `?? $t->id` fallback. `nama_cabang` has never reached the UI.

Adding dedicated columns makes this class of bug impossible.

**Blocked by:** None. Unblocks 04 and 05.

**Status:** done

- [x] `tenants` has real `name`, `code`, `is_active` columns
- [x] `TenantSeeder` writes those columns and the values survive to the UI
- [x] `Tenant` model exposes them with no `data->` prefixing
- [x] Existing branches are backfilled, not left null
- [x] No code reads `data->` for branch name or code anymore

---

## Why dedicated columns, not `data`

The package docs state it directly: if you need the value in a `WHERE` clause,
make it a dedicated column — *"this will improve performance and you won't have
to think about the `data->` prefixing."* A switcher filtering to active branches
is a `WHERE`.

## Decision: `id` stays a slug

Per the settled decision, `tenants.id` remains the human-readable slug (`hq`,
`plant-1`) and stays the URL segment. No UUID migration, no custom
`PathTenantResolver`. Tradeoff accepted: renaming a branch means changing its
primary key and every `tenant_id` FK — rare, and branch codes are normally stable
identifiers.

Note `id` is `varchar`, not `uuid`, while every tenant table uses
`foreignUuid('tenant_id')->constrained('tenants')`. SQLite tolerates this; MySQL
may not. **Out of scope, flagged only.**

## Implementation

**Migration** — add to `tenants`:
- `name` string, after backfill make it non-null
- `code` string nullable, unique
- `is_active` boolean default true, indexed

Backfill from `data` where present, falling back to `id` for `name` (mirrors the
existing `HandleInertiaRequests` fallback, so nothing regresses visually).

**`app/Models/Tenant.php`** (new) — extends the stock model:
```php
public static function getCustomColumns(): array
{
    return ['id', 'name', 'code', 'is_active'];
}
```
Required, per docs: the `id` key must be included. Then point
`config/tenancy.php:15` `tenant_model` at it.

Keep the stock `$guarded = []` and `$modelsShouldPreventAccessingMissingAttributes = false`
inherited from the base — but controllers must still pass explicit arrays, never
`$request->all()`. Add a full `@property` docblock per house style.

**`database/seeders/TenantSeeder.php:17-21`** — rewrite to write the real columns:
```php
Tenant::firstOrCreate(['id' => $data['id']], Arr::except($data, 'id'));
```
with `name`/`code`/`is_active` as top-level keys. This is the actual bug fix —
the current `['data' => array_diff_key(...))]` line is what gets discarded.

**`app/Http/Middleware/HandleInertiaRequests.php:56-62`** — read the columns directly:
```php
'tenants' => Tenant::query()->where('is_active', true)
    ->orderBy('name')->get(['id', 'name', 'code'])
    ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'code' => $t->code])
    ->all(),
```
(The filtering here is a placeholder; ticket 02 moves this behind `TenantAccess`.)

**Frontend consumers of `activeTenant`** — all currently render the raw slug and
must resolve a display name once it exists:
`app-sidebar.tsx:193-226` (`TenantChip`), `app-header.tsx:358-366`,
`bottom-nav.tsx:152-159`.

## Verification

- `php artisan migrate:fresh --seed` — `tenants` rows carry `name=Head Office`,
  `code=HQ`, `data = NULL` (proving the JSON path is gone).
- `migrate:rollback --step=1` then `migrate` — round-trips cleanly; backfill then
  `change()` to NOT NULL works on SQLite.
- 6 new `TenantBranchFieldsTest` cases: real columns vs the JSON blob, seeder
  persistence, seeder idempotency, `label()` slug fallback, code uniqueness, archiving.
- `php artisan test` — 87 tests, 1 pre-existing `SamlTest` failure.

## Delivered

**Migration** `2026_10_06_140500_add_branch_fields_to_tenants_table` — adds
`name`/`code`/`is_active` nullable, backfills from `data` (falling back to `id`
for `name`, since `data` is empty on every existing row), then tightens `name` to
NOT NULL and adds a unique index on `code`.

**`app/Models/Tenant.php`** — extends the stock model with `getCustomColumns()`
returning `['id', 'name', 'code', 'is_active']` (the docs require `id` in that list),
an `is_active` boolean cast, and a `label()` helper.

**`creating` hook** — defaults `name` to the slug. Without it, the ~20 existing
`Tenant::query()->firstOrCreate(['id' => 'hq'])` call sites in tests would all
violate the new NOT NULL constraint. Deliberately in the model rather than in
`TenantSeeder`, so any future script or tinker call stays valid.

**`TenantFactory`** with an `inactive()` state, for ticket 04.

**`TenantSeeder`** — now passes columns as top-level attributes. This is the
actual bug fix; the old `['data' => array_diff_key(...))]` payload was being
discarded by `VirtualColumn::encodeAttributes()`.

**Consumers.** `config/tenancy.php` `tenant_model` now points at `App\Models\Tenant`.
`TenantAccess::operableTenants()` sorts by `name`. `HandleInertiaRequests` sends
`label()` + `code`. Three frontend components that were rendering the raw slug
(`app-sidebar` `TenantChip`, `app-header` `HeaderBrand`, `bottom-nav`) now resolve
the display name from the shared `tenants` prop. `TenantChip`'s hardcoded
"Cabang Utama" caption became "Cabang Aktif" — it was wrong for every non-HQ branch.

**One thing worth knowing:** all six test files imported the *stock* `Tenant`
directly. After the model swap they still constructed rows through it, bypassing
`getCustomColumns()` — so `name`/`code` were stripped into the JSON blob and the
NOT NULL insert failed 35 times. Repointed every import at `App\Models\Tenant`.
The stock class is no longer referenced in app or test code.