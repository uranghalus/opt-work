<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pemulihan tabel/kolom yang terhapus pada penggabungan cabang:
 *
 * - work_orders.released_at: kapan WO dirilis ke department tujuan — diisi
 *   saat approve gerbang (atau langsung saat create untuk WO satu
 *   department). WorkOrder::boot sudah mengisinya pada creating; kolom
 *   ini wajib ada agar insert tidak gagal (termasuk tabel uji sqlite).
 * - work_orders.request_rejection_reason: alasan tolak departemen pengaju
 *   (R4), sudah dalam $fillable model tetapi belum ada kolomnya.
 * - work_time_logs: timer durasi kerja per pelaksana (R16-R18), dipakai
 *   WorkTimeLogService yang constructor-inject oleh WorkOrderController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable()->after('status');
            $table->text('request_rejection_reason')->nullable()->after('extend_reason');
        });

        // WO lama lahir sebelum gerbang approval — sudah otomatis "dirilis".
        DB::table('work_orders')
            ->whereNull('released_at')
            ->update(['released_at' => DB::raw('created_at')]);

        Schema::create('work_time_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('paused_seconds')->default(0);
            $table->timestamp('paused_at')->nullable();
            $table->json('pause_log')->nullable();
            $table->timestamps();

            $table->unique(['work_order_id', 'employee_id']);
            $table->index(['tenant_id', 'employee_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_time_logs');

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['released_at', 'request_rejection_reason']);
        });
    }
};
