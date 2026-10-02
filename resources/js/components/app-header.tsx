import { Link, usePage } from '@inertiajs/react';
import { Bell, ChevronDown, Menu, Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogo from '@/components/app-logo';
import { Breadcrumbs } from '@/components/breadcrumbs';
import {
    mainNavItems,
    navIcon,
    utilityNavItems,
    type OptiNavItem,
} from '@/components/nav-items';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { useAppearance, type Appearance } from '@/hooks/use-appearance';
import { cn, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

/* Depth vocabulary (DESIGN.md elevation: raised control + popover only). */
const shadowRaised = 'shadow-[0_1px_2px_rgba(21,32,43,0.06)]';
const shadowPopover = 'shadow-[0_4px_12px_rgba(21,32,43,0.10)]';

/**
 * Tracks whether the page has scrolled past `threshold` so the sticky header
 * can wake its soft shadow only when content passes beneath it.
 */
function useScrolled(threshold = 8): boolean {
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > threshold);

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, [threshold]);

    return scrolled;
}

/**
 * Desktop nav link: icon + label on a rail, with the Execution Teal
 * indicator parked on the header's bottom rule (echoes the deadline-rail
 * motif). The indicator crossfades; hover/press never shift layout.
 */
function NavLink({ item, className }: { item: OptiNavItem; className?: string }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const Icon = navIcon(item);
    const active = isCurrentOrParentUrl(item.href);

    return (
        <Link
            href={item.href}
            prefetch
            aria-current={active ? 'page' : undefined}
            className={cn(
                'group relative inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-sm font-medium whitespace-nowrap transition-colors duration-150',
                'hover:bg-accent hover:text-foreground active:bg-accent/70',
                'focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none',
                active ? 'text-foreground' : 'text-ink-muted',
                className,
            )}
        >
            <Icon
                aria-hidden="true"
                className={cn(
                    'size-4 shrink-0 transition-colors duration-150',
                    active ? 'text-brand' : 'opacity-70 group-hover:opacity-100',
                )}
            />
            {item.title}
            <span
                aria-hidden="true"
                className={cn(
                    'absolute inset-x-3 -bottom-[13px] h-0.5 rounded-full bg-brand transition-opacity duration-200 motion-reduce:transition-none',
                    active ? 'opacity-100' : 'opacity-0',
                )}
            />
        </Link>
    );
}

/** Sheet nav link: grouped, icon-led, multi-signal active state. */
function MobileNavLink({ item }: { item: OptiNavItem }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const Icon = navIcon(item);
    const active = isCurrentOrParentUrl(item.href);

    return (
        <SheetClose asChild>
            <Link
                href={item.href}
                prefetch
                aria-current={active ? 'page' : undefined}
                className={cn(
                    'flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium transition-colors duration-150',
                    'hover:bg-accent hover:text-foreground',
                    'focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none',
                    active
                        ? 'bg-accent text-foreground'
                        : 'text-ink-muted',
                )}
            >
                <Icon
                    aria-hidden="true"
                    className={cn(
                        'size-[18px] shrink-0',
                        active ? 'text-brand' : 'opacity-70',
                    )}
                />
                {item.title}
            </Link>
        </SheetClose>
    );
}

function NotificationButton() {
    return (
        <Button
            variant="ghost"
            size="icon"
            aria-label="Notifikasi"
            className="relative size-10 cursor-pointer rounded-md text-ink-muted hover:bg-accent hover:text-foreground after:absolute after:-inset-0.5 after:content-['']"
        >
            <Bell aria-hidden="true" className="size-[18px] opacity-80" />
            {/* Placeholder unread dot until the notification module ships a count */}
            <span
                aria-hidden="true"
                className="absolute top-2 right-2 size-2 rounded-full bg-warning ring-2 ring-card"
            />
        </Button>
    );
}

