import type { AppActions } from '@/types/ui';
import type { BreadcrumbItem } from '@/types/navigation';
import type { Auth, TenantSummary } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** Branches the user may switch into — the same set the server enforces. */
            tenants: TenantSummary[];
            activeTenant: string | null;
            /** Current tenant context ({id, name}) or null in central pages. */
            tenant: { id: string; name: string } | null;
            /** Current route identity for same-page tenant switching. */
            route: {
                name: string | null;
                params: Record<string, string | number>;
            };
            /** Superadmin impersonation banner state. */
            impersonation: {
                active: boolean;
                home_tenant_id: string | null;
                impersonated_at: string | null;
            };
            unreadNotificationsCount: number;
            [key: string]: unknown;
        };
        layoutProps: {
            breadcrumbs?: BreadcrumbItem[];
            title?: string;
            description?: string;
            greeting?: boolean;
            actions?: AppActions;
        };
    }
}
