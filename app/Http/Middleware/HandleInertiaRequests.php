<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantAccess;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $currentTenant = null;
        $availableTenants = [];

        try {
            $activeTenant = \App\Models\Tenant::current();

            $isSuperAdmin = $user && ($user->hasRole('super-admin') || $user->is_super_admin);

            if ($activeTenant) {
                $currentTenant = $activeTenant->only(['id', 'name']);
                $query = $user
                    ? ($isSuperAdmin
                        ? \App\Models\Tenant::latest()->get()
                        : $this->getUserTenants($user))
                    : collect();
                $availableTenants = $query->map(fn (\App\Models\Tenant $t) => $this->mapTenant($t));
            } elseif ($user) {
                $query = $isSuperAdmin ? \App\Models\Tenant::latest()->get() : $this->getUserTenants($user);
                $availableTenants = $query->map(fn (\App\Models\Tenant $t) => $this->mapTenant($t));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('HandleInertiaRequests: '.$e->getMessage());
        }

        $isSuperAdmin = $user !== null && ($user->hasRole('super-admin') || $user->is_super_admin);

        $isImpersonating = $isSuperAdmin
            && $request->session()->get('is_impersonating', false)
            && $request->session()->get('impersonated_at');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user
                    ? array_merge($user->toArray(), [
                        'roles' => $user->getRoleNames()->toArray(),
                        'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                    ])
                    : null,
                'isSuperAdmin' => $isSuperAdmin,
            ],
            'tenant' => $currentTenant,
            'availableTenants' => $availableTenants,
            'tenants' => $availableTenants,
            'impersonation' => [
                'active' => $isImpersonating,
                'home_tenant_id' => $user?->tenant_id,
                'impersonated_at' => $request->session()->get('impersonated_at'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
            ],
            'unreadNotificationsCount' => fn (): int => $user?->unreadNotifications()->count() ?? 0,
        ];
    }

    /**
     * Get tenants accessible to the user, using either the pivot table or the tenant_id column.
     */
    private function getUserTenants($user)
    {
        if ($user->tenant_id) {
            // Use the tenant_id column as fallback for users without pivot records
            return \App\Models\Tenant::where('id', $user->tenant_id)->get();
        }

        return $user->tenants()->get();
    }

    private function mapTenant(\App\Models\Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'code' => $tenant->code,
            'is_active' => (bool) $tenant->is_active,
        ];
    }
}