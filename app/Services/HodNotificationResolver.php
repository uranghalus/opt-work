<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class HodNotificationResolver
{
    /**
     * Rantai fallback penerima notifikasi untuk department tujuan:
     * hod_user_id → manager_user_id (deputy) → semua user ber-flag is_hod di
     * department tsb. Jika tetap kosong → Admin Tenant cabang (is_super_admin
     * dengan tenant_id tsb) + log kritikal.
     *
     * @return Collection<int, User>
     */
    public function resolve(Department $department): Collection
    {
        $recipients = collect()
            ->merge($this->usersById($department->hod_user_id))
            ->merge($this->usersById($department->manager_user_id));

        if ($recipients->isEmpty()) {
            $recipients = User::query()
                ->where('is_hod', true)
                ->whereHas('employee', fn ($query) => $query->where('department_id', $department->getKey()))
                ->get();
        }

        if ($recipients->isEmpty()) {
            $recipients = User::query()
                ->where('is_super_admin', true)
                ->where('tenant_id', $department->tenant_id)
                ->get();

            Log::critical('Fallback HOD: tidak ada penerima notifikasi untuk department tujuan.', [
                'department_id' => $department->getKey(),
                'tenant_id' => $department->tenant_id,
            ]);
        }

        return $recipients->unique('id');
    }

    /**
     * @return Collection<int, User>
     */
    private function usersById(?int $userId): Collection
    {
        if ($userId === null) {
            return collect();
        }

        return User::query()->whereKey($userId)->get();
    }
}
