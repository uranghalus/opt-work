<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            $activeTenant = Tenant::current();

            $isSuperAdmin = $user?->isSuperAdmin() ?? false;

            if ($activeTenant) {
                $currentTenant = $activeTenant->only(['id', 'name']);
                $query = $user
                    ? ($isSuperAdmin
                        ? Tenant::latest()->get()
                        : $this->getUserTenants($user))
                    : collect();
                $availableTenants = $query->map(fn (Tenant $t) => $this->mapTenant($t));
            } elseif ($user) {
                $query = $isSuperAdmin ? Tenant::latest()->get() : $this->getUserTenants($user);
                $availableTenants = $query->map(fn (Tenant $t) => $this->mapTenant($t));
            }
        } catch (\Throwable $e) {
            Log::warning('HandleInertiaRequests: '.$e->getMessage());
        }

        $isSuperAdmin = $user?->isSuperAdmin() ?? false;

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
                    ])
                    : null,
                'isSuperAdmin' => $isSuperAdmin,
                // Ability flags computed through the Gate (never the raw
                // permission list): a super admin holds zero DB permissions
                // by design, so flags must survive the bypass (ADR-0004).
                'can' => [
                    'role.read' => (bool) ($user?->can('role.read')),
                    'role.create' => (bool) ($user?->can('role.create')),
                    'role.update' => (bool) ($user?->can('role.update')),
                    'role.delete' => (bool) ($user?->can('role.delete')),
                    'permission.read' => (bool) ($user?->can('permission.read')),
                    'permission.create' => (bool) ($user?->can('permission.create')),
                    'permission.update' => (bool) ($user?->can('permission.update')),
                    'permission.delete' => (bool) ($user?->can('permission.delete')),
                    'akun.read' => (bool) ($user?->can('akun.read')),
                    'akun.update' => (bool) ($user?->can('akun.update')),
                    // WO slice 1: HOD review/assign actions on the show page.
                    'work-order.review' => (bool) ($user?->can('work-order.review')),
                    'work-order.assign' => (bool) ($user?->can('work-order.assign')),
                ],
            ],
            'tenant' => $currentTenant,
            // The route identifier for {tenant} URLs — the code slug, not the
            // ULID. Null in central contexts (admin panel, pre-tenant pages).
            'activeTenant' => $activeTenant?->code,
            // Current route identity, so the tenant switcher can rebuild the
            // same page for another tenant server-side (models reduced to
            // their route keys).
            'route' => [
                'name' => $request->route()?->getName(),
                'params' => collect($request->route()?->parameters() ?? [])
                    ->map(fn ($value) => $value instanceof Model
                        ? $value->getRouteKey()
                        : $value)
                    ->all(),
            ],
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
                'error' => fn (): ?string => $request->session()->get('error'),
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
            return Tenant::where('id', $user->tenant_id)->get();
        }

        return $user->tenants()->get();
    }

    private function mapTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'code' => $tenant->code,
            'is_active' => (bool) $tenant->is_active,
        ];
    }
}
