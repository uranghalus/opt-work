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
                    // Gateway WhatsApp per tenant (ADR-0009 amendmen).
                    'whatsapp.read' => (bool) ($user?->can('whatsapp.read')),
                    'whatsapp.update' => (bool) ($user?->can('whatsapp.update')),
                    // WO slice 1: HOD review/assign actions on the show page.
                    'work-order.review' => (bool) ($user?->can('work-order.review')),
                    'work-order.assign' => (bool) ($user?->can('work-order.assign')),
                    // WO slice 2: pelaksana submit hasil + HOD verify (FR-1.7/1.8).
                    'work-order.submit' => (bool) ($user?->can('work-order.submit')),
                    'work-order.verify' => (bool) ($user?->can('work-order.verify')),
                    // FR-3.x Work Data — histori pekerjaan per WO closed.
                    'work-data.read' => (bool) ($user?->can('work-data.read')),
                    'work-data.update' => (bool) ($user?->can('work-data.update')),
                    // FR-5.x Daily Work — daftar harian + kelola template/adhoc (HOD).
                    'daily-work.read' => (bool) ($user?->can('daily-work.read')),
                    'daily-work.update' => (bool) ($user?->can('daily-work.update')),
                    'daily-work.create' => (bool) ($user?->can('daily-work.create')),
                    'daily-work.delete' => (bool) ($user?->can('daily-work.delete')),
                    // WO slice 3: extend deadline WO telat (FR-2.3–2.5).
                    'work-order.extend' => (bool) ($user?->can('work-order.extend')),
                    'work-order.extend-approve' => (bool) ($user?->can('work-order.extend-approve')),
                    // ADR-0011: gerbang approval lintas-department — inbox
                    // approver department pengaju.
                    'work-order.approve-request' => (bool) ($user?->can('work-order.approve-request')),
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
