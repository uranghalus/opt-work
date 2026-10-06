import { usePage } from '@inertiajs/react';
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

/** Human labels for the seeded Spatie role slugs (RoleAndPermissionSeeder). */
const ROLE_LABELS: Record<string, string> = {
    super_admin: 'Super Admin',
    admin_tenant: 'Admin Cabang',
    general_manager: 'General Manager',
    deputy_general_manager: 'Deputy GM',
    hod: 'HOD',
    team_leader: 'Team Leader',
    karyawan: 'Karyawan',
    field_staff: 'Karyawan Pelaksana',
    viewer: 'Viewer / Auditor',
};

function roleLabel(role: string | null | undefined): string | null {
    if (!role) {
        return null;
    }

    return (
        ROLE_LABELS[role] ??
        role.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
    );
}

/** Identity block at the foot of the navigation panel. Matches mockup:
    avatar gradient, name, role, logout icon. Avatar click opens dropdown. */
export function NavUser() {
    const { auth } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const getInitials = useInitials();
    const cleanup = useMobileNavigation();

    if (!auth?.user) {
        return null;
    }

    // Primary Spatie role, with the email as a last-resort label.
    const userRole = roleLabel(auth.role) ?? auth.user.email;

    const handleLogout = () => {
        cleanup();
        submitLogout();
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <div className="flex items-center gap-2.5 rounded-xl bg-white/5 p-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <SidebarMenuButton
                                size="lg"
                                className={cn(
                                    'h-12 gap-2.5 rounded-xl px-2 text-panel-ink transition-colors duration-200',
                                    'hover:bg-panel-hover',
                                    'data-[state=open]:bg-panel-hover',
                                    'motion-reduce:transition-none',
                                )}
                            >
                                <Avatar className="size-9 rounded-xl ring-2 ring-brand-strong/40">
                                    <AvatarImage
                                        src={auth.user.avatar}
                                        alt={auth.user.name}
                                    />
                                    <AvatarFallback className="rounded-xl bg-gradient-to-tr from-brand-strong to-brand-deep text-sm font-bold text-on-brand">
                                        {getInitials(auth.user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="grid min-w-0 flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                                    <p className="truncate text-[0.8125rem] font-semibold text-panel-ink">
                                        {auth.user.name}
                                    </p>
                                    <p className="truncate text-[0.6875rem] font-normal text-panel-ink-muted">
                                        {userRole}
                                    </p>
                                </div>
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
                    <button
                        type="button"
                        onClick={handleLogout}
                        className="rounded-lg p-1.5 text-panel-ink-muted transition-colors group-data-[collapsible=icon]:hidden hover:bg-white/5 hover:text-panel-ink"
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
