<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DivisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $kode_division
 * @property string $nama_division
 * @property string|null $optigate_division_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['kode_division', 'nama_division', 'optigate_division_id'])]
class Division extends Model
{
    /** @use HasFactory<DivisionFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'division_id');
    }
}
