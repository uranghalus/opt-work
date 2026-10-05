import { Link, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import {
    navGroups,
    navIcon,
    navToneStyles,
    utilityNavItems,
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
import type { InertiaConfig } from '@inertiajs/core';

/*
 * Navigation stratum: a deep ink-teal panel that owns wayfinding while the
 * work sits on the canvas. Group captions in tracked micro caps, module
 * icons tinted by group identity hue (never by status), a teal chip and a
 * 3px panel-edge indicator marking the current section. Collapses to a 72px
 * icon rail (⌘/Ctrl+B or the rail); the members' order never changes.
 */

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
    const tone = navToneStyles[item.tone ?? 'ops'];
    const active = !item.disabled && isCurrentOrParentUrl(item.href);

    const itemClasses = cn(
        'group/nav relative h-9 gap-2.5 rounded-md px-2 text-[0.8125rem] font-medium text-panel-ink-muted',
        'transition-[background-color,color] duration-200 ease-standard motion-reduce:transition-none',
        'hover:bg-panel-hover hover:text-panel-ink',
        'data-[active=true]:bg-panel-active! data-[active=true]:font-semibold data-[active=true]:text-panel-ink',
        'focus-visible:ring-2 focus-visible:ring-brand-strong/70',
    );

    const icon = (
        <Icon
            aria-hidden="true"
            className={cn(
                'size-4 shrink-0 transition-colors duration-200 motion-reduce:transition-none',
                item.disabled ? 'text-panel-ink-subtle' : tone.icon,
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
                            className={cn(itemClasses, 'cursor-not-allowed opacity-55')}
                        >
                            {icon}
                            <span className="truncate">{item.title}</span>
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
                    className="absolute -left-2 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-full bg-brand-strong"
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
                    className="cursor-pointer"
                >
                    {icon}
                    <span className="truncate">{item.title}</span>
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
                        <PanelNavItem key={item.title} item={item} badge={badge} />
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}

/** Cabang context: the active branch this session operates in. */
function TenantChip({ tenant }: { tenant: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 self-start rounded-full border border-panel-border bg-white/5 px-2.5 py-1 text-[0.6875rem] font-medium text-panel-ink-muted group-data-[collapsible=icon]:hidden">
            <Building2 aria-hidden="true" className="size-3.5 text-brand-strong" />
            <span className="text-panel-ink-subtle">Cabang</span>
            <span className="max-w-32 truncate font-semibold text-panel-ink">
                {tenant}
            </span>
        </span>
    );
}

export function AppSidebar() {
    const { permissions, activeTenant, unreadNotificationsCount } = usePage<
        InertiaConfig['sharedPageProps']
    >().props;
    const { state } = useSidebar();
    const groups = navGroups({ activeTenant, permissions });
    const unread = Number(unreadNotificationsCount ?? 0);

    return (
        <Sidebar
            collapsible="icon"
            className="group-data-[side=left]:border-panel-border"
        >
            <SidebarHeader className="gap-3 px-3 pt-3 pb-2 group-data-[collapsible=icon]:px-2">
                <Link
                    href={dashboard()}
                    prefetch
                    aria-label="Beranda OptiWorks"
                    className="-m-1 flex items-center rounded-md p-1 transition-colors duration-150 hover:bg-panel-hover focus-visible:ring-2 focus-visible:ring-brand-strong/70 focus-visible:outline-none"
                >
                    <AppLogo
                        variant="panel"
                        compact={state === 'collapsed'}
                    />
                </Link>
                {activeTenant && <TenantChip tenant={activeTenant} />}
            </SidebarHeader>

            <SidebarContent className="gap-1">
                {groups.map((group) => (
                    <PanelNavGroup
                        key={group.id}
                        group={group}
                        badge={unread}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter className="gap-1 border-t border-panel-border px-3 pt-2 pb-3 group-data-[collapsible=icon]:px-2">
                <SidebarMenu className="gap-1">
                    {utilityNavItems.map((item) => (
                        <PanelNavItem key={item.title} item={item} />
                    ))}
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>

            <SidebarRail aria-label="Ubah ukuran panel navigasi" />
        </Sidebar>
    );
}
