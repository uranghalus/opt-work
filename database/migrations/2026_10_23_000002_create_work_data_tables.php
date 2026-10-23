<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-3.1–3.3: Work Data Management — satu record per WO closed (snapshot
 * data turunan WO + pekerja), append-only: satu finalisasi analitik
 * pasca-close, setelah itu beku selamanya. Penamaan mengikuti konvensi
 * Laravel (bukan prefix fld_ atau tb_ dari ERD lama — lihat AGENTS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_data', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            // Satu record per WO — WO closed bersifat terminal (OQ revisi
            // post-closing belum settled; tidak ada reopen di MVP).
            $table->foreignUuid('work_order_id')->unique()->constrained('work_orders')->cascadeOnDelete();
            $table->string('no_kerja');
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('status_pekerjaan', 30)->default('selesai');
            $table->date('tgl_mulai_aktual')->nullable();
            $table->date('tgl_selesai_aktual')->nullable();
            $table->decimal('jam_pengerjaan', 8, 2)->nullable();
            $table->json('gambar_sebelum')->nullable();
            $table->json('gambar_sesudah')->nullable();
            $table->text('prediksi_penyebab')->nullable();
            $table->text('tindakan')->nullable();
            $table->text('hasil_kesimpulan')->nullable();
            $table->text('saran_solusi')->nullable();
            $table->foreignId('pic_lead_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status_pekerjaan']);
            $table->index('tgl_selesai_aktual');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('work_data_pekerja', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreignUuid('work_data_id')->constrained('work_data')->cascadeOnDelete();
            // Snapshot nama tetap tersimpan meski employee dihapus.
            $table->foreignUuid('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('nama_employee');
            $table->string('role_pekerja', 50)->default('pelaksana');
            $table->string('catatan', 255)->nullable();
            $table->timestamps();

            $table->unique(['work_data_id', 'employee_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_data_pekerja');
        Schema::dropIfExists('work_data');
    }
};
