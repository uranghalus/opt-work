<?php

namespace App\Queries;

use App\Models\Employee;
use Illuminate\Contracts\Database\Eloquent\Builder;

class EmployeeQuery
{
    public const SORTABLE_COLUMNS = [
        'nik_employee',
        'nama_employee',
        'email',
        'number',
        'created_at',
    ];

    /**
     * Build the query with search, filters, sorting, and eager loading.
     */
    public function build(
        ?string $search,
        string $sort,
        string $direction,
        ?string $departmentId = null,
        ?string $divisionId = null,
        ?string $status = null,
        ?string $akun = null,
    ): Builder {
        $query = Employee::query()
            ->with(['department', 'position', 'division', 'user'])
            ->select([
                'id',
                'nik_employee',
                'nama_employee',
                'email',
                'number',
                'photo_url',
                'department_id',
                'position_id',
                'division_id',
                'banned_at',
                'created_at',
                'tenant_id',
            ]);

        if ($search !== null && $search !== '') {
            $searchTerm = "%{$search}%";
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->where('nik_employee', 'like', $searchTerm)
                    ->orWhere('nama_employee', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('number', 'like', $searchTerm)
                    ->orWhereHas('department', fn (Builder $dq) => $dq->where('kode_department', 'like', $searchTerm)
                        ->orWhere('nama_department', 'like', $searchTerm))
                    ->orWhereHas('position', fn (Builder $pq) => $pq->where('nama_position', 'like', $searchTerm))
                    ->orWhereHas('division', fn (Builder $dq) => $dq->where('kode_division', 'like', $searchTerm)
                        ->orWhere('nama_division', 'like', $searchTerm));
            });
        }

        if ($departmentId !== null && $departmentId !== '') {
            $query->where('department_id', $departmentId);
        }

        if ($divisionId !== null && $divisionId !== '') {
            $query->where('division_id', $divisionId);
        }

        // Ban status: "active" = not banned, "banned" = banned_at set. The
        // column is nullable, so active is expressed as NULL.
        match ($status) {
            'banned' => $query->whereNotNull('banned_at'),
            'active' => $query->whereNull('banned_at'),
            default => null,
        };

        // Akun link: has a linked user account or not (hasOne via employee_id).
        match ($akun) {
            'linked' => $query->whereHas('user'),
            'unlinked' => $query->whereDoesntHave('user'),
            default => null,
        };

        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'nama_employee';
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction);

        return $query;
    }
}
