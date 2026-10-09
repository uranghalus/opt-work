<?php

namespace App\Models;

use App\Services\TenantAccess;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Models\Tenant as SpatieTenant;

class Tenant extends SpatieTenant
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'id' => 'string',
    ];

    public function getTenantKey(): string
    {
        return (string) $this->getKey();
    }

    /**
     * Resolve route binding with access verification.
     * Prevents IDOR by ensuring the current user can access this tenant.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        $tenant = $this->where('id', $value)->first();

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