<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Outcome of resolving a tenant from a SAML assertion.
 *
 * Denials carry a machine reason that maps to fixed, non-leaking copy on the
 * saml.denied page; the details live in the resolver's log lines only.
 */
class SamlTenantResolution
{
    private function __construct(
        public readonly ?Tenant $tenant,
        public readonly ?string $denialReason,
    ) {}

    public static function found(Tenant $tenant): self
    {
        return new self($tenant, null);
    }

    public static function denied(string $reason): self
    {
        return new self(null, $reason);
    }

    public function isFound(): bool
    {
        return $this->tenant !== null;
    }

    public function isDenied(): bool
    {
        return $this->tenant === null;
    }
}
