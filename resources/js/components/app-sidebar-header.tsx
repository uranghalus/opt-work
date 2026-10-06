import { usePage, Link } from '@inertiajs/react';
import { Bell, ChevronDown, HelpCircle, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ThemeToggle } from '@/components/theme-toggle';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useScrolled } from '@/hooks/use-scrolled';
import { cn } from '@/lib/utils';
import { index as notificationsIndex } from '@/routes/notifications';
import { index as workOrdersIndex } from '@/routes/work-orders';
import type { BreadcrumbItem } from '@/types';
import type { InertiaConfig } from '@inertiajs/core';

/**
 * Top bar of the content stratum: shell trigger, breadcrumb trail, WO search (⌘/Ctrl+K),
 * and the account cluster (appearance, notifications, help, identity).
 */

function NotificationButton() {
    const { unreadNotificationsCount } =
        usePage<InertiaConfig['sharedPageProps']>().props;
    const unread = Number(unreadNotificationsCount ?? 0);

    return (
        <Button
            variant="ghost"
            size="icon"
            asChild
            className="relative size-9 cursor-pointer rounded-lg text-ink-muted after:absolute after:-inset-1 after:content-[''] hover:bg-accent hover:text-foreground"
        >
            <Link
                href={notificationsIndex()}
                aria-label={`Notifikasi${unread > 0 ? ` (${unread} belum dibaca)` : ''}`}
            >
                <Bell aria-hidden="true" className="size-[18px]" />
                {unread > 0 && (
                    <span
                        aria-hidden="true"
                        className="absolute top-1.5 right-1.5 size-2 rounded-full bg-danger ring-2 ring-surface"
                    />
                )}
            </Link>
        </Button>
    );
}

function HelpButton() {
    return (
        <Button
            variant="ghost"
            size="icon"
            aria-label="Pusat Bantuan"
            className="size-9 cursor-pointer rounded-lg text-ink-muted hover:bg-accent hover:text-foreground"
        >
            <HelpCircle aria-hidden="true" className="size-[18px]" />
        </Button>
    );
}

function WorkOrderSearch() {
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;
    const inputRef = useRef<HTMLInputElement>(null);
    const [query, setQuery] = useState('');
    const tenant = activeTenant ?? null;

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                inputRef.current?.focus();
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!tenant) return;
        window.location.href = String(workOrdersIndex({ tenant }));
    };

    return (
        <form role="search" onSubmit={submit} className="relative hidden sm:block">
            <label htmlFor="wo-search" className="sr-only">Cari Work Order, aset, atau PIC</label>
            <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-subtle" />
            <input
                id="wo-search"
                ref={inputRef}
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                disabled={!tenant}
                placeholder="Cari WO, aset, PIC..."
                className={cn(
                    'h-9 w-56 rounded-xl border border-border bg-surface-raised/70 pr-12 pl-9 text-xs text-ink',
                    'transition-colors duration-200 ease-standard placeholder:text-ink-subtle motion-reduce:transition-none',
                    'hover:bg-surface focus:bg-surface focus:ring-2 focus:ring-brand/20 focus:ring-offset-0 focus:outline-none',
                    'disabled:cursor-not-allowed disabled:opacity-60 lg:w-72',
                )}
            />
            <kbd className="absolute top-1/2 right-2 -translate-y-1/2 rounded border border-border bg-surface px-1.5 py-0.5 font-mono text-[0.625rem] leading-none text-ink-subtle select-none">
                Ctrl+K
            </kbd>
        </form>
    );
}

function UserButton() {
    const { auth } = usePage<InertiaConfig['sharedPageProps']>().props;
    const getInitials = useInitials();
    if (!auth?.user) return null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" aria-label={`Menu pengguna: ${auth.user.name}`} className="h-9 cursor-pointer gap-1.5 rounded-full pr-2 pl-1 hover:bg-accent">
                    <Avatar className="size-7 rounded-full ring-1 ring-border">
                        <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                        <AvatarFallback className="rounded-full bg-brand-soft text-[0.625rem] font-semibold text-brand">
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <ChevronDown aria-hidden="true" className="size-3.5 text-ink-subtle" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" sideOffset={8} className="w-64 rounded-xl">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
    const scrolled = useScrolled();

    return (
        <header className={cn(
            'sticky top-0 z-30 flex h-16 shrink-0 w-full items-center justify-between gap-3 border-b bg-surface/95 px-6 lg:px-8 shadow-sm backdrop-blur-md',
            'border-border transition-[box-shadow] duration-200 ease-standard motion-reduce:transition-none',
            scrolled && 'shadow-md',
        )}>
            <div className="flex items-center gap-3">
                <SidebarTrigger
                    aria-label="Buka atau tutup panel navigasi"
                    className="relative -ml-1 size-9 cursor-pointer rounded-xl border border-border bg-surface-raised/90 text-ink-muted shadow-xs hover:bg-accent hover:text-ink focus-visible:ring-2 focus-visible:ring-brand-strong/70 focus-visible:outline-none"
                />
                <span aria-hidden="true" className="mx-1 hidden h-5 w-px shrink-0 bg-border sm:block" />
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs font-medium">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </nav>
            </div>

            <div className="flex shrink-0 items-center gap-2">
                <WorkOrderSearch />
                <span aria-hidden="true" className="hidden h-5 w-px shrink-0 border-l border-border lg:block" />
                <ThemeToggle className="hidden sm:inline-flex" />
                <div className="flex items-center gap-1.5 border-l border-border pl-3">
                    <NotificationButton />
                    <HelpButton />
                </div>
                <UserButton />
            </div>
        </header>
    );
}
