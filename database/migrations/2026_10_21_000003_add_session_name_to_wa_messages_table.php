<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit pengiriman (ADR-0009 amendmen, slice 2 per-tenant): snapshot nama
 * sesi/nomor yang benar-benar dipakai tiap baris outbox, plus index gabungan
 * untuk query ber-scope tenant (UI admin & diagnosa isolasi antar tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->string('session_name', 100)->nullable()->after('chat_id');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn('session_name');
        });
    }
};
