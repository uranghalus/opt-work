import {
    Bell,
    BriefcaseBusiness,
    Building2,
    CalendarClock,
    ClipboardList,
    Database,
    House,
    LayoutGrid,
    LifeBuoy,
    Network,
    Settings,
    ShieldCheck,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { dashboard } from '@/routes';
import departments from '@/routes/departments';
import divisions from '@/routes/divisions';
import employees from '@/routes/employees';
import { index as notificationsIndex } from '@/routes/notifications';
import positions from '@/routes/positions';
import { index as workOrdersIndex } from '@/routes/work-orders';
import tenants from '@/routes/tenants';
import type { NavItem } from '@/types';

/**
 * OptiWorks navigation (DESIGN_BRIEF §6 S01, PRD §5 MVP scope).
 *
 * One source of truth for the sidebar, the mobile sheet, and the bottom nav.
 * Tenant-scoped items need an active cabang to build a URL; modules without a
 * route yet are rendered disabled with a "Segera" hint instead of pointing at a
 * fake URL.
 */
export type NavTone = 'ops' | 'master' | 'system';

export type OptiNavItem = NavItem & {
    /** Group identity hue used for the icon tint. */
    tone?: NavTone;
    /** Compact label used by the mobile bottom navigation. */
    shortTitle?: string;
    /** Reads the shared unread notification count and renders a badge. */
    badge?: 'notifications';
    /** Module not built yet (or no cabang active): rendered inert. */
    disabled?: boolean;
    /** Reason shown in the tooltip when an item is inert. */
    hint?: string;
};

export type OptiNavGroup = {
    id: string;
    label: string;
    tone: NavTone;
    items: OptiNavItem[];
};

type NavContext = {
    activeTenant?: string | null;
};

/** Group hues: identity for a navigation area — never a status signal. */
export const navToneStyles: Record<
    NavTone,
    { icon: string; chip: string; chipRing: string }
> = {
    ops: {
        icon: 'text-mod-ops',
        chip: 'bg-mod-ops/12',
        chipRing: 'ring-mod-ops/25',
    },
    master: {
        icon: 'text-mod-master',
        chip: 'bg-mod-master/12',
        chipRing: 'ring-mod-master/25',
    },
    system: {
        icon: 'text-mod-system',
        chip: 'bg-mod-system/12',
        chipRing: 'ring-mod-system/25',
    },
};

/** A tenant-scoped href, or an inert item when no cabang is active. */
function tenantItem(
    activeTenant: string | null,
    build: (tenant: string) => NonNullable<NavItem['href']>,
): Pick<OptiNavItem, 'href' | 'disabled' | 'hint'> {
    if (!activeTenant) {
        return {
            href: dashboard(),
            disabled: true,
            hint: 'Pilih cabang untuk membuka modul ini',
        };
    }

    return { href: build(activeTenant) };
}

function modulePending(name: string): Pick<OptiNavItem, 'disabled' | 'hint'> {
    return { disabled: true, hint: `${name} segera hadir` };
}

export function navGroups({
    activeTenant = null,
}: NavContext): OptiNavGroup[] {
    const tenant = activeTenant ?? null;

    const groups: OptiNavGroup[] = [
        {
            id: 'ops',
            label: 'Operasional',
            tone: 'ops',
            items: [
                {
                    title: 'Dashboard Utama',
                    shortTitle: 'Beranda',
                    href: dashboard(),
                    icon: House,
                    tone: 'ops',
                },
                {
                    title: 'Work Order',
                    shortTitle: 'WO',
                    icon: ClipboardList,
                    tone: 'ops',
                    ...tenantItem(tenant, (t) =>
                        workOrdersIndex({ tenant: t }),
                    ),
                },
                {
                    title: 'Terjadwal',
                    shortTitle: 'Jadwal',
                    icon: CalendarClock,
                    tone: 'ops',
                    ...tenantItem(tenant, (t) =>
                        workOrdersIndex({ tenant: t }),
                    ),
                },
                {
                    title: 'Daily Work',
                    shortTitle: 'Harian',
                    href: dashboard(),
                    icon: LayoutGrid,
                    tone: 'ops',
                    ...modulePending('Daily Work'),
                },
                {
                    title: 'Work Data',
                    shortTitle: 'Data',
                    href: dashboard(),
                    icon: Database,
                    tone: 'ops',
                    ...modulePending('Work Data'),
                },
                {
                    title: 'Notifikasi',
                    shortTitle: 'Notif',
                    href: notificationsIndex(),
                    icon: Bell,
                    tone: 'ops',
                    badge: 'notifications',
                },
            ],
        },
        {
            id: 'master',
            label: 'Data Master',
            tone: 'master',
            items: [
                {
                    title: 'Karyawan',
                    icon: Users,
                    tone: 'master',
                    ...tenantItem(tenant, (t) =>
                        employees.index({ tenant: t }),
                    ),
                },
                {
                    title: 'Departemen',
                    icon: Building2,
                    tone: 'master',
                    ...tenantItem(tenant, (t) =>
                        departments.index({ tenant: t }),
                    ),
                },
                {
                    title: 'Divisi',
                    icon: Network,
                    tone: 'master',
                    ...tenantItem(tenant, (t) =>
                        divisions.index({ tenant: t }),
                    ),
                },
                {
                    title: 'Posisi',
                    icon: BriefcaseBusiness,
                    tone: 'master',
                    ...tenantItem(tenant, (t) =>
                        positions.index({ tenant: t }),
                    ),
                },
                {
                    title: 'Unit Bisnis',
                    icon: Building2,
                    tone: 'master',
                    href: tenants.index(),
                },
            ],
        },
        {
            id: 'system',
            label: 'Sistem',
            tone: 'system',
            items: [
                {
                    title: 'Pengaturan',
                    shortTitle: 'Atur',
                    href: tenants.index(),
                    icon: Settings,
                    tone: 'system',
                },
            ],
        },
    ];

    return groups.filter((group) => group.items.length > 0);
}

/**
 * Flat primary navigation for the header tabs, the tablet menu, and the
 * icon rail: the operations group plus role-gated system entries.
 */
export function mainNavItems(activeTenant?: string | null): OptiNavItem[] {
    const groups = navGroups({ activeTenant });
    const ops = groups.find((group) => group.id === 'ops');
    const system = groups.find((group) => group.id === 'system');

    return [
        ...(ops?.items ?? []),
        ...(system?.items ?? []),
    ];
}

/** Footer utilities: help is not shipped yet, so it stays inert. */
export const utilityNavItems: OptiNavItem[] = [
    {
        title: 'Bantuan',
        href: dashboard(),
        icon: LifeBuoy,
        disabled: true,
        hint: 'Pusat bantuan segera hadir',
    },
];

/** Mobile bottom bar entries (≤4 destinations; "Lainnya" holds the rest). */
export function mobileNavItems({
    activeTenant,
}: NavContext): OptiNavItem[] {
    const ops = navGroups({ activeTenant }).find(
        (group) => group.id === 'ops',
    );

    return (ops?.items ?? []).filter((item) => !item.disabled).slice(0, 4);
}

/** Always resolves to an icon: falls back to LayoutGrid when unset. */
export function navIcon(item: OptiNavItem): LucideIcon {
    return item.icon ?? (LayoutGrid as unknown as LucideIcon);
}

/** Icon tint for an item, falling back to the operations hue. */
export function navToneIcon(item: OptiNavItem): string {
    return navToneStyles[item.tone ?? 'ops'].icon;
}
