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

            if ($activeTenant) {
                $currentTenant = $activeTenant->only(['id', 'name']);
                $query = $user
                    ? ($user->hasRole('super-admin')
                        ? \App\Models\Tenant::latest()->get()
                        : $user->tenants()->get())
                    : collect();
                $availableTenants = $query->map(fn (\App\Models\Tenant $t) => $this->mapTenant($t));
            } elseif ($user) {
                $query = $user->hasRole('super-admin') ? \App\Models\Tenant::latest()->get() : collect();
                $availableTenants = $query->map(fn (\App\Models\Tenant $t) => $this->mapTenant($t));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('HandleInertiaRequests: '.$e->getMessage());
        }

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
                'isSuperAdmin' => $user !== null && $user->hasRole('super-admin'),
            ],
            'tenant' => $currentTenant,
            'availableTenants' => $availableTenants,
            'tenants' => $availableTenants,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
            ],
            'unreadNotificationsCount' => fn (): int => $user?->unreadNotifications()->count() ?? 0,
        ];
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
