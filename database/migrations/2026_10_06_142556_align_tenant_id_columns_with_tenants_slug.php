<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables whose `tenant_id` must reference the branch slug, not a uuid.
     *
     * @var array<int, string>
     */
    private const TABLES = ['divisions', 'departments', 'positions', 'employees', 'work_orders'];

    /**
     * Drops the foreign key on `$column` only when it is actually present.
     *
     * The create migrations previously called `constrained()` on a plain string
     * column, which silently emits no foreign key, so databases that ran them have
     * a `tenant_id` column with no constraint at all. Dropping unconditionally fails
     * on MySQL with error 1091 and, on SQLite, hides behind a grammar stub that
     * ignores the statement. Either way the key has to end up present, not absent.
     */
    private function dropForeignKeyIfPresent(string $table, string $column): void
    {
        if (! $this->foreignKeyExists($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropForeign([$column]);
        });
    }

    /**
     * Whether a foreign key constrains `$table.$column`.
     *
     * Only MySQL/MariaDB are checked against the catalogue. SQLite's
     * `compileDropForeign` is an intentional no-op, so reporting `true` there keeps
     * the drop call in the code path without changing the outcome.
     */
    private function foreignKeyExists(string $table, string $column): bool
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return true;
        }

        return DB::selectOne(
            'select 1 as `present` from information_schema.key_column_usage
             where table_schema = database() and table_name = ? and column_name = ?
               and referenced_table_name is not null',
            [$table, $column],
        ) !== null;
    }

    /**
     * Run the migrations.
     *
     * `tenants.id` is a varchar slug (`hq`, `plant-1`) because path-based tenancy
     * puts it in the URL — see ADR 0002. Every `tenant_id` foreign key was
     * declared `foreignUuid`, which MySQL compiles to `char(36)`. SQLite accepts
     * the mismatch; MySQL/InnoDB rejects the foreign key outright (error 3780,
     * "referencing column ... and referenced column ... are incompatible").
     *
     * The create migrations are corrected in place. This brings databases that
     * already ran them into line.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->dropForeignKeyIfPresent($table, 'tenant_id');

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('tenant_id')->change();
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('users', 'tenant_id')) {
            $this->dropForeignKeyIfPresent('users', 'tenant_id');

            Schema::table('users', function (Blueprint $blueprint): void {
                $blueprint->string('tenant_id')->nullable()->change();
            });

            Schema::table('users', function (Blueprint $blueprint): void {
                $blueprint->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Drops the corrected foreign keys and restores the previous uuid column
     * types. Only reversible on SQLite: MySQL never had the mismatched state
     * to begin with, and will not re-create it.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $this->dropForeignKeyIfPresent($table, 'tenant_id');

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('tenant_id')->change();
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('users', 'tenant_id')) {
            $this->dropForeignKeyIfPresent('users', 'tenant_id');

            Schema::table('users', function (Blueprint $blueprint): void {
                $blueprint->uuid('tenant_id')->nullable()->change();
            });

            Schema::table('users', function (Blueprint $blueprint): void {
                $blueprint->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        }
    }
};
