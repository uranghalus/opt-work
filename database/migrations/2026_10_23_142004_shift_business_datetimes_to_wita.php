<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D2 — one-off data move alongside the app.timezone switch (UTC →
 * Asia/Makassar). Every datetime column below stores instants written by an
 * app that ran on UTC; shifting them -8 hours converts the stored values to
 * the WITA reading of the same instant, so new writes on the Asia/Makassar
 * clock continue the same series instead of mixing two clocks.
 *
 * Excluded — runtime/transient stores whose "now" is produced and consumed
 * by infrastructure (sessions, cache*, jobs, job_batches) or only for retry
 * bookkeeping (failed_jobs): their rows are short-lived and not part of the
 * business timeline.
 * Excluded by type — DATE-only columns (deadline_date, scheduled_date,
 * requested_schedule_date) hold calendar days, not instants; shifting them
 * would move whole dates, which is wrong.
 *
 * Column list captured from information_schema (dev DB `opti_works`,
 * 2026-10-23, COLUMN_TYPE REGEXP '^(datetime|timestamp)').
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $columns = [
        'audit_events' => ['created_at', 'updated_at'],
        'departments' => ['created_at', 'updated_at'],
        'divisions' => ['created_at', 'updated_at'],
        'employees' => ['banned_at', 'created_at', 'updated_at'],
        'extend_requests' => ['approved_at', 'created_at', 'rejected_at', 'team_leader_approved_at', 'updated_at'],
        'notifications' => ['created_at', 'read_at', 'updated_at'],
        'password_reset_tokens' => ['created_at'],
        'permissions' => ['created_at', 'updated_at'],
        'positions' => ['created_at', 'updated_at'],
        'roles' => ['created_at', 'updated_at'],
        'tenant_wa_sessions' => ['created_at', 'last_connected_at', 'last_synced_at', 'updated_at'],
        'tenants' => ['created_at', 'deactivated_at', 'last_department_synced_at', 'last_division_synced_at', 'updated_at'],
        'user_tenants' => ['created_at', 'updated_at'],
        'users' => ['created_at', 'email_verified_at', 'updated_at'],
        'wa_messages' => ['created_at', 'sent_at', 'updated_at'],
        'work_data' => ['created_at', 'finalized_at', 'updated_at'],
        'work_data_pekerja' => ['created_at', 'updated_at'],
        'work_order_events' => ['created_at', 'updated_at'],
        'work_orders' => ['assigned_at', 'created_at', 'escalation_h3_sent_at', 'escalation_h5_sent_at', 'escalation_h6_sent_at', 'extended_at', 'updated_at'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // Tests run on sqlite against an in-memory database whose rows are
            // written on the WITA clock already (the config switched in the
            // same change) — there is nothing to move.
            return;
        }

        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("UPDATE `{$table}` SET `{$column}` = `{$column}` - INTERVAL 8 HOUR");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-off data correction: reversing (+8h) would double-shift rows
        // written after this ran, so it is intentionally irreversible.
    }
};
