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
import { index as rolesIndex } from '@/routes/roles';
import positions from '@/routes/positions';
import { edit as profileEdit } from '@/routes/profile';
import {
    index as workOrdersIndex,
    timeline as workOrdersTimeline,
} from '@/routes/work-orders';
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

/**
 * A renderable item: it has a destination.
 */
export type ActiveOptiNavItem = Omit<NavItem, 'disabled'> & {
    /** Group identity hue used for the icon tint. */
    tone?: NavTone;
    /** Compact label used by the mobile bottom navigation. */
    shortTitle?: string;
    /** Reads the shared unread notification count and renders a badge. */
    badge?: 'notifications';
    disabled?: false;
    /** Reason shown in the tooltip when an item is inert. */
    hint?: string;
};

/**
 * An inert item: no destination at all (not built yet, or no cabang
 * active) — never a fake URL.
 */
type InertOptiNavItem = Omit<NavItem, 'href' | 'disabled'> & {
    href?: undefined;
    /** Group identity hue used for the icon tint. */
    tone?: NavTone;
    /** Compact label used by the mobile bottom navigation. */
    shortTitle?: string;
    /** Reads the shared unread notification count and renders a badge. */
    badge?: 'notifications';
    disabled: true;
    /** Reason shown in the tooltip when an item is inert. */
    hint?: string;
};

export type OptiNavItem = ActiveOptiNavItem | InertOptiNavItem;

export type OptiNavGroup = {
    id: string;
    label: string;
    tone: NavTone;
    items: OptiNavItem[];
};

type NavContext = {
    activeTenant?: string | null;
    /** Unit Bisnis management is a platform-level, super-admin-only surface. */
    isSuperAdmin?: boolean;
    /**
     * Hak Akses surfaces are gated by the shared `can('role.read')` flag
     * (ADR-0004), computed through the Gate — not the raw permission list.
     */
    canReadRoles?: boolean;
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

/** A tenant-scoped item: usable with the active cabang, inert without one. */
function tenantItem(
    item: Omit<ActiveOptiNavItem, 'href' | 'disabled'>,
    activeTenant: string | null,
    build: (tenant: string) => NonNullable<NavItem['href']>,
): OptiNavItem {
    if (!activeTenant) {
        return {
            ...item,
            disabled: true,
            hint: item.hint ?? 'Pilih cabang untuk membuka modul ini',
        };
    }

    return { ...item, href: build(activeTenant) };
}

function modulePending(
    item: Omit<ActiveOptiNavItem, 'href' | 'disabled'>,
    name: string,
): InertOptiNavItem {
    return { ...item, disabled: true, hint: `${name} segera hadir` };
}

export function navGroups({
    activeTenant = null,
    isSuperAdmin = false,
    canReadRoles = false,
}: NavContext): OptiNavGroup[] {
    const tenant = activeTenant ?? null;

    const masterItems: OptiNavItem[] = [
        tenantItem(
            { title: 'Karyawan', icon: Users, tone: 'master' },
            tenant,
            (t) => employees.index({ tenant: t }),
        ),
        tenantItem(
            { title: 'Departemen', icon: Building2, tone: 'master' },
            tenant,
            (t) => departments.index({ tenant: t }),
        ),
        tenantItem(
            { title: 'Divisi', icon: Network, tone: 'master' },
            tenant,
            (t) => divisions.index({ tenant: t }),
        ),
        tenantItem(
            { title: 'Posisi', icon: BriefcaseBusiness, tone: 'master' },
            tenant,
            (t) => positions.index({ tenant: t }),
        ),
        ...(isSuperAdmin
            ? [
                  {
                      title: 'Unit Bisnis',
                      icon: Building2,
                      tone: 'master',
                      href: tenants.index(),
                  } satisfies OptiNavItem,
              ]
            : []),
    ];

    const groups: OptiNavGroup[] = [
        {
            id: 'ops',
            label: 'Operasional',
            tone: 'ops',
            items: [
                tenantItem(
                    {
                        title: 'Dashboard Utama',
                        shortTitle: 'Beranda',
                        icon: House,
                        tone: 'ops',
                    },
                    tenant,
                    (t) => dashboard({ tenant: t }),
                ),
                tenantItem(
                    {
                        title: 'Work Order',
                        shortTitle: 'WO',
                        icon: ClipboardList,
                        tone: 'ops',
                    },
                    tenant,
                    (t) => workOrdersIndex({ tenant: t }),
                ),
                tenantItem(
                    {
                        title: 'Terjadwal',
                        shortTitle: 'Jadwal',
                        icon: CalendarClock,
                        tone: 'ops',
                    },
                    tenant,
                    (t) => workOrdersTimeline({ tenant: t }),
                ),
                modulePending(
                    {
                        title: 'Daily Work',
                        shortTitle: 'Harian',
                        icon: LayoutGrid,
                        tone: 'ops',
                    },
                    'Daily Work',
                ),
                modulePending(
                    {
                        title: 'Work Data',
                        shortTitle: 'Data',
                        icon: Database,
                        tone: 'ops',
                    },
                    'Work Data',
                ),
                {
                    title: 'Notifikasi',
                    shortTitle: 'Notif',
                    icon: Bell,
                    tone: 'ops',
                    badge: 'notifications',
                    href: notificationsIndex(),
                },
            ],
        },
        {
            id: 'master',
            label: 'Data Master',
            tone: 'master',
            items: masterItems,
        },
        {
            // Akses — first-class module, deliberately outside Sistem and the
            // settings section (routes/hak-akses.php).
            id: 'access',
            label: 'Hak Akses',
            tone: 'system',
            items: [
                ...(canReadRoles
                    ? [
                          {
                              title: 'Peran',
                              shortTitle: 'Peran',
                              href: rolesIndex(),
                              icon: ShieldCheck,
                              tone: 'system',
                          } satisfies OptiNavItem,
                      ]
                    : []),
            ],
        },
        {
            id: 'system',
            label: 'Sistem',
            tone: 'system',
            items: [
                ...(isSuperAdmin
                    ? [
                          {
                              title: 'Pengaturan',
                              shortTitle: 'Atur',
                              href: profileEdit(),
                              icon: Settings,
                              tone: 'system',
                          } satisfies OptiNavItem,
                      ]
                    : []),
            ],
        },
    ];

    return groups.filter((group) => group.items.length > 0);
}

/**
 * Flat primary navigation for the header tabs, the tablet menu, and the
 * icon rail: the operations group plus role-gated system entries.
 */
export function mainNavItems(context: NavContext): OptiNavItem[] {
    const groups = navGroups(context);
    const ops = groups.find((group) => group.id === 'ops');
    const access = groups.find((group) => group.id === 'access');
    const system = groups.find((group) => group.id === 'system');

    return [
        ...(ops?.items ?? []),
        ...(access?.items ?? []),
        ...(system?.items ?? []),
    ];
}

/** Footer utilities: help is not shipped yet, so it stays inert. */
export const utilityNavItems: OptiNavItem[] = [
    {
        title: 'Bantuan',
        icon: LifeBuoy,
        disabled: true,
        hint: 'Pusat bantuan segera hadir',
    },
];

/** Mobile bottom bar entries (≤4 destinations; "Lainnya" holds the rest). */
export function mobileNavItems(context: NavContext): OptiNavItem[] {
    const ops = navGroups(context).find((group) => group.id === 'ops');

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
