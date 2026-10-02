<?php

namespace App\Models;

use Database\Factories\DivisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $kode_division
 * @property string $nama_division
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['kode_division', 'nama_division'])]
class Division extends Model
{
    /** @use HasFactory<DivisionFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'division_id');
    }
}
