import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { BottomNav } from '@/components/bottom-nav';
import { PageHeader } from '@/components/page-header';
import type { AppLayoutProps } from '@/types';

/**
 * OptiWorks shell (sidebar variant).
 * Navigation stratum (AppSidebar) owns wayfinding.
 * Content stratum owns: sticky top bar (AppSidebarHeader), section heading band
 * (PageHeader — module chip, title, description, actions), and page content
 * on a single padding owner with Stitch surface tokens.
 * Phone (<768px) swaps the panel for a floating bottom bar with a full nav sheet.
 */
export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    title,
    description,
    greeting,
    actions,
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="flex w-full flex-1 flex-col gap-4 px-4 pt-5 pb-20 sm:gap-5 sm:px-6 sm:pt-6 lg:px-8 lg:pt-7 lg:pb-12">
                    <PageHeader
                        title={title}
                        description={description}
                        greeting={greeting}
                        actions={actions}
                    />
                    {children}
                </div>
            </AppContent>
            <BottomNav />
        </AppShell>
    );
}
