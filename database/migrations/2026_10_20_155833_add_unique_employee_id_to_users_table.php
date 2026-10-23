<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One akun can belong to at most one karyawan. The relation is hasOne, so
     * a duplicate link makes the resolved account arbitrary.
     *
     * The migration fails loudly instead of deleting data when duplicates
     * already exist. Resolve them manually, then rerun the migration.
     */
    public function up(): void
    {
        $duplicates = DB::table('users')
            ->select('employee_id', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('employee_id')
            ->groupBy('employee_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $details = $duplicates
                ->map(fn (object $row): string => "{$row->employee_id} ({$row->aggregate} akun)")
                ->implode(', ');

            throw new RuntimeException(
                "Tidak dapat menambahkan unique index users.employee_id: duplikat ditemukan -> {$details}. "
                .'Selesaikan duplikat tersebut lalu jalankan ulang migrasi.'
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('employee_id', 'users_employee_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_employee_id_unique');
        });
    }
};
