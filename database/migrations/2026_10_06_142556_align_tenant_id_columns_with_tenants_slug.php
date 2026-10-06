<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['tenant_id']);
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('tenant_id')->change();
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['tenant_id']);
            });

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
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['tenant_id']);
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('tenant_id')->change();
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['tenant_id']);
            });

            Schema::table('users', function (Blueprint $blueprint): void {
                $blueprint->uuid('tenant_id')->nullable()->change();
            });

            Schema::table('users', function (Blueprint $blueprint): void {
                $blueprint->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        }
    }
};
