import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { AppLayoutProps } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    title,
    description,
    greeting,
    actions,
    children,
}: AppLayoutProps) {
    return (
        <AppLayoutTemplate
            breadcrumbs={breadcrumbs}
            title={title}
            description={description}
            greeting={greeting}
            actions={actions}
        >
            {children}
        </AppLayoutTemplate>
    );
}
