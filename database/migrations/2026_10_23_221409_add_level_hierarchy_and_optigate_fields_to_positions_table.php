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
        Schema::table('positions', function (Blueprint $table) {
            // Level (eselon 1-7) from OptiGate
            $table->unsignedTinyInteger('level')->nullable()->after('division_id');

            // Level label from OptiGate (e.g., "Manager", "Supervisor / Lead", "Staff / Pelaksana")
            $table->string('level_label')->nullable()->after('level');

            // Parent position for hierarchy (self-referencing)
            $table->foreignUuid('parent_position_id')->nullable()->constrained('positions')->nullOnDelete()->after('level_label');

            // Whether position is directly under company (no department) - from OptiGate
            $table->boolean('is_direct')->default(false)->after('parent_position_id');

            // Whether position has no employees (computed but stored for filtering)
            $table->boolean('is_vacant')->default(true)->after('is_direct');

            // Level label from OptiGate (e.g., "Manager", "Supervisor / Lead", "Staff / Pelaksana")
            // Note: level_label already added above
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropForeign(['parent_position_id']);
            $table->dropColumn([
                'level',
                'level_label',
                'parent_position_id',
                'is_direct',
                'is_vacant',
            ]);
        });
    }
};
