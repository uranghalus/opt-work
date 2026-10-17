<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            // Stable Optigate identity so the portal sync is idempotent
            // (portal department id). Local uuid stays the primary key.
            $table->string('optigate_department_id', 36)->nullable();

            $table->unique(['tenant_id', 'optigate_department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'optigate_department_id']);
            $table->dropColumn('optigate_department_id');
        });
    }
};
