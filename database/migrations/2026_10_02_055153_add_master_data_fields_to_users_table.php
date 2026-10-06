<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('password');
            $table->foreignUuid('employee_id')->after('phone')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('tenant_id')->after('employee_id')->nullable()->constrained('tenants')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['phone', 'employee_id', 'tenant_id']);
        });
    }
};
