<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assignee storage for WO slice 1 (issue 03): the assigned karyawan
 * pelaksana list follows the opti-work2 reference (json
 * `assigned_employees` on the work order) — work_data_pekerja rows come
 * later with Work Data Management (issue 04/08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->json('assigned_employees')->nullable()->after('hod_notes');
            $table->text('assignment_notes')->nullable()->after('assigned_employees');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['assigned_employees', 'assignment_notes']);
        });
    }
};
