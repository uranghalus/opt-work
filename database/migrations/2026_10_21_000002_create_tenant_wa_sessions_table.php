<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesi/nomor WhatsApp per tenant pada SATU instance WAHA bersama
 * (ADR-0009 amendmen 2026-10-21: sesi global diganti per-tenant).
 *
 * Satu baris per tenant (tenant_id unique) — ganti nomor = putus sesi lalu
 * daftar ulang. session_name unique global karena nama sesi berlaku
 * se-Instance WAHA, bukan per tenant.
 *
 * status diperbarui oleh command terjadwal `app:waha-sync-sessions` (polling
 * keluar), BUKAN webhook masuk — permukaan serang tetap nol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_wa_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('session_name', 100);
            $table->string('phone_number', 30)->nullable();
            $table->string('status', 20)->default('stopped');
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->unique('session_name');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_wa_sessions');
    }
};
