<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Stancl\Tenancy\Database\Models\Tenant;

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

        $activeTenant = tenant()?->getTenantKey() ?? $user?->tenant_id;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'role' => $user?->getRoleNames()->first(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
            ],
            'unreadNotificationsCount' => fn (): int => $user?->unreadNotifications()->count() ?? 0,
            'activeTenant' => $activeTenant,
            'tenants' => Tenant::all(['id', 'data'])->map(function ($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->data['nama_cabang'] ?? $t->id,
                    'code' => $t->data['kode_cabang'] ?? null,
                ];
            })->toArray(),
            'can' => [
                'division.read' => $user?->can('division.read') ?? false,
                'department.read' => $user?->can('department.read') ?? false,
                'employee.read' => $user?->can('employee.read') ?? false,
            ],
            'permissions' => [
                'division.read' => $user?->can('division.read') ?? false,
                'department.read' => $user?->can('department.read') ?? false,
                'employee.read' => $user?->can('employee.read') ?? false,
                'work-order.read' => $user?->can('work-order.read') ?? false,
                'work-order.create' => $user?->can('work-order.create') ?? false,
                'rbac.manage' => $user?->can('rbac.manage') ?? false,
            ],
        ];
    }
}
