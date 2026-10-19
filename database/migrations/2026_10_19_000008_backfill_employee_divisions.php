<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For each tenant, build maps of portal IDs to local IDs, then backfill
        // employee division_id from their position's division.
        Tenant::whereNotNull('optigate_company_id')->chunkById(100, function ($tenants) {
            foreach ($tenants as $tenant) {
                // Positions that have a division_id filled.
                $positionsWithDivision = Position::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->whereNotNull('division_id')
                    ->pluck('id', 'optigate_position_id')
                    ->toArray();

                // Employees whose position maps to a division.
                $updates = Employee::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->whereNotNull('position_id')
                    ->whereNull('division_id')
                    ->whereHas('position', function ($q) use ($positionsWithDivision) {
                        $q->whereIn('optigate_position_id', array_keys($positionsWithDivision));
                    })
                    ->get();

                foreach ($updates as $employee) {
                    $positionId = $employee->position_id;
                    $position = Position::withoutGlobalScopes()->find($positionId);
                    if ($position && $position->division_id) {
                        Employee::withoutGlobalScopes()
                            ->where('id', $employee->id)
                            ->update(['division_id' => $position->division_id]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Do not rollback: we cannot reverse the mapping reliably.
    }
};
