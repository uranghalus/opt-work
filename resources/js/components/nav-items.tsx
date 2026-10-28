import {
    BadgeCheck,
    Bell,
    Boxes,
    BriefcaseBusiness,
    Building2,
    CalendarClock,
    ClipboardList,
    Database,
    FileClock,
    House,
    Hourglass,
    KeyRound,
    LayoutGrid,
    ListChecks,
    ListTree,
    Network,
    Settings,
    ShieldCheck,
    Users,
    Wrench,
    type LucideIcon,
} from 'lucide-react';
import { dashboard } from '@/routes';
import departments from '@/routes/departments';
import divisions from '@/routes/divisions';
import employees from '@/routes/employees';
import {
    index as dailyWorkIndex,
    templates as dailyWorkTemplates,
} from '@/routes/daily-work';
import { index as notificationsIndex } from '@/routes/notifications';
import { index as permissionsIndex } from '@/routes/permissions';
import { index as rolesIndex } from '@/routes/roles';
import { index as workDataIndex } from '@/routes/work-data';
import positions from '@/routes/positions';
import { tree as positionsTreeIndex } from '@/routes/positions';
import { edit as profileEdit } from '@/routes/profile';
import {
    index as workOrdersIndex,
    approvals as workOrdersApprovals,
    extendApprovals as workOrdersExtendApprovals,
    my as workOrdersMy,
    timeline as workOrdersTimeline,
} from '@/routes/work-orders';
import { verification as verificationRoute } from '@/routes/daily-work';
import tenants from '@/routes/tenants';
import type { InertiaConfig } from '@inertiajs/core';
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
 * Single translation point from the shared page props into nav gating
 * flags (ADR-0004/ADR-0011): every navigation surface — sidebar, mobile
 * sheet, future surfaces — must derive its NavContext through this helper
 * so items never appear on one surface and vanish on another.
 */
