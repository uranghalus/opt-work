<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R15: lokasi pekerjaan pada Work Order — opsional, diisi pemohon saat
 * create/revisi, ditampilkan pada kartu "Tugasan Saya" pelaksana dan
 * detail WO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('location')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
