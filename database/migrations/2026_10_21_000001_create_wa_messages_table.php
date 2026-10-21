<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox pengiriman WhatsApp (ADR-0009, grill 2026-10-21 G5): satu baris per
 * pesanan kirim ke satu penerima, dengan status hidupnya (pending → sent /
 * failed / skipped). Pengganti fire-and-forget blueprint opti-work2 yang hanya
 * menulis Log saat gagal.
 *
 * Sengaja TANPA trait BelongsToTenant: baris ditulis dari dalam transaksi
 * bisnis dan dibaca kembali oleh job di queue yang bisa berjalan tanpa konteks
 * tenant saat ini (mis. hasil command eskalasi). tenant_id disimpan sebagai
 * data, sama seperti kolom tenant_id pada tabel notifications yang juga
 * global-scope-free.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->foreignUuid('work_order_id')->nullable()->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 50);
            $table->string('phone')->nullable();
            $table->string('chat_id')->nullable();
            $table->text('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('work_order_id');
            // tenant_id string (ULID tenants.id), bukan uuid — sama seperti
            // work_order_events.
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
    }
};
