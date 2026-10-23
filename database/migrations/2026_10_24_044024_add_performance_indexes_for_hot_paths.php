<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index hot-path audit (agent run 2026-10-24 — see REPORT.md):
 *
 * - work_orders.scheduled_date: timeline endpoint filters whereNotNull +
 *   whereBetween + orderBy — was a bare column (full scan per request).
 * - work_orders (status, deadline_date): nightly escalation command filters
 *   status IN (...) AND deadline_date < now().
 * - employees.email: EnsureEmployeeNotBanned looks an employee up by email
 *   on EVERY authenticated request (unscoped, whole-table before).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->index('scheduled_date');
            $table->index(['status', 'deadline_date']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex(['scheduled_date']);
            $table->dropIndex(['status', 'deadline_date']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['email']);
        });
    }
};
