<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $kode_department
 * @property string|null $nama_department
 * @property int|null $hod_user_id
 * @property string|null $hod_employee_id
 * @property int|null $manager_user_id
 * @property string|null $division_id
 * @property string|null $optigate_department_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['kode_department', 'nama_department', 'hod_user_id', 'hod_employee_id', 'manager_user_id', 'division_id', 'optigate_department_id', 'is_active'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * HOD shown in the UI: prefer the linked user account, fall back to the
     * synced employee when the HOD has not signed in yet.
     */
    protected function hodDisplay(): Attribute
    {
        return Attribute::get(function (): ?array {
            if ($this->hod !== null) {
                return [
                    'name' => $this->hod->name,
                    'email' => $this->hod->email,
                    'photo_url' => $this->hod->employee?->photo_url,
                ];
            }

            if ($this->hodEmployee !== null) {
                return [
                    'name' => $this->hodEmployee->nama_employee,
                    'email' => $this->hodEmployee->email,
                    'photo_url' => $this->hodEmployee->photo_url,
                ];
            }

            return null;
        });
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function hod(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hod_user_id');
    }

    /**
     * Direct link to the employee who is the HOD.
     */
    public function hodEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hod_employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    /**
     * Departments where this employee is the HOD.
     */
    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'hod_employee_id');
    }

    /**
     * @return HasMany<Position, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'department_id');
    }
}
