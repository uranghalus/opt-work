import { router, usePage } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import { switching } from '@/routes/tenant';

/**
 * The tenant-related slice of the shared props (declared centrally in
 * resources/js/types/global.d.ts). activeTenant is the {tenant} URL
 * identifier (the code slug) and is genuinely nullable: it is null on
 * central pages (admin panel, pre-tenant pages) and whenever the URL
 * segment did not resolve to a tenant.
 */
export type TenantSharedProps = InertiaConfig['sharedPageProps'];

/**
 * Thrown when a tenant page tries to build a tenant route without a tenant.
 * That is always a bug — the page rendered outside /{tenant}/... — so it
 * must be loud and name the route, never a cryptic "reading 'toString'"
 * crash inside a generated route file.
 */
export class MissingTenantError extends Error {
    readonly routeName: string;

    constructor(routeName: string) {
        super(
            `Route "${routeName}" requires the tenant parameter, but the page rendered without tenant context. `
                + 'Tenant pages must be opened under /{tenant}/...',
        );
        this.name = 'MissingTenantError';
        this.routeName = routeName;
    }
}

/**
 * The current tenant code, guaranteed present. This is the single seam for
 * the current tenant: pages call it once and never pass the tenant around
 * by hand. SSR-safe — reads Inertia shared props only, no window/document.
 *
 * Throws MissingTenantError instead of returning a broken value, so a page
 * rendered outside a tenant context fails with one clear message.
 */
export function useRequiredTenant(routeName: string): string {
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;

    if (!activeTenant) {
        throw new MissingTenantError(routeName);
    }

    return activeTenant;
}

/**
 * Switch to another tenant and stay on the current page. The backend
 * (TenantSwitchTarget) computes the equivalent page for the target tenant:
 * record-level pages map to their parent list, the query string is dropped,
 * and unknown pages fall back to the dashboard with a message. The POST is
 * followed by an Inertia redirect — no full reload — with the standard
 * progress bar as the loading state.
 *
 * Must be called from event handlers, not render: pass the current route
 * info captured during render.
 */
export function switchTenant(newTenantCode: string, current: TenantSharedProps['route']): void {
    router.post(
        switching.url(),
        {
            tenant: newTenantCode,
            route: { name: current.name, params: current.params },
        },
        {
            preserveState: false,
            preserveScroll: false,
        },
    );
}
