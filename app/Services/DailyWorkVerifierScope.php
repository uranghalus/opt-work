<?php

namespace App\Services;

use App\Models\DailyWork;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Scope verifikasi Daily Work (plan .scratch/daily-work-verification/
 * plan.md Task 3): admin tenant, hod/manager department karyawan, atau
 * Team Leader department yang sama dengan karyawan (settled OQ-5 —
 * department dianggap punya TL bila ada user ber-role team_leader di
 * department itu, dari employee-link). Owner tidak memverifikasi hasil
 * kerjanya sendiri; GM/DGM sampai di sini (keputusan desain).
 */
class DailyWorkVerifierScope
{
    /**
     * Boleh memverifikasi / mengembalikan item ini?
     */
    public function canVerify(User $user, DailyWork $work): bool
    {
        if ($user->hasRole('admin_tenant')) {
            return true;
        }

        $department = $work->employee?->department;

        if ($department !== null && (
            $department->hod_user_id === $user->getKey()
            || $department->manager_user_id === $user->getKey()
        )) {
            return true;
        }

        return $this->hasSameDepartmentTeamLeaderRole($user, $work);
    }

    /**
     * Seluruh pemverifikasi potensial untuk item — hod + manager
     * department ∪ TL department sama, unique. Dipakai notifikasi
     * Task 4 (fallback rantai HOD ditangani di sana bila hasil kosong).
     *
     * @return Collection<int, User>
     */
    public function verifiersFor(DailyWork $work): Collection
    {
        $departmentId = $work->employee?->department_id;

        $verifierIds = collect();

        $department = $departmentId !== null ? Department::query()->find($departmentId) : null;

        if ($department?->hod_user_id !== null) {
            $verifierIds->push($department->hod_user_id);
        }

        if ($department?->manager_user_id !== null) {
            $verifierIds->push($department->manager_user_id);
        }

        if ($departmentId !== null) {
            $teamLeaders = User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'team_leader'))
                ->whereHas('employee', fn ($query) => $query->where('department_id', $departmentId))
                ->pluck('id');

            $verifierIds = $verifierIds->merge($teamLeaders);
        }

        return User::query()
            ->whereIn('id', $verifierIds->unique()->values())
            ->get();
    }

    /**
     * Id seluruh karyawan yang itemnya PANTAS verifikasi user (untuk
     * inbox "Perlu Verifikasi"): department yang dipimpin (hod_user_id /
     * manager_user_id) ∪ department TL (bila user ber-role team_leader
     * dan ter-link karyawan); admin tenant → semua karyawan tenant.
     *
     * @return Collection<int, string>
     */
    public function verifiableEmployeeIds(User $user): Collection
    {
        if ($user->hasRole('admin_tenant')) {
            return Employee::query()->pluck('id');
        }

        $departmentIds = Department::query()
            ->where('hod_user_id', $user->getKey())
            ->orWhere('manager_user_id', $user->getKey())
            ->pluck('id');

        $employee = $user->employee;

        if ($employee !== null
            && $employee->department_id !== null
            && $user->hasRole('team_leader')
        ) {
            $departmentIds = $departmentIds
                ->push($employee->department_id)
                ->unique()
                ->values();
        }

        if ($departmentIds->isEmpty()) {
            return collect();
        }

        return Employee::query()
            ->whereIn('department_id', $departmentIds)
            ->pluck('id');
    }

    /**
     * TL department yang sama dengan department karyawan pemilik item.
     * TL tanpa employee ter-link tidak pernah memverifikasi (desain).
     */
    private function hasSameDepartmentTeamLeaderRole(User $user, DailyWork $work): bool
    {
        if (! $user->hasRole('team_leader')) {
            return false;
        }

        $employee = $user->employee;

        if ($employee === null || $employee->department_id === null) {
            return false;
        }

        $workDepartmentId = $work->employee?->department_id;

        return $workDepartmentId !== null
            && (string) $employee->department_id === (string) $workDepartmentId;
    }
}
