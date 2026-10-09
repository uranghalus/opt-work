<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Recreates tenants table with:
     * - id: ULID (primary key)
     * - optigate_company_id: integer (unique, from Optigate API)
     * - code: string (from API, used as URL slug)
     * - name: string
     * - is_active: boolean
     * - deactivated_at: timestamp (nullable)
     * - timestamps
     *
     * Also updates foreign keys in related tables to reference the new ULID id.
     */
    public function up(): void
    {
        // Drop foreign keys first
        $tables = ['divisions', 'departments', 'positions', 'employees', 'work_orders', 'user_tenants', 'jobs'];
        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                $this->dropForeignKeyIfPresent($table, 'tenant_id');
            }
        }

        // Drop and recreate tenants table
        Schema::dropIfExists('tenants');

        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedInteger('optigate_company_id')->nullable()->unique();
            $table->string('code')->unique(); // from API, used as URL slug
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });

        // Recreate foreign keys pointing to tenants.id (ULID)
        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->ulid('tenant_id')->nullable()->change();
                });

                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                });
            }
        }

        // users.tenant_id is nullable FK
        if (Schema::hasColumn('users', 'tenant_id')) {
            $this->dropForeignKeyIfPresent('users', 'tenant_id');
            Schema::table('users', function (Blueprint $blueprint) {
                $blueprint->ulid('tenant_id')->nullable()->change();
            });
            Schema::table('users', function (Blueprint $blueprint) {
                $blueprint->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible in a meaningful way since we drop the table
        Schema::dropIfExists('tenants');
    }

    private function dropForeignKeyIfPresent(string $table, string $column): void
    {
        if (! $this->foreignKeyExists($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropForeign([$column]);
        });
    }

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
};