<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail durable (rencana 2026-10-22 langkah 1): satu baris per aksi
 * sensitif (link/unlink/ban/unban/role-sync fase 1) dengan actor, target,
 * tenant, dan snapshot before/after. Append-only — tidak ada jalur update/
 * delete di aplikasi.
 *
 * Sengaja TANPA trait BelongsToTenant (mengikuti alasan wa_messages):
 * tenant_id disimpan sebagai data agar baris tetap terbaca tanpa konteks
 * tenant (audit reader, command), dan aksi platform/role yang hidup di luar
 * prefix {tenant} tidak salah di-scope. Kolom subject sengaja string biasa
 * (bukan foreignUuid): subject bisa UUID (employees) maupun bigint (users).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_email')->nullable();
            $table->string('subject_type');
            $table->string('subject_id');
            $table->string('action', 40);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            // Baca normal: riwayat satu subject, atau listing per tenant
            // terbaru-dulu.
            $table->index(['subject_type', 'subject_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
