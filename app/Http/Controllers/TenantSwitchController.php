<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantAccess;
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
    ) {}

    /**
     * Switch to a specific tenant via signed URL.
     * This is the primary, secure way to switch tenants.
     */
    public function switch(Request $request, string $tenantId): RedirectResponse
    {
        // Verify the signed URL for security
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired tenant switch link.');
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            abort(404, 'Cabang tidak ditemukan.');
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->route('saml.redirect');
        }

        // Check if user can operate on this tenant
        if (! $this->tenantAccess->canOperate($user, $tenantId)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        // Determine if this is an impersonation (super admin accessing another tenant)
        $isImpersonating = $user->is_super_admin && $user->tenant_id !== $tenantId;

        // Make tenant current
        $this->makeTenantCurrentAction->execute($tenant);

        // Store in session for convenience (same-tab navigation)
        if ($request->hasSession()) {
            $request->session()->put('current_tenant_id', $tenant->id);
            $request->session()->put('is_impersonating', $isImpersonating);
            $request->session()->put('impersonated_at', now()->toISOString());
        }

        // Log the tenant switch for audit
        Log::info('Tenant switched', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'from_tenant' => $user->tenant_id,
            'to_tenant' => $tenant->id,
            'is_impersonating' => $isImpersonating,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Redirect to the intended page or tenant dashboard
        $redirectUrl = $request->query('redirect', $this->urlGenerator->route('dashboard', ['tenant' => $tenant->id]));

        return redirect($redirectUrl)
            ->with('success', "Berpindah ke cabang: {$tenant->name}");
    }

    /**
     * Generate a signed URL for switching to a tenant.
     * Used by the BranchSwitcher component.
     */
    public function signedSwitchUrl(Request $request, string $tenantId): \Illuminate\Http\JsonResponse
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return response()->json(['error' => 'Cabang tidak ditemukan.'], 404);
        }

        $user = $request->user();

        if (! $user || ! $this->tenantAccess->canOperate($user, $tenantId)) {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

        $url = URL::temporarySignedRoute(
            'tenant.switch',
            now()->addMinutes(5),
            ['tenant' => $tenantId, 'redirect' => $request->query('redirect')]
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

        return redirect()->route('dashboard', ['tenant' => $homeTenant->id])
            ->with('success', "Kembali ke cabang asal: {$homeTenant->name}");
    }

    /**
     * Get current impersonation status for UI.
     */
    public function impersonationStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['impersonating' => false]);
        }

        $isImpersonating = $request->session()->get('is_impersonating', false);
        $impersonatedAt = $request->session()->get('impersonated_at');
        $homeTenantId = $user->tenant_id;

        return response()->json([
            'impersonating' => $isImpersonating && $user->is_super_admin,
            'impersonated_at' => $impersonatedAt,
            'home_tenant_id' => $homeTenantId,
            'current_tenant_id' => $request->route('tenant'),
        ]);
    }
}