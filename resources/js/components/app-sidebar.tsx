import { Link, usePage } from '@inertiajs/react';
import { Building2, ChevronDown, Plus } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import {
    navGroups,
    navIcon,
    navToneStyles,
    type OptiNavGroup,
    type OptiNavItem,
} from '@/components/nav-items';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create as workOrdersCreate } from '@/routes/work-orders';
import type { InertiaConfig } from '@inertiajs/core';

/**
 * Navigation stratum matching Stitch reference (OptiWorks Main Overview).
 * Dark navy panel (#0B151C) with emerald gradient accent, brand logo, cabang
 * selector, gradient CTA, grouped nav with active emerald pill + left border,
 * and footer identity strip. Collapses to 4.5rem icon rail.
 */

/** Group hues: identity for a navigation area — never a status signal. */
const groupTones: Record<string, string> = {
    ops: navToneStyles.ops.icon,
    master: navToneStyles.master.icon,
    system: navToneStyles.system.icon,
};

function UnreadBadge({ count }: { count: number }) {
    const label = count > 99 ? '99+' : count;

    return (
        <span
            aria-hidden="true"
            className={cn(
                'ms-auto flex h-5 min-w-5 items-center justify-center rounded-full px-1.5',
                'bg-brand-strong/20 text-[0.6875rem] leading-none font-semibold text-brand-strong tabular-nums',
                'group-data-[collapsible=icon]:absolute group-data-[collapsible=icon]:top-1.5 group-data-[collapsible=icon]:right-1.5',
                'group-data-[collapsible=icon]:size-2 group-data-[collapsible=icon]:min-w-0 group-data-[collapsible=icon]:bg-brand-strong group-data-[collapsible=icon]:p-0 group-data-[collapsible=icon]:text-transparent',
            )}
        >
            {label}
        </span>
    );
}

