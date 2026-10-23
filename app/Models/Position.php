<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $nama_position
 * @property string|null $department_id
 * @property string|null $division_id
 * @property string|null $optigate_position_id
 * @property int|null $level
 * @property string|null $level_label
 * @property string|null $parent_position_id
 * @property bool $is_direct
 * @property bool $is_vacant
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'nama_position',
    'department_id',
    'division_id',
    'optigate_position_id',
    'level',
    'level_label',
    'parent_position_id',
    'is_direct',
    'is_vacant',
])]
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'position_id');
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function parentPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'parent_position_id');
    }

    /**
     * @return HasMany<Position, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Position::class, 'parent_position_id');
    }

    /**
     * Check if position has no employees (vacant).
     */
    public function isVacant(): bool
    {
        return $this->employees_count === 0;
    }
}
