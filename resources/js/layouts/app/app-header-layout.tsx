import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import { BottomNav } from '@/components/bottom-nav';
import { IconRail } from '@/components/icon-rail';
import type { AppLayoutProps } from '@/types';

/*
 * Workspace shell (Glassy Modern redesign): a floating frosted-glass
 * capsule icon rail beside a content column whose translucent top bar
 * (backdrop-blur, deepen-on-scroll) holds the pill-tab section navigation.
 * Mobile (<768px) swaps the rail for a floating glass pill bottom nav.
 */
export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: AppLayoutProps) {
    return (
        <AppShell variant="header">
            <IconRail />
            <div className="flex min-w-0 flex-1 flex-col">
                <AppHeader breadcrumbs={breadcrumbs} />
                <AppContent variant="header">{children}</AppContent>
            </div>
            <BottomNav />
        </AppShell>
    );
}
