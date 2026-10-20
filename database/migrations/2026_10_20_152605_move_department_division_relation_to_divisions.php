<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move the department/division link to the portal-aligned direction:
     * divisions.department_id (nullable, direct divisions have no parent).
     */
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->uuid('department_id')->nullable()->after('tenant_id');
            $table->boolean('is_direct')->default(false)->after('department_id');
            $table->index('department_id');
        });

        // The old direction can only be reversed when exactly one department
        // pointed at a division. Ambiguous rows are left unlinked rather than
        // guessed; the next portal sync will supply the canonical value.
        $oldLinks = DB::table('departments')
            ->whereNotNull('division_id')
            ->get(['id', 'division_id'])
            ->groupBy('division_id');

        foreach ($oldLinks as $divisionId => $departments) {
            if ($departments->count() !== 1) {
                Log::warning('Department/division migration: ambiguous old link skipped.', [
                    'division_id' => $divisionId,
                    'department_count' => $departments->count(),
                ]);

                continue;
            }

            DB::table('divisions')
                ->where('id', $divisionId)
                ->update(['department_id' => $departments->first()->id]);
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropColumn('division_id');
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    /**
     * Restore the legacy departments.division_id direction best-effort.
     */
    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->uuid('division_id')->nullable()->after('manager_user_id');
        });

        $newLinks = DB::table('divisions')
            ->whereNotNull('department_id')
            ->get(['id', 'department_id'])
            ->groupBy('department_id');

        foreach ($newLinks as $departmentId => $divisions) {
            if ($divisions->count() !== 1) {
                Log::warning('Department/division rollback: ambiguous link skipped.', [
                    'department_id' => $departmentId,
                    'division_count' => $divisions->count(),
                ]);

                continue;
            }

            DB::table('departments')
                ->where('id', $departmentId)
                ->update(['division_id' => $divisions->first()->id]);
        }

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn(['department_id', 'is_direct']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
        });
    }
};
