<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail durable untuk aksi hak-akses/akun (grill 2026-10-21, keputusan
 * #4): pengganti Log::info yang tidak bisa di-query. Cakupan awal sengaja
 * sempit — akun.link, akun.unlink, akun.roles.sync, employee.ban,
 * employee.unban.
 *
 * Aturan tulis: baris ditulis DI DALAM transaksi bisnis yang sama dengan
 * aksinya (atomic — audit gagal berarti aksi gagal). Karena itu tabel ini,
 * seperti wa_messages, TIDAK memakai BelongsToTenant: tenant_id disimpan
 * sebagai data, dan pembaca (superadmin/auditor) tidak harus berada di konteks
 * tenant yang sama dengan penulis.
 *
 * actor_user_id nullable = aksi sistem (mis. auto-assign karyawan saat link),
 * bukan aktor manusia. before/after json nullable; reason untuk alasan
 * eksplisit (input UI menyusul — kolom disiapkan lebih dulu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // tenant_id string (ULID tenants.id), sama seperti wa_messages.
            $table->string('tenant_id')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            // Morph generik: employee (uuid) maupun user (bigint) — kolom
            // string menampung keduanya.
            $table->string('auditable_type');
            $table->string('auditable_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index(['auditable_type', 'auditable_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
