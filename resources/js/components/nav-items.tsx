import {
    CalendarClock,
    ClipboardList,
    Database,
    House,
    LayoutGrid,
    Settings,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/**
 * OptiWorks navigation (DESIGN_BRIEF §6 S01, PRD §5 MVP scope).
 * Permission-driven: pages gate visibility at render time via
 * `roles`; unauthenticated/unpermitted items are hidden, not disabled.
 * `roles?: string[]` is a forward hook — role data arrives with RBAC,
 * until then every item renders for every authenticated user.
 */
export type OptiNavItem = NavItem & {
    /** Spatie role names allowed to see this item (forward hook for RBAC). */
    roles?: string[];
};

export const mainNavItems: OptiNavItem[] = [
    {
        title: 'Beranda',
        href: dashboard(),
        icon: House,
    },
    {
        title: 'Work Order',
        href: dashboard(), // WO list route lands with the WO module
        icon: ClipboardList,
    },
    {
        title: 'Daily Work',
        href: dashboard(), // D01 Daily Work route lands with the module
        icon: CalendarClock,
    },
    {
        title: 'Work Data',
        href: dashboard(), // WD01 history route lands with the module
        icon: Database,
    },
    {
        title: 'Admin',
        href: dashboard(), // A01 RBAC route lands with the module; gate by role then
        icon: Users,
        roles: ['Super Admin'],
    },
];

export const utilityNavItems: OptiNavItem[] = [
    {
        title: 'Pengaturan',
        href: '/settings/profile',
        icon: Settings,
    },
];

/** Always resolves to an icon: falls back to LayoutGrid when unset. */
export function navIcon(item: OptiNavItem): LucideIcon {
    return item.icon ?? (LayoutGrid as unknown as LucideIcon);
}
