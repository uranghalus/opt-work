<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unit bisnis (cabang) — the multi-tenancy boundary, synced 1:1 from
     * Optigate Companies (see SamlTenantResolver and app:sync-tenants).
     *
     * `id` is a ULID: path-based tenancy keeps the `code` slug in the URL
     * (/{tenant}/work-orders) while `optigate_company_id` is the immutable
     * sync mapping key. Every tenant_id foreign key elsewhere is a plain
     * string column referencing this ULID, declared explicitly —
     * `constrained()` on a plain string column emits no foreign key.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedInteger('optigate_company_id')->nullable()->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
