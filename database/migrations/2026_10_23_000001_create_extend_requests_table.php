<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-2.3–2.5: pengajuan extend deadline WO telat (OQ-9 settled: tabel
 * terpisah `extend_requests`). Approval berjenjang Team Leader (jika ada)
 * → HOD; setiap baris menyimpan audit lengkap: siapa mengaju, siapa
 * menyetujui/menolak, kapan, dan alasannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extend_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('days');
            $table->text('reason');
            $table->string('status', 30);
            $table->foreignId('team_leader_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('team_leader_approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extend_requests');
    }
};
