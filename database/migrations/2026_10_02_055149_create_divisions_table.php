<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('kode_division')->nullable();
            $table->string('nama_division');
            $table->timestamps();

            // `constrained()` is only defined on ForeignIdColumnDefinition, so on a
            // plain string column it silently emits no foreign key at all. Declare
            // the key explicitly or `tenant_id` loses all referential integrity.
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
