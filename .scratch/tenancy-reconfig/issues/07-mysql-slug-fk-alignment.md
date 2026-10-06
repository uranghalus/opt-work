# Stage 1 — MySQL readiness for the branch slug

**What to build:** Make every migration valid on MySQL so the app can connect to and migrate against a MySQL database. One blocker, plus the environment wiring.

**Blocked by:** None.

**Status:** code complete, **live verification pending — no MySQL server on this machine**

- [x] Every `tenant_id` column type-matches `tenants.id`
- [x] `migrate` / `migrate:rollback` / `migrate:fresh` round-trip on the current driver
- [x] `.env.example` targets MySQL with charset and collation
- [ ] Verified against a live MySQL 8 server (`php artisan migrate:fresh`)

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

## Verification — and its limit

- `migrate:fresh --seed` → clean.
- `migrate:rollback --step=1` → clean, then `migrate` → clean.
- Full suite: 87 tests, 78 pass, 1 pre-existing `SamlTest` failure.

**What I could not verify:** there is no MySQL server, `docker`, or `mysql` client
on this machine. `pdo_mysql` is loaded, so PHP *can* talk to MySQL — but nothing
here proves the migrations run against one. `php artisan migrate --pretend` does not
help: it still opens a PDO connection, and it failed with
`No connection could be made because the target machine actively refused it`.

I started a `MysqlDdl` test helper that compiles the blueprints through
`MySqlGrammar` without a server, then **removed it** — `Blueprint` needs a booted
connection to resolve its grammar, and building a fake one was more machinery than
the check was worth. The type-pairing fix is verified by reading the migrations and
by the SQLite round-trip; **it still needs one live run against MySQL 8.**

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