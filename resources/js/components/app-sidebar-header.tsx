import { Link, usePage } from '@inertiajs/react';
import { Bell, ChevronDown } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ThemeToggle } from '@/components/theme-toggle';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useScrolled } from '@/hooks/use-scrolled';
import { cn } from '@/lib/utils';
import { index as notificationsIndex } from '@/routes/notifications';
import type { BreadcrumbItem } from '@/types';
import type { InertiaConfig } from '@inertiajs/core';

/*
 * Top bar of the content stratum: shell trigger, breadcrumb trail, and the
 * account cluster (appearance, notifications, identity). The bar stays calm
 * at rest and lifts off the page with a hairline and soft shadow only once
 * content scrolls beneath it.
 */

function NotificationButton() {
    const { unreadNotificationsCount } = usePage<
        InertiaConfig['sharedPageProps']
    >().props;
    const unread = Number(unreadNotificationsCount ?? 0);

    return (
        <Button
            variant="ghost"
            size="icon"
            asChild
            className="relative size-9 cursor-pointer rounded-md text-ink-muted after:absolute after:-inset-1 after:content-[''] hover:bg-accent hover:text-foreground"
        >
            <Link
                href={notificationsIndex()}
                aria-label={`Notifikasi${unread > 0 ? ` (${unread} belum dibaca)` : ''}`}
            >
                <Bell aria-hidden="true" className="size-[18px]" />
                {unread > 0 && (
                    <span
                        aria-hidden="true"
                        className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand px-1 text-[0.625rem] leading-none font-semibold text-on-brand ring-2 ring-surface tabular-nums"
                    >
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </Link>
        </Button>
    );
}

function UserButton() {
    const { auth } = usePage<InertiaConfig['sharedPageProps']>().props;
    const getInitials = useInitials();

    if (!auth?.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    aria-label={`Menu pengguna: ${auth.user.name}`}
                    className="h-9 cursor-pointer gap-1.5 rounded-full pr-2 pl-1 hover:bg-accent"
                >
                    <Avatar className="size-7 rounded-full ring-1 ring-border">
                        <AvatarImage
                            src={auth.user.avatar}
                            alt={auth.user.name}
                        />
                        <AvatarFallback className="rounded-full bg-brand-soft text-[0.625rem] font-semibold text-brand">
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <ChevronDown
                        aria-hidden="true"
                        className="size-3.5 text-ink-subtle"
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="w-64 rounded-xl"
            >
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
}) {
    const scrolled = useScrolled();

    return (
        <header
            className={cn(
                'sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b px-3 sm:px-5',
                'transition-[background-color,border-color,box-shadow] duration-200 ease-standard motion-reduce:transition-none',
                scrolled
                    ? 'border-border bg-surface/90 shadow-[var(--shadow-card)] backdrop-blur-md'
                    : 'border-transparent bg-surface/70 backdrop-blur-sm',
            )}
        >
            <SidebarTrigger
                aria-label="Buka atau tutup panel navigasi"
                className="relative -ml-1 size-9! cursor-pointer rounded-md text-ink-muted after:absolute after:-inset-1 after:content-[''] hover:bg-accent hover:text-foreground"
            />
            <span
                aria-hidden="true"
                className="mx-1 hidden h-5 w-px shrink-0 bg-border sm:block"
            />
            <div className="min-w-0 flex-1">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex shrink-0 items-center gap-1.5">
                <ThemeToggle className="hidden sm:inline-flex" />
                <NotificationButton />
                <UserButton />
            </div>
        </header>
    );
}