export function navContext(props: InertiaConfig['sharedPageProps']): NavContext {
    const auth = props.auth;

    return {
        activeTenant: props.activeTenant,
        isSuperAdmin: auth?.isSuperAdmin,
        canReadRoles: auth?.can?.['role.read'],
        canReadPermissions: auth?.can?.['permission.read'],
        canReadWorkData: auth?.can?.['work-data.read'],
        canReadDailyWork: auth?.can?.['daily-work.read'],
        canManageDailyWork: auth?.can?.['daily-work.create'],
        canReadWorkOrder: auth?.can?.['work-order.read'],
        canReadDivision: auth?.can?.['division.read'],
        canReadDepartment: auth?.can?.['department.read'],
        canReadTenant: auth?.can?.['tenant.read'],
        canVerifyDailyWork: auth?.can?.['daily-work.verify'],
        canApproveRequests: auth?.can?.['work-order.approve-request'],
        hasEmployeeLink: auth?.user?.employee_id != null,
        canDecideExtends:
            (auth?.can?.['work-order.extend-approve'] ?? false) ||
            (auth?.isSuperAdmin ?? false) ||
            (auth?.user?.roles?.includes('general_manager') ?? false) ||
            (auth?.user?.roles?.includes('deputy_general_manager') ?? false),
    };
}

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
    /**
     * Izin (permissions) surface is gated by `can('permission.read')`
     * (ADR-0004), computed through the same Gate — never raw permission list.
     */
    canReadPermissions?: boolean;
    /**
     * Work Data (FR-3.x) is gated by `can('work-data.read')` — histori
     * pekerjaan per WO closed.
     */
    canReadWorkData?: boolean;
    /**
     * Daily Work (FR-5.x) is gated by `can('daily-work.read')` — daftar
     * harian karyawan. The HOD template surface additionally needs
     * `daily-work.create`.
     */
    canReadDailyWork?: boolean;
    canManageDailyWork?: boolean;
    /**
     * FR-6.2: work-order CRUD read gate — `work-order.read`.
     */
    canReadWorkOrder?: boolean;
    /**
     * FR-6.2: master data read gates — `division.read` / `department.read` /
     * `tenant.read`.
     */
    canReadDivision?: boolean;
    canReadDepartment?: boolean;
    canReadTenant?: boolean;
    /**
     * FR-6.2: Daily Work verification — `daily-work.verify`.
     */
    canVerifyDailyWork?: boolean;
    /**
     * ADR-0011: Approval inbox — permintaan WO lintas-department dari
     * department pengaju, gated `can('work-order.approve-request')`.
     */
    canApproveRequests?: boolean;
    /**
     * ADR-0011 R15: "Tugasan Saya" — hanya untuk akun ter-link karyawan
     * (pelaksana potensial); cek employee_id, bukan permission.
     */
    hasEmployeeLink?: boolean;
    /**
     * R21: inbox "Persetujuan Extend" — TL/HOD (extend-approve), DGM/GM
     * (extend jadwal), atau super admin.
     */
    canDecideExtends?: boolean;
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
    canReadPermissions = false,
    canReadWorkData = false,
    canReadDailyWork = false,
    canManageDailyWork = false,
    canReadWorkOrder = false,
    canReadDivision = false,
    canReadDepartment = false,
    canReadTenant = false,
    canVerifyDailyWork = false,
    canApproveRequests = false,
    hasEmployeeLink = false,
    canDecideExtends = false,
}: NavContext): OptiNavGroup[] {
    const tenant = activeTenant ?? null;

    const masterItems: OptiNavItem[] = [
        tenantItem(
            { title: 'Karyawan', icon: Users, tone: 'master' },
            tenant,
            (t) => employees.index({ tenant: t }),
        ),
        ...(canReadDepartment
            ? [
                  tenantItem(
                      { title: 'Departemen', icon: Building2, tone: 'master' },
                      tenant,
                      (t) => departments.index({ tenant: t }),
                  ),
              ]
            : []),
        ...(canReadDivision
            ? [
                  tenantItem(
                      { title: 'Divisi', icon: Network, tone: 'master' },
                      tenant,
                      (t) => divisions.index({ tenant: t }),
                  ),
              ]
            : []),
        tenantItem(
            { title: 'Posisi', icon: BriefcaseBusiness, tone: 'master' },
            tenant,
            (t) => positions.index({ tenant: t }),
        ),
        tenantItem(
            { title: 'Struktur Jabatan', icon: ListTree, tone: 'master' },
            tenant,
            (t) => positionsTreeIndex({ tenant: t }),
        ),
        ...(isSuperAdmin
            ? [
                  {
                      title: 'Unit Bisnis',
                      icon: Boxes,
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
                ...(canReadWorkOrder
                    ? [
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
                      ]
                    : []),
                ...(hasEmployeeLink
                    ? [
                          tenantItem(
                              {
                                  title: 'Tugasan Saya',
                                  shortTitle: 'Tugas',
                                  icon: Wrench,
                                  tone: 'ops',
                              },
                              tenant,
                              (t) => workOrdersMy({ tenant: t }),
                          ),
                      ]
                    : []),
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
                ...(canApproveRequests
                    ? [
                          tenantItem(
                              {
                                  title: 'Approval Permintaan',
                                  shortTitle: 'Approval',
                                  icon: FileClock,
                                  tone: 'ops',
                              },
                              tenant,
                              (t) => workOrdersApprovals({ tenant: t }),
                          ),
                      ]
                    : []),
                ...(canDecideExtends
                    ? [
                          tenantItem(
                              {
                                  title: 'Persetujuan Extend',
                                  shortTitle: 'Extend',
                                  icon: Hourglass,
                                  tone: 'ops',
                              },
                              tenant,
                              (t) => workOrdersExtendApprovals({ tenant: t }),
                          ),
                      ]
                    : []),
                ...(canReadDailyWork
                    ? [
                          tenantItem(
                              {
                                  title: 'Daily Work',
                                  shortTitle: 'Harian',
                                  icon: LayoutGrid,
                                  tone: 'ops',
                              },
                              tenant,
                              (t) => dailyWorkIndex({ tenant: t }),
                          ),
                      ]
                    : [
                          modulePending(
                              {
                                  title: 'Daily Work',
                                  shortTitle: 'Harian',
                                  icon: LayoutGrid,
                                  tone: 'ops',
                              },
                              'Daily Work',
                          ),
                      ]),
                ...(canVerifyDailyWork
                    ? [
                          tenantItem(
                              {
                                  title: 'Perlu Verifikasi',
                                  shortTitle: 'Verifikasi',
                                  icon: BadgeCheck,
                                  tone: 'ops',
                              },
                              tenant,
                              (t) => verificationRoute({ tenant: t }),
                          ),
                      ]
                    : []),
                ...(canManageDailyWork
                    ? [
                          tenantItem(
                              {
                                  title: 'Template Daily',
                                  shortTitle: 'Template',
                                  icon: ListChecks,
                                  tone: 'ops',
                              },
                              tenant,
                              (t) => dailyWorkTemplates({ tenant: t }),
                          ),
                      ]
                    : []),
                ...(canReadWorkData
                    ? [
                          tenantItem(
                              {
                                  title: 'Work Data',
                                  shortTitle: 'Data',
                                  icon: Database,
                                  tone: 'ops',
                              },
                              tenant,
                              (t) => workDataIndex({ tenant: t }),
                          ),
                      ]
                    : [
                          modulePending(
                              {
                                  title: 'Work Data',
                                  shortTitle: 'Data',
                                  icon: Database,
                                  tone: 'ops',
                              },
                              'Work Data',
                          ),
                      ]),
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
                ...(canReadPermissions
                    ? [
                          {
                              title: 'Izin',
                              shortTitle: 'Izin',
                              href: permissionsIndex(),
                              icon: KeyRound,
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
            items: isSuperAdmin
                ? [
                      {
                          title: 'Pengaturan',
                          shortTitle: 'Atur',
                          href: profileEdit(),
                          icon: Settings,
                          tone: 'system',
                      } satisfies OptiNavItem,
                  ]
                : [],
        },
    ];

    return groups.filter((group) => group.items.length > 0);
}

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
