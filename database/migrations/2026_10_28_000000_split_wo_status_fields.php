<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Split the Work Order model's single `status` field into two:
     * `status_tiket` (ticket level) and `status_pekerjaan` (job/execution
     * level). The existing `status` column is kept for backward
     * compatibility during transition.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->string('status_tiket', 50)->nullable()->after('status');
            $table->string('status_pekerjaan', 50)->nullable()->after('status_tiket');
        });

        // Backfill legacy `status` values into the new dual-status columns.
        DB::table('work_orders')->whereNull('status_tiket')
            ->whereIn('status', ['waiting_hod', 'hod_approved', 'scheduled'])
            ->update(['status_tiket' => DB::raw('status')]);

        DB::table('work_orders')->whereNull('status_tiket')
            ->where('status', 'assigned')
            ->update(['status_tiket' => 'hod_approved']);

        DB::table('work_orders')->whereNull('status_pekerjaan')
            ->where('status', 'assigned')
            ->update(['status_pekerjaan' => 'assigned']);

        DB::table('work_orders')->whereNull('status_pekerjaan')
            ->whereIn('status', ['in_progress', 'pending_verify', 'revision'])
            ->update(['status_pekerjaan' => DB::raw('status')]);

        DB::table('work_orders')->whereNull('status_tiket')
            ->where('status', 'closed')
            ->update(['status_tiket' => 'closed']);

        DB::table('work_orders')->whereNull('status_tiket')
            ->where('status', 'cancelled')
            ->update(['status_tiket' => 'cancelled']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn(['status_tiket', 'status_pekerjaan']);
        });
    }
};