function PanelNavItem({
    item,
    badge = 0,
}: {
    item: OptiNavItem;
    badge?: number;
}) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const Icon = navIcon(item);
    const tone = item.tone ?? 'ops';
    const iconClass = groupTones[tone] ?? groupTones.ops;
    const active = !item.disabled && isCurrentOrParentUrl(item.href);

    const itemClasses = cn(
        'group/nav relative h-10 gap-3 rounded-xl px-3 text-[0.8125rem] font-medium text-panel-ink-muted overflow-hidden',
        'transition-[background-color,color] duration-200 ease-standard motion-reduce:transition-none',
        'hover:bg-white/5 hover:text-panel-ink',
        'data-[active=true]:bg-brand-strong/15! data-[active=true]:font-semibold data-[active=true]:text-brand-strong',
        'focus-visible:ring-2 focus-visible:ring-brand-strong/70 focus-visible:outline-none',
        'group-data-[collapsible=icon]:justify-center',
    );

    const icon = (
        <Icon
            aria-hidden="true"
            className={cn(
                'size-5 shrink-0 transition-colors duration-200 motion-reduce:transition-none',
                item.disabled ? 'text-panel-ink-subtle' : iconClass,
            )}
        />
    );

    if (item.disabled) {
        return (
            <SidebarMenuItem>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <SidebarMenuButton
                            aria-disabled="true"
                            className={cn(
                                itemClasses,
                                'cursor-not-allowed opacity-55 overflow-hidden',
                            )}
                        >
                            {icon}
                            <span className="truncate group-data-[collapsible=icon]:hidden">
                                {item.title}
                            </span>
                            <span className="ms-auto rounded-full bg-white/5 px-1.5 py-0.5 text-[0.625rem] font-semibold tracking-wide text-panel-ink-subtle uppercase group-data-[collapsible=icon]:hidden">
                                Segera
                            </span>
                        </SidebarMenuButton>
                    </TooltipTrigger>
                    <TooltipContent side="right">
                        {item.hint ?? `${item.title} segera hadir`}
                    </TooltipContent>
                </Tooltip>
            </SidebarMenuItem>
        );
    }

    return (
        <SidebarMenuItem>
            {active && (
                <span
                    aria-hidden="true"
                    className="absolute top-1/2 left-0 h-6 w-[3px] -translate-y-1/2 rounded-r-full bg-brand-strong"
                />
            )}
            <SidebarMenuButton
                asChild
                isActive={active}
                tooltip={{ children: item.title }}
                className={itemClasses}
            >
                <Link
                    href={item.href}
                    prefetch
                    aria-current={active ? 'page' : undefined}
                    className="min-w-0 cursor-pointer"
                >
                    {icon}
                    <span className="min-w-0 flex-1 truncate group-data-[collapsible=icon]:hidden">
                        {item.title}
                    </span>
                    {item.badge === 'notifications' && badge > 0 && (
                        <UnreadBadge count={badge} />
                    )}
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

function PanelNavGroup({
    group,
    badge,
}: {
    group: OptiNavGroup;
    badge: number;
}) {
    return (
        <SidebarGroup className="px-2 py-1">
            <SidebarGroupLabel className="h-7 px-2 text-[0.6875rem]! font-semibold tracking-[0.08em] text-panel-ink-subtle! uppercase">
                {group.label}
            </SidebarGroupLabel>
            <SidebarGroupContent>
                <SidebarMenu className="gap-1">
                    {group.items.map((item) => (
                        <PanelNavItem
                            key={item.title}
                            item={item}
                            badge={badge}
                        />
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}

/** Cabang context: the active branch this session operates in. */
function TenantChip({ tenant }: { tenant: string | null }) {
    return (
        <span
            title={tenant ? `Cabang: ${tenant}` : 'Belum ada cabang aktif'}
            className={cn(
                'flex w-full items-center justify-between gap-2.5 rounded-xl border border-slate-700/60 bg-panel-raised/80 p-2.5 text-left',
                'transition-colors duration-150 hover:border-slate-600 motion-reduce:transition-none',
                'overflow-hidden',
                'group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:border-transparent group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:p-0',
            )}
        >
            <span className="flex min-w-0 items-center gap-2.5">
                <span
                    aria-hidden="true"
                    className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-blue-500/15 text-blue-400"
                >
                    <Building2 className="size-4" />
                </span>
                <span className="min-w-0 group-data-[collapsible=icon]:hidden">
                    <span className="block text-[0.625rem] leading-none font-bold tracking-wider text-slate-400 uppercase">
                        Cabang Utama
                    </span>
                    <span className="mt-0.5 block truncate text-xs font-semibold text-slate-200">
                        {tenant ?? 'Pilih cabang'}
                    </span>
                </span>
            </span>
            <ChevronDown
                aria-hidden="true"
                className="size-4 shrink-0 text-slate-400 group-data-[collapsible=icon]:hidden"
            />
        </span>
    );
}

/** Primary action: raise a new Work Order from anywhere in the shell. */
function CreateWorkOrderButton({ tenant }: { tenant: string | null }) {
    const { permissions } = usePage<InertiaConfig['sharedPageProps']>().props;
    const hasPermission = permissions['work-order.create'];

    if (!hasPermission) {
        return null;
    }

    if (!tenant) {
        return (
            <span
                title="Pilih cabang untuk membuat Work Order"
                className={cn(
                    'flex h-10 w-full items-center justify-center gap-2 rounded-xl text-xs font-bold text-white/60',
                    'bg-slate-700/60 cursor-not-allowed',
                    'group-data-[collapsible=icon]:hidden',
                )}
            >
                <Plus aria-hidden="true" className="size-5 shrink-0 opacity-60" />
                <span>Buat Work Order</span>
            </span>
        );
    }

    return (
        <Link
            href={workOrdersCreate({ tenant })}
            title="Buat Work Order"
            className={cn(
                'flex h-10 w-full items-center justify-center gap-2 rounded-xl text-xs font-bold text-white',
                'bg-linear-to-r from-[#059669] to-[#0d9488] shadow-md shadow-emerald-950/40',
                'transition-[filter,transform] duration-200 hover:brightness-110 active:scale-[0.98] motion-reduce:transition-none',
                'focus-visible:ring-2 focus-visible:ring-brand-strong/70 focus-visible:outline-none',
            )}
        >
            <Plus aria-hidden="true" className="size-5 shrink-0" />
            <span className="group-data-[collapsible=icon]:hidden">
                Buat Work Order
            </span>
        </Link>
    );
}

export function AppSidebar() {
    const { permissions, activeTenant, unreadNotificationsCount } =
        usePage<InertiaConfig['sharedPageProps']>().props;
    const { state } = useSidebar();
    const groups = navGroups({ activeTenant, permissions });
    const unread = Number(unreadNotificationsCount ?? 0);

    return (
        <Sidebar
            collapsible="icon"
            className="bg-[#0B151C] text-panel-ink group-data-[side=left]:border-r group-data-[side=left]:border-slate-800 overflow-x-hidden"
        >
            <SidebarHeader className="gap-4 px-4 pt-4 pb-2 group-data-[collapsible=icon]:px-2 overflow-hidden">
                <Link
                    href={dashboard()}
                    prefetch
                    aria-label="Beranda OptiWorks"
                    className="-m-1 flex items-center gap-3 rounded-md px-2 py-1 transition-colors duration-150 hover:bg-white/5 focus-visible:ring-2 focus-visible:ring-brand-strong/70 focus-visible:outline-none group-data-[collapsible=icon]:justify-center"
                >
                    <AppLogo
                        variant="panel"
                        compact={state === 'collapsed'}
                        badge="PRO"
                        subtitle="Enterprise Operations"
                    />
                </Link>
                <TenantChip tenant={activeTenant} />
                <CreateWorkOrderButton tenant={activeTenant} />
            </SidebarHeader>

            <SidebarContent className="gap-1 overflow-x-hidden overflow-y-auto">
                {groups.map((group) => (
                    <PanelNavGroup
                        key={group.id}
                        group={group}
                        badge={unread}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter className="gap-1 border-t border-slate-800 bg-[#0E1B24] px-3 pt-2 pb-3 group-data-[collapsible=icon]:px-2">
                <SidebarMenu className="gap-1">
                    {/* Footer utilities live in the NavUser strip now. */}
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>

            <SidebarRail aria-label="Ubah ukuran panel navigasi" />
        </Sidebar>
    );
}


