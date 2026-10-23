<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-2.4 + FR-2.5: Work Planning — detail jadwal per WO Terjadwal. Tanggal
 * jadwal HANYA berpindah lewat Extend Work Schedule dengan approval
 * DGM/GM (alur terpisah dari extend WO telat FR-2.3); audit siapa/kapan/
 * alasan tersimpan di kolom extend_*.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_plannings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            // Satu planning per WO — dibuat otomatis saat review 'schedule'.
            $table->foreignUuid('work_order_id')->unique()->constrained('work_orders')->cascadeOnDelete();
            $table->date('tgl_jadwal');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->string('jenis_pekerjaan', 100)->nullable();
            $table->unsignedInteger('lama_pekerjaan_hari')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            // planned | pending_extend_approval | rescheduled
            $table->string('status_jadwal', 30)->default('planned');
            $table->text('catatan')->nullable();

            // Audit Extend Work Schedule (FR-2.5).
            $table->unsignedInteger('extend_count')->default(0);
            $table->date('original_tgl_jadwal')->nullable();
            $table->date('proposed_tgl_jadwal')->nullable();
            $table->text('extend_reason')->nullable();
            $table->foreignId('extend_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('extend_requested_at')->nullable();
            $table->foreignId('extend_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('extend_approved_at')->nullable();
            $table->text('extend_approval_notes')->nullable();
            $table->timestamps();

            $table->index(['status_jadwal', 'tgl_jadwal']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_plannings');
    }
};
