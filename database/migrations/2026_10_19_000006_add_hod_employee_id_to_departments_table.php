<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignUuid('hod_employee_id')->nullable()->after('hod_user_id')
                ->constrained('employees')->nullOnDelete();
            $table->index('hod_employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['hod_employee_id']);
            $table->dropIndex(['hod_employee_id']);
            $table->dropColumn('hod_employee_id');
        });
    }
};
