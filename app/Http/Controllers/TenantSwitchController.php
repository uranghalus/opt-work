<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantAccess;
use App\Services\TenantSwitchTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;

class TenantSwitchController extends Controller
{
    public function __construct(
        protected TenantAccess $tenantAccess,
        protected MakeTenantCurrentAction $makeTenantCurrentAction,
        protected UrlGenerator $urlGenerator,
        protected TenantSwitchTarget $switchTarget,
    ) {}

    /**
     * Switch tenant while staying on the current page.
     *
     * The client posts the current route name + parameters; the target page
     * is computed server-side (record pages map to their parent list, the
     * query string is dropped, unknown pages fall back to the dashboard).
     * Route parameter 'tenant' is the `code` (slug).
     */
    public function switching(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant' => ['required', 'string'],
            'route.name' => ['nullable', 'string'],
            'route.params' => ['nullable', 'array'],
            // Route keys are strings (ULIDs) or integers ({index} params);
            // anything else cannot rebuild a URL and falls back server-side.
            'route.params.*' => [
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! is_string($value) && ! is_int($value)) {
                        $fail("The {$attribute} must be a route key (string or integer).");
                    }
                },
            ],
        ]);

        $tenant = Tenant::whereCode($validated['tenant'])->first();

        if (! $tenant) {
            abort(404, 'Cabang tidak ditemukan.');
        }

        $user = $request->user();

        if (! $this->tenantAccess->canOperate($user, $tenant->code)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        $target = $this->switchTarget->resolve(
            $tenant->code,
            $validated['route']['name'] ?? null,
            $validated['route']['params'] ?? [],
        );

        $this->persistSwitch($request, $user, $tenant);

        Log::info('Tenant switched', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'from_tenant' => $user->tenant_id,
            'to_tenant' => $tenant->id,
            'target_route' => $validated['route']['name'] ?? null,
            'target_path' => $target['path'],
            'fell_back' => $target['fell_back'],
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->to($target['path'])
            ->with(
                $target['fell_back'] ? 'error' : 'success',
                $target['fell_back']
                    ? "Halaman ini tidak tersedia untuk cabang {$tenant->name}. Anda diarahkan ke halaman dashboard cabang tersebut."
                    : "Berpindah ke cabang: {$tenant->name}",
            );
    }

    /**
     * Shared bookkeeping for every switch path: make the tenant current and
     * remember the context (and impersonation state) in the session.
     */
    protected function persistSwitch(Request $request, $user, Tenant $tenant): void
    {
        $this->makeTenantCurrentAction->execute($tenant);

        if ($request->hasSession()) {
            $request->session()->put('current_tenant_id', $tenant->id);
            $request->session()->put('is_impersonating', $user->is_super_admin && $user->tenant_id !== $tenant->id);
            $request->session()->put('impersonated_at', now()->toISOString());
        }
    }

    /**
     * Switch to a specific tenant via signed URL.
     * This is the primary, secure way to switch tenants.
     * Route parameter 'tenant' is the `code` (slug).
     */
    public function switch(Request $request, string $tenantCode): RedirectResponse
    {
        // Verify the signed URL for security
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired tenant switch link.');
        }

        $tenant = Tenant::where('code', $tenantCode)->first();

        if (! $tenant) {
            abort(404, 'Cabang tidak ditemukan.');
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->route('saml.redirect');
        }

        // Check if user can operate on this tenant
        if (! $this->tenantAccess->canOperate($user, $tenantCode)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        $this->persistSwitch($request, $user, $tenant);

        // Log the tenant switch for audit
        Log::info('Tenant switched', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'from_tenant' => $user->tenant_id,
            'to_tenant' => $tenant->id,
            'is_impersonating' => $user->is_super_admin && $user->tenant_id !== $tenant->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Redirect to the intended page or tenant dashboard - use code for route
        $redirectUrl = $request->query('redirect', $this->urlGenerator->route('dashboard', ['tenant' => $tenant->code]));

        return redirect($redirectUrl)
            ->with('success', "Berpindah ke cabang: {$tenant->name}");
    }

    /**
     * Generate a signed URL for switching to a tenant.
     * Used by the BranchSwitcher component.
     * Route parameter 'tenant' is the `code` (slug).
     */
    public function signedSwitchUrl(Request $request, string $tenantCode): JsonResponse
    {
        $tenant = Tenant::where('code', $tenantCode)->first();

        if (! $tenant) {
            return response()->json(['error' => 'Cabang tidak ditemukan.'], 404);
        }

        $user = $request->user();

        if (! $user || ! $this->tenantAccess->canOperate($user, $tenantCode)) {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

        $url = URL::temporarySignedRoute(
            'tenant.switch',
            now()->addMinutes(5),
            ['tenant' => $tenantCode, 'redirect' => $request->query('redirect')]
        );

        return response()->json([
            'url' => $url,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'code' => $tenant->code,
            ],
        ]);
    }

    /**
     * End impersonation and return to user's home tenant.
     */
    public function stopImpersonating(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->is_super_admin) {
            abort(403);
        }

        $homeTenantId = $user->tenant_id;

        if (! $homeTenantId) {
            // Super admin with no home tenant - go to first accessible tenant
            $accessible = $this->tenantAccess->operableTenants($user)->first();

            if (! $accessible) {
                return redirect()->route('dashboard')->with('error', 'Tidak ada cabang yang dapat diakses.');
            }

            $homeTenantId = $accessible->id;
        }

        $homeTenant = Tenant::find($homeTenantId);

        if (! $homeTenant) {
            return redirect()->route('dashboard')->with('error', 'Cabang asal tidak ditemukan.');
        }

        $this->makeTenantCurrentAction->execute($homeTenant);

        if ($request->hasSession()) {
            $request->session()->put('current_tenant_id', $homeTenant->id);
            $request->session()->forget(['is_impersonating', 'impersonated_at']);
        }

        Log::info('Impersonation stopped', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'returned_to_tenant' => $homeTenant->id,
        ]);

        return redirect()->route('dashboard', ['tenant' => $homeTenant->code])
            ->with('success', "Kembali ke cabang asal: {$homeTenant->name}");
    }

    /**
     * Get current impersonation status for UI.
     */
    public function impersonationStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['impersonating' => false]);
        }

        $isImpersonating = $request->session()->get('is_impersonating', false);
        $impersonatedAt = $request->session()->get('impersonated_at');
        $homeTenantId = $user->tenant_id;

        // Current tenant from route is the code
        $currentTenantCode = $request->route('tenant');

        return response()->json([
            'impersonating' => $isImpersonating && $user->is_super_admin,
            'impersonated_at' => $impersonatedAt,
            'home_tenant_id' => $homeTenantId,
            'current_tenant_id' => $currentTenantCode,
        ]);
    }
}
