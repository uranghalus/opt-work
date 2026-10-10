<?php

namespace App\Queries;

use App\Models\Employee;
use Illuminate\Contracts\Database\Eloquent\Builder;

class EmployeeQuery
{
    private const SORTABLE_COLUMNS = [
        'nik_employee',
        'nama_employee',
        'email',
        'number',
        'created_at',
    ];

    /**
     * Build the query with search, sorting, and eager loading.
     */
    public function build(
        ?string $search,
        string $sort,
        string $direction
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
                'created_at',
                'tenant_id',
            ]);

        // Search across multiple columns
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

        // Validate and apply sort
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'nama_employee';
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction);

        return $query;
    }
}