# Stage 1 — MySQL readiness for the branch slug

**What to build:** Make every migration valid on MySQL so the app can connect to and migrate against a MySQL database. One blocker, plus the environment wiring.

**Blocked by:** None.

**Status:** done — verified live against **MariaDB 10.4.32** (XAMPP). MySQL 8 not yet exercised.

- [x] Every `tenant_id` column type-matches `tenants.id`
- [x] Every `tenant_id` foreign key **actually exists** (see "The real blocker" below)
- [x] `migrate` / `migrate:rollback` / `migrate:fresh` round-trip on MariaDB
- [x] `.env.example` targets MySQL with charset and collation
- [x] `.env` flipped to MySQL; app boots and reads seeded data
- [x] Full suite green on MariaDB (104 tests) and SQLite
- [ ] Verified against a live **MySQL 8** server

---

## The blocker

`tenants.id` is a **varchar slug** — path-based tenancy puts it in the URL
(`/{tenant}/work-orders`), see ADR 0002. Every `tenant_id` foreign key was declared
`foreignUuid()`, which compiles to `char(36)`.

SQLite tolerates the mismatch. **MySQL/InnoDB rejects the foreign key outright**:

```
ERROR 3780 (HY000): Referencing column 'tenant_id' and referenced column 'id'
in foreign key constraint are incompatible.
```

The key must reference a `varchar`, not a `char(36)`.

## The real blocker — `constrained()` silently creates nothing

Found on the first live MySQL run. Changing the column types was necessary but **not
sufficient**: `constrained()` is only defined on `ForeignIdColumnDefinition`. On a plain
`ColumnDefinition` (what `string('tenant_id')` returns) it is a no-op that sets an unused
attribute, so **no foreign key command was ever emitted**.

Proof — Laravel's MySQL DDL for `create_divisions_table` before the fix:

```sql
create table `divisions` (`id` char(36) not null, `tenant_id` varchar(255) not null, ..., primary key (`id`))
```

No key. Every `foreignUuid` key in the same schema emitted its `alter table ... add constraint`
correctly, which is what made this look fine on inspection.

This hid behind SQLite twice over: `SQLiteGrammar::compileDropForeign` is an intentional
no-op ("Handled on table alteration"), so the align migration's `dropForeign` neither failed
nor did anything, and the SQLite file picked up its keys only from the align migration's
explicit `foreign()` call. The earlier "clean SQLite round-trip" proved nothing about keys.

Fix — declare the key explicitly in all six create/alter migrations, matching the pattern
already used in `2019_09_15_000020_create_domains_table.php:22`:

```php
$table->string('tenant_id');
$table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
```

`users.tenant_id` is nullable and keeps `nullOnDelete()`. The align migration's `dropForeign`
is now guarded by an `information_schema` lookup, because every database that ran ticket 07
as originally written has the column but **no** key — exactly the state that made
`dropForeign` fail with error 1091.

## Fix

Six create/alter migrations changed `foreignUuid('tenant_id')` → `string('tenant_id')`:
`divisions`, `departments`, `positions`, `employees`, `work_orders`, and the
`users.tenant_id` addition. All other uuid foreign keys are correct and untouched —
`employees.id`, `divisions.id`, `departments.id` are genuinely `uuid`/`char(36)`
(`HasUuids`), so `users.employee_id`, `departments.division_id`,
`work_orders.target_department_id` stay as `foreignUuid`.

Migration `2026_10_06_142556_align_tenant_id_columns_with_tenants_slug` brings
already-migrated databases into line: drop FK → `change()` → re-add FK, with
`cascadeOnDelete` matching the tenant-scoped tables and `nullOnDelete` for the
nullable `users.tenant_id`.

`2019_09_15_000010_create_tenants_table.php` carries a comment explaining why the
primary key must stay varchar, so nobody "fixes" it back to a uuid later.

## Verification

- `migrate:fresh --seed` → clean on MariaDB 10.4.32 (XAMPP), database `opti_works`.
- `migrate:rollback --step=1` → clean, then `migrate` → clean.
- All seven keys present in `information_schema` with the intended delete rule
  (`CASCADE` on the six tenant-scoped tables, `SET NULL` on `users`).
- Full suite: **104 tests, 95 pass, 1 pre-existing `SamlTest` failure, 8 skipped** —
  byte-identical results on MariaDB and SQLite.

**Environment caveat:** the server on this machine reports
`select version()` = **10.4.32-MariaDB**, not MySQL 8. MariaDB enforces foreign key type
matching, so the slug/key pairing is genuinely proven. Index-length limits and the
`utf8mb4_unicode_ci` handling are close but not identical to MySQL 8; run the suite once
against real MySQL 8 before deploying there.

`php artisan migrate --pretend` is the tool that made this findable: it prints the DDL
Laravel would send without needing a clean database, so the missing `add constraint` lines
were visible by reading the output.

Database `opti_works` already existed on the XAMPP server but was **empty** (zero tables),
so `migrate:fresh` was safe. The throwaway `opti_works_test` database is kept for future
manual MySQL suite runs.

## To finish

```bash
# .env
DB_CONNECTION=mysql
DB_HOST=<host> DB_PORT=3306
DB_DATABASE=opti_works DB_USERNAME=<user> DB_PASSWORD=<pass>

php artisan config:clear
php artisan migrate:fresh --seed
```

Then re-run the suite against MySQL rather than SQLite — `phpunit.xml` pins
`DB_CONNECTION=sqlite` with `:memory:`, so the tests will not exercise MySQL until
that is overridden. Worth doing: `RefreshDatabase` on MySQL is slower and index
length limits differ from SQLite.

## Also worth knowing

- **`storage/tenanthq/`** accumulates under the tenancy filesystem suffix. Not
  MySQL-related, but it is why `.gitignore` candidates keep appearing.
- **`tenants.id` is `varchar(255)`**, and several tables carry a composite unique
  including it (e.g. `work_orders` unique on `tenant_id` + `nomor_wo`). Under
  `utf8mb4` that is 1020 bytes for the key column alone. InnoDB's default index
  prefix limit is 3072 bytes with `DYNAMIC` row format, so it fits — but if a
  composite ever grows, watch it.