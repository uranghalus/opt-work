<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gerbang approval lintas-department (R1–R12) + timer durasi kerja (R16–R18):
 *
 * - work_orders.released_at: kapan WO dilepas ke department tujuan — diisi
 *   saat approver department pengaju menyetujui (atau langsung saat create
 *   untuk WO satu department / requester tanpa department). NULL = belum
 *   pernah keluar dari department pengaju → dasar larangan visibilitas
 *   department tujuan (R8). Backfill: seluruh WO lama lahir di alur lama
 *   (langsung ke dept tujuan) → released_at = created_at.
 * - work_orders.request_rejection_reason: alasan tolak yang wajib (R4),
 *   tampil di tracker requester (R19), dibersihkan saat resubmit.
 * - work_time_logs: satu baris per (WO, pelaksana) — timestamp
 *   diterima/mulai/selesai + akumulasi jeda (paused_seconds, paused_at
 *   segmen aktif, pause_log riwayat). Semua dihitung server (R17) agar
 *   tetap benar setelah refresh / di perangkat lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable()->after('status');
            $table->text('request_rejection_reason')->nullable()->after('extend_reason');
        });

        // WO lama lahir sebelum gerbang — sudah otomatis "dirilis".
        DB::table('work_orders')
            ->whereNull('released_at')
            ->update(['released_at' => DB::raw('created_at')]);

        Schema::create('work_time_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            // Nullable — nullOnDelete: hapus employee tidak menghapus log timer.
            $table->foreignUuid('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            // Akumulasi total jeda (detik) dari seluruh segmen pause/resume.
            $table->unsignedInteger('paused_seconds')->default(0);
            // Segmen jeda yang masih berjalan (null = sedang tidak dipause).
            $table->timestamp('paused_at')->nullable();
            // Riwayat segmen jeda: [{paused_at, resumed_at}] — append-only.
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
