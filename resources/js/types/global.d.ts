import type { AppActions } from '@/types/ui';
import type { BreadcrumbItem } from '@/types/navigation';
import type { Auth } from '@/types/auth';

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
            can: Record<string, boolean>;
            /** Navigation permissions (never overridden by page props). */
            permissions: Record<string, boolean>;
            activeTenant: string | null;
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