function AppearanceMenu() {
    const { appearance, updateAppearance } = useAppearance();
    const [open, setOpen] = useState(false);
    const isDark = appearance === 'dark';

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Ganti tema tampilan"
                    className="size-10 cursor-pointer rounded-md text-ink-muted hover:bg-accent hover:text-foreground after:absolute after:-inset-0.5 after:content-['']"
                >
                    {isDark ? (
                        <Sun aria-hidden="true" className="size-[18px] opacity-80" />
                    ) : (
                        <Moon aria-hidden="true" className="size-[18px] opacity-80" />
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className={cn('rounded-lg border-border/80', shadowPopover)}
            >
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={(value) =>
                        updateAppearance(value as Appearance)
                    }
                >
                    <DropdownMenuRadioItem value="light">
                        Terang
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem value="dark">
                        Gelap
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem value="system">
                        Sistem
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function UserMenu() {
    const page = usePage();
    const { auth } = page.props;
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    aria-label={`Menu pengguna: ${auth.user?.name ?? ''}`}
                    className={cn(
                        'group h-10 cursor-pointer gap-2.5 rounded-lg border border-border/70 bg-card py-1 pr-2 pl-1 hover:bg-accent/60 sm:pr-2.5',
                        shadowRaised,
                    )}
                >
                    <Avatar className="size-8 overflow-hidden rounded-full ring-1 ring-border-strong/50 transition-shadow duration-150 group-hover:ring-brand/40">
                        <AvatarImage
                            src={auth.user?.avatar}
                            alt={auth.user?.name}
                        />
                        <AvatarFallback className="rounded-full bg-secondary text-foreground">
                            {getInitials(auth.user?.name ?? '')}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden min-w-0 text-left leading-tight xl:grid">
                        <span className="truncate text-sm font-semibold">
                            {auth.user?.name}
                        </span>
                        <span className="truncate text-xs text-ink-muted">
                            {auth.user?.email}
                        </span>
                    </span>
                    <ChevronDown
                        aria-hidden="true"
                        className="hidden size-4 shrink-0 text-ink-muted transition-transform duration-200 group-data-[state=open]:rotate-180 xl:block"
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className={cn('w-60 rounded-lg border-border/80', shadowPopover)}
            >
                {auth.user && <UserMenuContent user={auth.user} />}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function HeaderBrand() {
    return (
        <Link
            href={dashboard()}
            prefetch
            aria-label="Beranda OptiWorks"
            className="-ml-1.5 flex shrink-0 items-center rounded-lg p-1.5 transition-colors duration-150 hover:bg-accent/70 focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
        >
            <AppLogo />
        </Link>
    );
}

export function AppHeader({ breadcrumbs = [] }: Props) {
    const scrolled = useScrolled();

    return (
        <header
            className={cn(
                'sticky top-0 z-30 border-b bg-card transition-[border-color,box-shadow] duration-300 ease-out motion-reduce:transition-none',
                scrolled
                    ? 'border-border shadow-[0_2px_12px_rgba(21,32,43,0.08),0_1px_3px_rgba(21,32,43,0.05)]'
                    : 'border-border/80',
            )}
        >
            <div className="mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-2 px-4 md:px-6">
                {/* Mobile menu */}
                <div className="lg:hidden">
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Buka menu navigasi"
                                className="relative size-10 cursor-pointer rounded-md after:absolute after:-inset-1 after:content-['']"
                            >
                                <Menu aria-hidden="true" className="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            className="w-80 gap-0 border-r border-border/80 bg-card p-0"
                        >
                            <SheetHeader className="border-b border-border/70 px-5 py-4">
                                <SheetTitle className="sr-only">
                                    Menu navigasi
                                </SheetTitle>
                                <AppLogo />
                            </SheetHeader>
                            <div className="flex-1 overflow-y-auto px-3 py-4">
                                <p className="px-2 pb-2 text-xs font-semibold tracking-wide text-ink-muted uppercase">
                                    Navigasi utama
                                </p>
                                <nav
                                    aria-label="Navigasi utama"
                                    className="flex flex-col gap-1"
                                >
                                    {mainNavItems.map((item) => (
                                        <MobileNavLink
                                            key={item.title}
                                            item={item}
                                        />
                                    ))}
                                </nav>
                                <div className="mt-5 border-t border-border/70 pt-4">
                                    <p className="px-2 pb-2 text-xs font-semibold tracking-wide text-ink-muted uppercase">
                                        Lainnya
                                    </p>
                                    <div className="flex flex-col gap-1">
                                        {utilityNavItems.map((item) => (
                                            <SheetClose key={item.title} asChild>
                                                <Link
                                                    href={toUrl(item.href)}
                                                    className="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium text-ink-muted transition-colors duration-150 hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                                                >
                                                    {item.title}
                                                </Link>
                                            </SheetClose>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <HeaderBrand />

                {/* Desktop navigation rail — flow-centered so it can never collide with the right cluster */}
                <nav
                    aria-label="Navigasi utama"
                    className="hidden items-center gap-0.5 lg:flex"
                >
                    {mainNavItems.map((item) => (
                        <NavLink key={item.title} item={item} />
                    ))}
                </nav>

                {/* Right cluster: segmented controls pill + user identity block */}
                <div className="flex shrink-0 items-center gap-2 sm:gap-2.5">
                    <div
                        className={cn(
                            'flex items-center gap-0.5 rounded-lg border border-border/70 bg-card p-0.5',
                            shadowRaised,
                        )}
                    >
                        <AppearanceMenu />
                        <span
                            aria-hidden="true"
                            className="h-5 w-px bg-border"
                        />
                        <NotificationButton />
                    </div>
                    <UserMenu />
                </div>
            </div>

            {/* Breadcrumb row on the same surface, separated by a hairline */}
            {breadcrumbs.length > 1 && (
                <div className="mx-auto flex h-10 w-full max-w-7xl items-center border-t border-border/60 px-4 text-sm text-ink-muted md:px-6">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            )}
        </header>
    );
}
