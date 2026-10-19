<?php

namespace App\Models;

use App\Services\TenantAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Models\Tenant as SpatieTenant;

class Tenant extends SpatieTenant
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'optigate_company_id',
        'code',
        'name',
        'is_active',
        'deactivated_at',
        'last_department_synced_at',
    ];

    protected $casts = [
        'id' => 'string',
        'optigate_company_id' => 'string',
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
        'last_department_synced_at' => 'datetime',
    ];

    public function getTenantKey(): string
    {
        return (string) $this->getKey();
    }

    /**
     * The single seam for looking a tenant up by its URL identifier: exact,
     * trimmed, case-insensitive match on the code slug. Never partial, never
     * a guess — callers decide what "no row" means (404, deny, ...).
     */
    public function scopeWhereCode(Builder $query, string $code): Builder
    {
        return $query->whereRaw('LOWER(TRIM(code)) = LOWER(TRIM(?))', [$code]);
    }

    /**
     * Resolve route binding with access verification.
     * Prevents IDOR by ensuring the current user can access this tenant.
     * Uses the `code` column for route binding (URL slug).
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        // Route binding uses the `code` (slug) from URL
        $tenant = $this->whereCode($value)->first();

        if (! $tenant) {
            return null;
        }

        $request = app(Request::class);
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $tenantAccess = app(TenantAccess::class);

        if (! $tenantAccess->canOperate($user, $tenant->id)) {
            return null;
        }

        return $tenant;
    }

    /** @return BelongsToMany<User, $this, Pivot, 'pivot'> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_tenants', 'tenant_id', 'user_id');
    }
}
