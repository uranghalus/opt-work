<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            // Record-level block on a karyawan; the portal sync never touches it.
            $table->timestamp('banned_at')->nullable();

            // Stable Optigate identity so the portal sync is idempotent
            // (portal user uuid). Local uuid stays the primary key.
            $table->uuid('optigate_user_id')->nullable();

            $table->unique(['tenant_id', 'optigate_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'optigate_user_id']);
            $table->dropColumn(['banned_at', 'optigate_user_id']);
        });
    }
};
