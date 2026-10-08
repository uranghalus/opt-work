<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The tenant_id FK on `users` is already created by
        // 2026_10_02_055153_add_master_data_fields_to_users_table, and the
        // tenant-scoped tables (divisions, departments, positions, employees,
        // work_orders) are aligned by 2026_10_06_142556_align_tenant_id_columns_
        // with_tenants_slug. Only the pivot here still needs its constraint.
        if (Schema::hasColumn('user_tenants', 'tenant_id')) {
            Schema::table('user_tenants', function (Blueprint $table) {
                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_tenants', 'tenant_id')) {
            Schema::table('user_tenants', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
        }
    }
};