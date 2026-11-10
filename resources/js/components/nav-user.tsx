import { usePage } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import { ChevronsUpDown, LogOut } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';
import { submitLogout } from '@/lib/logout';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';

/** Identity block at the foot of the navigation panel: avatar, name, logout.
    Avatar click opens the account dropdown. The former role line is gone with
    RBAC; the identity flag returns with it. */
export function NavUser() {
    const { auth } = usePage<InertiaConfig['sharedPageProps']>().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const getInitials = useInitials();
    const cleanup = useMobileNavigation();

    if (!auth?.user) {
        return null;
    }

    const handleLogout = () => {
        cleanup();
        submitLogout();
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <div className="flex items-center gap-2 rounded-xl border border-white/[0.08] bg-white/[0.05] p-1.5 shadow-xs transition-colors hover:bg-white/[0.08]">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <SidebarMenuButton
                                size="lg"
                                className={cn(
                                    'h-11 gap-2.5 rounded-xl px-2 text-panel-ink transition-colors duration-200',
                                    'hover:bg-panel-hover',
                                    'data-[state=open]:bg-panel-hover',
                                    'motion-reduce:transition-none',
                                )}
                            >
                                <Avatar className="size-8.5 rounded-xl ring-2 ring-[#3D9BD6]/40">
                                    <AvatarImage
                                        src={auth.user.avatar}
                                        alt={auth.user.name}
                                    />
                                    <AvatarFallback className="rounded-xl bg-gradient-to-tr from-[#2D82B7] to-[#1A4E70] text-sm font-bold text-white shadow-xs">
                                        {getInitials(auth.user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="grid min-w-0 flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                                    <p className="truncate text-[0.8125rem] font-semibold text-panel-ink">
                                        {auth.user.name}
                                    </p>
                                    <p className="truncate text-[0.6875rem] font-normal text-panel-ink-muted">
                                        {auth.user.email}
                                    </p>
                                </div>
                                <ChevronsUpDown
                                    aria-hidden="true"
                                    className="ms-auto size-4 shrink-0 text-panel-ink-subtle group-data-[collapsible=icon]:hidden"
                                />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-xl shadow-soft-lg"
                            align="end"
                            side={
                                isMobile
                                    ? 'bottom'
                                    : state === 'collapsed'
                                      ? 'right'
                                      : 'bottom'
                            }
                            sideOffset={8}
                        >
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <button
                        type="button"
                        onClick={handleLogout}
                        className="rounded-lg p-2 text-panel-ink-muted transition-colors group-data-[collapsible=icon]:hidden hover:bg-white/10 hover:text-white"
                        aria-label="Keluar / Logout"
                        title="Keluar"
                    >
                        <LogOut className="size-4" />
                    </button>
                </div>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
