<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-5.1–5.4: Daily Work — template tugas rutin per karyawan (dikelola HOD,
 * OQ-4 opsi A) + instance harian (status/lokasi per item). Materialize-on-view:
 * item template aktif di-upsert idempoten ke daily_works saat daftar tanggal D
 * dibuka — unique (template_id, work_date) mencegah dobel, kolom progres tidak
 * pernah ditimpa. Penamaan mengikuti konvensi Laravel (bukan tb_/fld_ ERD lama;
 * tb_work_daily repo lama = konsep berbeda: log harian per eksekusi WO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_work_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreignUuid('employee_id')->constrained('employees')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // Hari berulang: int 0 (Minggu) .. 6 (Sabtu), default Senin–Jumat.
            $table->json('weekdays');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('daily_works', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreignUuid('employee_id')->constrained('employees')->nullOnDelete();
            $table->date('work_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            // pending (belum) | in_progress (proses) | done (selesai).
            $table->string('status', 20)->default('pending');
            $table->timestamp('done_at')->nullable();
            // template | adhoc — adhoc = tugas tambahan HOD (notifikasi).
            $table->string('source', 20)->default('adhoc');
            // Lineage: instance materialize menunjuk template asalnya.
            // nullOnDelete — hapus template tidak menghapus riwayat harian.
            $table->foreignUuid('template_id')->nullable()->constrained('daily_work_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'work_date']);
            // Idempotensi materialize: satu instance per template per tanggal.
            // MySQL mengizinkan banyak NULL (adhoc) dalam unique ini.
            $table->unique(['template_id', 'work_date']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_works');
        Schema::dropIfExists('daily_work_templates');
    }
};
