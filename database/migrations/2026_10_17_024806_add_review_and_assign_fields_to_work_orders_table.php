<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HOD review + assign fields (WO slice 1, issue 03). Naming follows the
 * opti-work2 reference (`hod_action` / `scheduled_date` / `assigned_at`);
 * the deadline + escalation columns already exist on this table since the
 * create migration — only the review/assign fields are new here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('hod_action', 30)->nullable()->after('status');
            $table->date('scheduled_date')->nullable()->after('hod_action');
            $table->timestamp('assigned_at')->nullable()->after('scheduled_date');
            $table->text('hod_notes')->nullable()->after('assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['hod_action', 'scheduled_date', 'assigned_at', 'hod_notes']);
        });
    }
};
