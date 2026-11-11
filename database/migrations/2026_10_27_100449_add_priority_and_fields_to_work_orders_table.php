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
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('priority')->nullable()->after('title');
            $table->unsignedInteger('personnel_count')->nullable()->after('priority');
            $table->string('damage_type')->nullable()->after('personnel_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('priority');
            $table->dropColumn('personnel_count');
            $table->dropColumn('damage_type');
        });
    }
};