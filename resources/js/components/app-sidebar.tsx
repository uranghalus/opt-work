import { Link } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { BookOpen, Building2, FolderGit2, LayoutGrid, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import departments from '@/routes/departments';
import { dashboard } from '@/routes';
import divisions from '@/routes/divisions';
import employees from '@/routes/employees';
import type { InertiaConfig } from '@inertiajs/core';
import type { NavItem } from '@/types';

function masterDataNavItems(
    can: Record<string, boolean>,
    activeTenant: string | null,
): NavItem[] {
    if (!activeTenant) {
        return [];
    }

    const items: NavItem[] = [];

    if (can['department.read']) {
        items.push({
            title: 'Department',
            href: departments.index({ tenant: activeTenant }),
            icon: Building2,
        });
    }

    if (can['division.read']) {
        items.push({
            title: 'Divisi',
            href: divisions.index({ tenant: activeTenant }),
            icon: LayoutGrid,
        });
    }

    if (can['employee.read']) {
        items.push({
            title: 'Karyawan',
            href: employees.index({ tenant: activeTenant }),
            icon: Users,
        });
    }

    return items;
}

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { can, activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={[
                        {
                            title: 'Dashboard',
                            href: dashboard(),
                            icon: LayoutGrid,
                        },
                    ]}
                />
                <NavMain
                    label="Master Data"
                    items={masterDataNavItems(can, activeTenant)}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
