import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { BottomNav } from '@/components/bottom-nav';
import { PageHeader } from '@/components/page-header';
import type { AppLayoutProps } from '@/types';

/*
 * The OptiWorks shell. A deep ink-teal navigation panel owns wayfinding; the
 * content stratum carries the sticky top bar, the shell-owned section
 * heading band (module chip · title · description · actions) and the page
 * itself on one padding owner. Phone (<768px) swaps the panel for a floating
 * bottom bar with a full navigation sheet.
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
                <div className="mx-auto flex w-full max-w-[1400px] flex-1 flex-col gap-6 px-4 pt-6 pb-28 sm:px-6 lg:px-8 lg:pt-8 lg:pb-14">
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
