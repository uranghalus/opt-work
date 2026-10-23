<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DivisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $department_id
 * @property string|null $kode_division
 * @property string $nama_division
 * @property string|null $optigate_division_id
 * @property bool $is_direct
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['department_id', 'kode_division', 'nama_division', 'optigate_division_id', 'is_direct'])]
class Division extends Model
{
    /** @use HasFactory<DivisionFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $casts = [
        'is_active' => 'boolean',
        'is_direct' => 'boolean',
    ];

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
