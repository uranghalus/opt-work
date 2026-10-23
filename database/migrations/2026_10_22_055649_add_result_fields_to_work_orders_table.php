<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // FR-1.7: hasil pelaksanaan dari pelaksana (karyawan pelaksana) —
            // attempt terakhir saja; histori attempt di Work Data (FR-3.x).
            $table->text('result_notes')->nullable();
            $table->json('result_attachments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['result_notes', 'result_attachments']);
        });
    }
};
