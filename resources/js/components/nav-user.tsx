import { usePage } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';
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

/** Identity block at the foot of the navigation panel. */
export function NavUser() {
    const { auth } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const getInitials = useInitials();

    if (!auth.user) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            data-test="sidebar-menu-button"
                            className="h-12 gap-2.5 rounded-md px-2 text-panel-ink transition-colors duration-200 hover:bg-panel-hover data-[state=open]:bg-panel-hover motion-reduce:transition-none"
                        >
                            <Avatar className="size-7 rounded-full ring-1 ring-white/15">
                                <AvatarImage
                                    src={auth.user.avatar}
                                    alt={auth.user.name}
                                />
                                <AvatarFallback className="rounded-full bg-brand-strong/20 text-[0.625rem] font-semibold text-brand-strong">
                                    {getInitials(auth.user.name)}
                                </AvatarFallback>
                            </Avatar>
                            <span className="grid min-w-0 flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                                <span className="truncate text-[0.8125rem] font-semibold text-panel-ink">
                                    {auth.user.name}
                                </span>
                                <span className="truncate text-[0.6875rem] font-normal text-panel-ink-subtle">
                                    {auth.user.email}
                                </span>
                            </span>
                            <ChevronsUpDown
                                aria-hidden="true"
                                className="ms-auto size-4 shrink-0 text-panel-ink-subtle group-data-[collapsible=icon]:hidden"
                            />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-xl"
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
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
