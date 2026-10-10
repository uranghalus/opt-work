<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use LightSaml\Model\Assertion\Attribute;
use SocialiteProviders\Saml2\User as Saml2User;

/**
 * Resolves the unit bisnis (tenant) for a SAML-authenticated user.
 *
 * The Optigate assertion carries only the company NAME (verified against a
 * real assertion dump — no code or ID attribute), so the lookup is an exact,
 * trimmed, case-insensitive match on tenants.name. Tenants are never created
 * here; they come only from the Optigate API sync.
 */
class SamlTenantResolver
{
    /** The SAML attribute carrying the Optigate company name. */
    protected const COMPANY_ATTRIBUTE = 'company';

    public const REASON_NO_COMPANY_DATA = 'no_company_data';

    public const REASON_NO_MATCH = 'no_match';

    public const REASON_MULTIPLE_MATCHES = 'multiple_matches';

    public const REASON_INACTIVE = 'inactive';

    /**
     * Platform-level bypass: this account may reach any unit bisnis without
     * being tied to the company in the assertion. Kept in one place so it
     * can be replaced by Spatie Permission later.
     */
    public function canAccessAnyTenant(User $user): bool
    {
        return $user->is_super_admin
            || strcasecmp($user->email ?? '', (string) config('auth.super_admin_email', 'superadmin@appdutamall.com')) === 0;
    }

    /**
     * Resolve the tenant for a SAML assertion, or deny with a reason.
     */
    public function resolve(Saml2User $samlUser): SamlTenantResolution
    {
        $companyName = $this->companyNameFrom($samlUser);

        if ($companyName === null) {
            Log::warning('SAML tenant resolution: assertion carries no company data.', [
                'name_id' => $samlUser->getId(),
            ]);

            return SamlTenantResolution::denied(self::REASON_NO_COMPANY_DATA);
        }

        $matches = Tenant::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($companyName)])
            ->get();

        if ($matches->count() > 1) {
            Log::error('SAML tenant resolution: multiple tenants match the company name; refusing to pick one.', [
                'name_id' => $samlUser->getId(),
                'company' => $companyName,
                'matched_tenant_ids' => $matches->pluck('id')->all(),
            ]);

            return SamlTenantResolution::denied(self::REASON_MULTIPLE_MATCHES);
        }

        $tenant = $matches->first();

        if (! $tenant) {
            Log::warning('SAML tenant resolution: no tenant matches the company name.', [
                'name_id' => $samlUser->getId(),
                'company' => $companyName,
            ]);

            return SamlTenantResolution::denied(self::REASON_NO_MATCH);
        }

        if (! $tenant->is_active || $tenant->deactivated_at !== null) {
            Log::warning('SAML tenant resolution: the matching tenant is inactive.', [
                'name_id' => $samlUser->getId(),
                'company' => $companyName,
                'tenant_id' => $tenant->id,
            ]);

            return SamlTenantResolution::denied(self::REASON_INACTIVE);
        }

        return SamlTenantResolution::found($tenant);
    }

    /**
     * First non-empty value of the company attribute, trimmed.
     */
    protected function companyNameFrom(Saml2User $samlUser): ?string
    {
        foreach ($samlUser->getRaw() as $attribute) {
            if (! $attribute instanceof Attribute) {
                continue;
            }

            if (strcasecmp($attribute->getName(), self::COMPANY_ATTRIBUTE) !== 0) {
                continue;
            }

            foreach ($attribute->getAllAttributeValues() as $value) {
                $trimmed = trim((string) $value);

                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }

        return null;
    }
}
