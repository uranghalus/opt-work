import { Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { BranchSwitcher } from '@/components/branch-switcher';
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
import { home } from '@/routes';
import { create as workOrdersCreate } from '@/routes/work-orders';
import type { InertiaConfig } from '@inertiajs/core';

/**
 * Navigation stratum matching Stitch reference (OptiWorks Main Overview).
 * Dark navy panel (#0B151C) with emerald gradient accent, brand logo, cabang
 * selector, gradient CTA, grouped nav with active emerald pill + left border,
 * and footer identity strip. Collapses to 4.5rem icon rail.
 */

/** Group hues: identity for a navigation area â€” never a status signal. */
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
                'bg-[#2D82B7]/25 text-[0.6875rem] leading-none font-bold text-[#3D9BD6] ring-1 ring-[#3D9BD6]/30 tabular-nums',
                'group-data-[collapsible=icon]:absolute group-data-[collapsible=icon]:top-1.5 group-data-[collapsible=icon]:right-1.5',
                'group-data-[collapsible=icon]:size-2 group-data-[collapsible=icon]:min-w-0 group-data-[collapsible=icon]:bg-[#3D9BD6] group-data-[collapsible=icon]:p-0 group-data-[collapsible=icon]:text-transparent',
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
        'group/nav relative h-10 gap-3 overflow-hidden rounded-xl px-3 text-[0.8125rem] font-medium text-panel-ink-muted',
        'transition-all duration-200 ease-standard motion-reduce:transition-none',
        'hover:bg-white/[0.06] hover:text-white',
        'data-[active=true]:bg-[#2D82B7]/18! data-[active=true]:font-semibold data-[active=true]:text-white!',
        'data-[active=true]:shadow-[inset_0_1px_0_rgba(255,255,255,0.1),0_2px_8px_rgba(45,130,183,0.25)]',
        'focus-visible:ring-2 focus-visible:ring-[#3D9BD6]/70 focus-visible:outline-none',
        // Collapsed, the button is a 40px square inside a full-width row
        'group-data-[collapsible=icon]:mx-auto group-data-[collapsible=icon]:justify-center',
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
                                'cursor-not-allowed overflow-hidden opacity-55',
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
                    className="absolute top-1/2 left-0 h-6 w-[3.5px] -translate-y-1/2 rounded-r-full bg-[#3D9BD6] shadow-[0_0_10px_rgba(61,155,214,0.6)]"
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

/** Primary action: raise a new Work Order from anywhere in the shell. */
function CreateWorkOrderButton({ tenant }: { tenant: string | null }) {
    if (!tenant) {
        return (
            <span
                title="Pilih cabang untuk membuat Work Order"
                className={cn(
                    'flex h-10 w-full items-center justify-center gap-2 rounded-xl text-xs font-bold text-white/60',
                    'cursor-not-allowed bg-slate-700/60',
                    'group-data-[collapsible=icon]:hidden',
                )}
            >
                <Plus
                    aria-hidden="true"
                    className="size-5 shrink-0 opacity-60"
                />
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
                'bg-gradient-to-r from-[#2D82B7] via-[#246D9B] to-[#1C567B]',
                'shadow-md shadow-[#2D82B7]/30 hover:shadow-lg hover:shadow-[#2D82B7]/40 hover:brightness-105 active:scale-[0.98]',
                'ring-1 ring-inset ring-white/20',
                'transition-all duration-200 ease-standard motion-reduce:transition-none',
                'focus-visible:ring-2 focus-visible:ring-[#3D9BD6]/70 focus-visible:outline-none',
            )}
        >
            <Plus aria-hidden="true" className="size-4.5 shrink-0 stroke-[2.5]" />
            <span className="group-data-[collapsible=icon]:hidden">
                Buat Work Order
            </span>
        </Link>
    );
}

export function AppSidebar() {
    const { activeTenant, unreadNotificationsCount, auth } =
        usePage<InertiaConfig['sharedPageProps']>().props;
    const { state } = useSidebar();
    const groups = navGroups({
        activeTenant,
        isSuperAdmin: auth?.isSuperAdmin,
        canReadRoles: auth?.can?.['role.read'],
        canReadPermissions: auth?.can?.['permission.read'],
        canReadWorkData: auth?.can?.['work-data.read'],
    });
    const unread = Number(unreadNotificationsCount ?? 0);

    return (
        <Sidebar
            collapsible="icon"
            className="overflow-x-hidden bg-[#0B141E] text-panel-ink group-data-[side=left]:border-r group-data-[side=left]:border-white/[0.08]"
        >
            <SidebarHeader className="gap-3.5 overflow-hidden px-4 pt-4 pb-2 group-data-[collapsible=icon]:px-2">
                <Link
                    href={home()}
                    prefetch
                    aria-label="Beranda OptiWorks"
                    className="-m-1 flex items-center gap-3 rounded-xl px-2 py-1.5 transition-all duration-150 group-data-[collapsible=icon]:justify-center hover:bg-white/[0.06] focus-visible:ring-2 focus-visible:ring-[#3D9BD6]/70 focus-visible:outline-none"
                >
                    <AppLogo
                        variant="panel"
                        compact={state === 'collapsed'}
                        subtitle="Enterprise Operations"
                    />
                </Link>
                <BranchSwitcher variant="sidebar" />
                <CreateWorkOrderButton tenant={activeTenant} />
            </SidebarHeader>

            <SidebarContent className="gap-1 overflow-x-hidden overflow-y-auto px-1 py-1">
                {groups.map((group) => (
                    <PanelNavGroup
                        key={group.id}
                        group={group}
                        badge={unread}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter className="gap-1 border-t border-white/[0.08] bg-[#09111A] px-3 pt-2.5 pb-3 group-data-[collapsible=icon]:px-2">
                <SidebarMenu className="gap-1">
                    {/* Footer utilities live in the NavUser strip now. */}
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>

            <SidebarRail aria-label="Ubah ukuran panel navigasi" />
        </Sidebar>
    );
}
