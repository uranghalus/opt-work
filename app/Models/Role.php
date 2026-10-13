<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * Extends Spatie's role (config permission.models.role points here) so the
 * is_system flag travels with the model everywhere roles are resolved.
 * Hak Akses never deletes or renames a system role, and never syncs the
 * super_admin role's permissions — its Gate bypass makes them decorative.
 *
 * Global model — roles apply across all cabang, never scoped by tenant.
 */
class Role extends SpatieRole
{
    /**
     * Matches the migration default so a freshly built (unsaved) model never
     * reports a null flag.
     */
    protected $attributes = [
        'is_system' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Roles owned by the seeder (see ADR-0004): the nine canonical business
     * roles, protected from delete/rename in Hak Akses.
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }
}
