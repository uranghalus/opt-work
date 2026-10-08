import { Link, usePage } from '@inertiajs/react';
import { Bell, ChevronDown, Menu, Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogo from '@/components/app-logo';
import { BranchSwitcher } from '@/components/branch-switcher';
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
import { index as notificationsIndex } from '@/routes/notifications';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { useAppearance, type Appearance } from '@/hooks/use-appearance';
import { cn, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

/*
 * Glassy Modern shell — floating translucent chrome over the concrete
 * canvas: backdrop-blur surfaces, luminous top-edge highlights, and the
 * evolved teal gradient reserved for where-the-user-is signals (logo orb,
 * active tab chip, bell badge). Blur and ambient shadow deepen on scroll.
 * Tablet (768-1023px) collapses nav into a hamburger sheet; mobile
 * (<768px) keeps logo + bell + avatar (bottom nav owns navigation).
 */

/**
 * Tracks whether the page has scrolled past `threshold` so the sticky
 * header deepens its translucency and ambient shadow on scroll.
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
 * Pill-tab section navigation inside a frosted glass capsule. The active
 * tab is a raised white chip with a teal icon and a soft brand glow.
 */
function PillTabs({ items }: { items: OptiNavItem[] }) {
    return (
        <nav
            aria-label="Navigasi utama"
            className={cn(
                'hidden items-center gap-1 rounded-full border border-white/70 bg-card/60 p-1 backdrop-blur-md lg:flex',
                'shadow-[0_2px_8px_rgba(21,32,43,0.06),inset_0_1px_0_rgba(255,255,255,0.7)]',
            )}
        >
            {items.map((item) => (
                <PillTab key={item.title} item={item} />
            ))}
        </nav>
    );
}

function PillTab({ item }: { item: OptiNavItem }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const Icon = navIcon(item);
    const active = isCurrentOrParentUrl(item.href);

    return (
        <Link
            href={item.href}
            prefetch
            aria-current={active ? 'page' : undefined}
            className={cn(
                'group relative inline-flex h-9 items-center gap-1.5 rounded-full px-3.5 text-sm whitespace-nowrap transition-all duration-200 ease-standard active:scale-[0.97] motion-reduce:transition-none motion-reduce:active:scale-100',
                'focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:ring-offset-1 focus-visible:outline-none',
                active
                    ? 'bg-white font-semibold text-foreground shadow-[0_2px_10px_-2px_rgba(12,107,88,0.35),0_1px_3px_rgba(21,32,43,0.08),inset_0_1px_0_rgba(255,255,255,0.9)] ring-1 ring-brand/20'
                    : 'font-medium text-ink-muted hover:bg-white/60 hover:text-foreground',
            )}
        >
            <Icon
                aria-hidden="true"
                className={cn(
                    'size-4 shrink-0 transition-transform duration-200 group-hover:scale-110 motion-reduce:transition-none motion-reduce:group-hover:scale-100',
                    active
                        ? 'text-brand'
                        : 'opacity-70 group-hover:opacity-100',
                )}
            />
            {item.title}
        </Link>
    );
}

/** Tablet hamburger: opens the full navigation sheet. */
function MobileMenu() {
    const { activeTenant } = usePage().props;

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Buka menu navigasi"
                    className="relative size-10 cursor-pointer rounded-full border border-white/70 bg-card/60 shadow-[0_2px_8px_rgba(21,32,43,0.06),inset_0_1px_0_rgba(255,255,255,0.7)] backdrop-blur-md after:absolute after:-inset-1 after:content-['']"
                >
                    <Menu aria-hidden="true" className="size-5" />
                </Button>
            </SheetTrigger>
            <SheetContent
                side="left"
                className="w-80 gap-0 border-r border-white/60 bg-card/90 p-0 backdrop-blur-xl"
            >
                <SheetHeader className="border-b border-border/60 px-5 py-4">
                    <SheetTitle className="sr-only">Menu navigasi</SheetTitle>
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
                        {mainNavItems(activeTenant).map((item) => (
                            <SheetNavLink key={item.title} item={item} />
                        ))}
                    </nav>
                    <div className="mt-5 border-t border-border/60 pt-4">
                        <p className="px-2 pb-2 text-xs font-semibold tracking-wide text-ink-muted uppercase">
                            Lainnya
                        </p>
                        <div className="flex flex-col gap-1">
                            {utilityNavItems.map((item) => (
                                <SheetClose key={item.title} asChild>
                                    <Link
                                        href={toUrl(item.href)}
                                        className="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-ink-muted transition-colors duration-150 hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
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
    );
}

/** Sheet nav link: active item glows with the brand gradient chip. */
function SheetNavLink({ item }: { item: OptiNavItem }) {
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
                    'flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-all duration-200',
                    'hover:bg-accent hover:text-foreground',
                    'focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none',
                    active
                        ? 'bg-gradient-to-br from-brand-strong to-brand font-semibold text-on-brand shadow-glow-brand'
                        : 'text-ink-muted',
                )}
            >
                <Icon
                    aria-hidden="true"
                    className={cn(
                        'size-[18px] shrink-0',
                        active ? 'text-on-brand' : 'opacity-70',
                    )}
                />
                {item.title}
            </Link>
        </SheetClose>
    );
}

function NotificationButton() {
    const { unreadNotificationsCount } = usePage().props;
    const unread = Number(unreadNotificationsCount ?? 0);
    const label = unread > 99 ? '99+' : unread;

    return (
        <Button
            variant="ghost"
            size="icon"
            asChild
            className="relative size-9 cursor-pointer rounded-full text-ink-muted hover:bg-white/70 hover:text-foreground after:absolute after:-inset-0.5 after:content-['']"
        >
            <Link
                href={notificationsIndex()}
                aria-label={`Notifikasi${unread > 0 ? ` (${unread} belum dibaca)` : ''}`}
            >
                <Bell aria-hidden="true" className="size-[18px] opacity-80" />
                {unread > 0 && (
                    <span
                        aria-hidden="true"
                        className="absolute -top-0.5 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-gradient-to-br from-brand-strong to-brand px-1 text-[0.625rem] leading-none font-bold text-on-brand ring-2 ring-white shadow-glow-brand"
                    >
                        {label}
                    </span>
                )}
            </Link>
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
                    className="size-9 cursor-pointer rounded-full text-ink-muted hover:bg-white/70 hover:text-foreground after:absolute after:-inset-0.5 after:content-['']"
                >
                    {isDark ? (
                        <Sun
                            aria-hidden="true"
                            className="size-[18px] opacity-80"
                        />
                    ) : (
                        <Moon
                            aria-hidden="true"
                            className="size-[18px] opacity-80"
                        />
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="rounded-xl border-white/60 bg-card/90 shadow-glass backdrop-blur-xl"
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
                    className="group h-11 cursor-pointer gap-2.5 rounded-full border border-white/70 bg-card/60 p-1 pr-2 shadow-[0_2px_8px_rgba(21,32,43,0.06),inset_0_1px_0_rgba(255,255,255,0.7)] backdrop-blur-md transition-all duration-200 hover:bg-white/80 hover:shadow-glass sm:pr-2.5"
                >
                    <Avatar className="size-8 overflow-hidden rounded-full ring-1 ring-brand/25 transition-shadow duration-150 group-hover:shadow-glow-brand group-hover:ring-brand/50">
                        <AvatarImage
                            src={auth.user?.avatar}
                            alt={auth.user?.name}
                        />
                        <AvatarFallback className="rounded-full bg-brand-soft text-brand">
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
                className="w-60 rounded-xl border-white/60 bg-card/90 shadow-glass backdrop-blur-xl"
            >
                {auth.user && <UserMenuContent user={auth.user} />}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** Logo orb plus the active cabang chip — cabang context stays operational truth. */
function HeaderBrand() {
    const { activeTenant } = usePage().props;

    return (
        <div className="flex min-w-0 items-center gap-2.5">
            <Link
                href={dashboard()}
                prefetch
                aria-label="Beranda OptiWorks"
                className="-ml-1.5 flex shrink-0 items-center rounded-full p-1.5 transition-colors duration-150 hover:bg-white/60 focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
            >
                <AppLogo />
            </Link>
            {activeTenant && (
                <span className="hidden md:inline-flex">
                    <BranchSwitcher variant="header" />
                </span>
            )}
        </div>
    );
}

export function AppHeader({ breadcrumbs = [] }: Props) {
    const scrolled = useScrolled();
    const { activeTenant } = usePage().props;

    return (
        <header
            className={cn(
                'sticky top-0 z-30 backdrop-blur-xl backdrop-saturate-150 transition-[background-color,border-color,box-shadow] duration-300 ease-standard motion-reduce:transition-none',
                scrolled
                    ? 'border-b border-white/50 bg-card/80 shadow-glass'
                    : 'border-b border-transparent bg-card/50',
            )}
        >
            {/* Luminous top-edge highlight */}
            <span
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/90 to-transparent"
            />
            <div className="mx-auto flex h-16 w-full max-w-[1280px] items-center justify-between gap-2 px-4 md:px-6">
                {/* Tablet hamburger (768-1023px) — mobile owns navigation via bottom nav */}
                <div className="hidden md:block lg:hidden">
                    <MobileMenu />
                </div>

                <HeaderBrand />

                {/* Pill-tab section navigation (desktop) */}
                <PillTabs items={mainNavItems(activeTenant)} />

                {/* Right cluster: appearance + notification bell + user identity */}
                <div className="flex shrink-0 items-center gap-2 sm:gap-2.5">
                    <div className="flex items-center gap-0.5 rounded-full border border-white/70 bg-card/60 p-1 shadow-[0_2px_8px_rgba(21,32,43,0.06),inset_0_1px_0_rgba(255,255,255,0.7)] backdrop-blur-md">
                        {/* Mobile keeps bell + avatar only (theme lives in Pengaturan) */}
                        <span className="max-md:hidden">
                            <AppearanceMenu />
                        </span>
                        <span
                            aria-hidden="true"
                            className="h-5 w-px bg-border/70 max-md:hidden"
                        />
                        <NotificationButton />
                    </div>
                    <UserMenu />
                </div>
            </div>

            {/* Breadcrumb row on the same glass surface, separated by a hairline */}
            {breadcrumbs.length > 1 && (
                <div className="mx-auto flex h-10 w-full max-w-[1280px] items-center border-t border-white/50 px-4 text-[0.8125rem] text-ink-muted md:px-6">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            )}
        </header>
    );
}
