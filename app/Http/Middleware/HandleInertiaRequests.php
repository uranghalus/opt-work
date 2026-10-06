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

        $activeTenant = tenant()?->getTenantKey() ?? $user?->tenant_id;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
            ],
            'unreadNotificationsCount' => fn (): int => $user?->unreadNotifications()->count() ?? 0,
            'activeTenant' => $activeTenant,
            // Branches this user may actually switch into — the same set
            // TenantAccess::canOperate() enforces, never a display shortcut.
            'tenants' => $user === null
                ? []
                : app(TenantAccess::class)->operableTenants($user)->map(fn (Tenant $t) => [
                    'id' => $t->getTenantKey(),
                    'name' => $t->label(),
                    'code' => $t->code,
                ])->values()->all(),
        ];
    }
}
