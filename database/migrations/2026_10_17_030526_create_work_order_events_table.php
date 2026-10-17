<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only history per work order (issue 03 checkbox 4: siapa, kapan,
 * aksi). opti-work2 kept only last-state snapshot columns; this rebuild
 * records every transition as an event row instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'created_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_events');
    }
};
