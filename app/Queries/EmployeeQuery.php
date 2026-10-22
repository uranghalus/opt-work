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
     * Build the query with search, sorting, filters, and eager loading.
     *
     * @param  array{department_id?: ?string, division_id?: ?string, position_id?: ?string, has_account?: ?bool, status?: string}  $filters
     */
    public function build(
        ?string $search,
        string $sort,
        string $direction,
        array $filters = []
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

        $query
            ->when(
                $filters['department_id'] ?? null,
                fn (Builder $q, string $id) => $q->where('department_id', $id)
            )
            ->when(
                $filters['division_id'] ?? null,
                fn (Builder $q, string $id) => $q->where('division_id', $id)
            )
            ->when(
                $filters['position_id'] ?? null,
                fn (Builder $q, string $id) => $q->where('position_id', $id)
            )
            ->when(
                ($filters['has_account'] ?? null) !== null,
                fn (Builder $q) => ($filters['has_account'] ?? false)
                    ? $q->whereHas('user')
                    : $q->whereDoesntHave('user')
            )
            ->when(($filters['status'] ?? 'all') === 'banned', fn (Builder $q) => $q->whereNotNull('banned_at'))
            ->when(($filters['status'] ?? 'all') === 'active', fn (Builder $q) => $q->whereNull('banned_at'));

        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'nama_employee';
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction);

        return $query;
    }
}
