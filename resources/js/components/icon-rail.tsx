import { Link, router, usePage } from '@inertiajs/react';
import { LifeBuoy, LogOut } from 'lucide-react';
import { mainNavItems, navIcon, type OptiNavItem } from '@/components/nav-items';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { logout } from '@/routes';
import { cn } from '@/lib/utils';

/*
 * Desktop icon rail (DESIGN.md §Shell & Navigation, >=1024px):
 * 64px floating rail, icon-only 44px targets, tooltips on hover/focus.
 * Active item: raised white chip with Strong Rule border.
 * Bottom group: Bantuan (disebabkan belum ada surface, disabled), Keluar.
 */

const activeChip = 'border border-border-strong bg-card shadow-raised';

function RailLink({ item }: { item: OptiNavItem }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const Icon = navIcon(item);
    const active = isCurrentOrParentUrl(item.href);

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link
                    href={item.href}
                    prefetch
                    aria-current={active ? 'page' : undefined}
                    aria-label={item.title}
                    className={cn(
                        'flex size-11 items-center justify-center rounded-md transition-colors duration-200 ease-standard motion-reduce:transition-none',
                        'focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none',
                        active
                            ? activeChip
                            : 'border border-transparent text-ink-muted hover:bg-card/70 hover:text-foreground',
                    )}
                >
                    <Icon
                        aria-hidden="true"
                        className={cn(
                            'size-[18px] shrink-0',
                            active ? 'text-brand' : 'opacity-80',
                        )}
                    />
                </Link>
            </TooltipTrigger>
            <TooltipContent side="right">{item.title}</TooltipContent>
        </Tooltip>
    );
}

function RailLogout() {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link
                    href={logout()}
                    method="post"
                    as="button"
                    aria-label="Keluar"
                    onClick={() => router.flushAll()}
                    className="flex size-11 items-center justify-center rounded-md text-ink-muted transition-colors duration-200 ease-standard motion-reduce:transition-none hover:bg-card/70 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                >
                    <LogOut aria-hidden="true" className="size-[18px]" />
                </Link>
            </TooltipTrigger>
            <TooltipContent side="right">Keluar</TooltipContent>
        </Tooltip>
    );
}

export function IconRail() {
    const { activeTenant } = usePage().props;

    return (
        <aside
            aria-label="Navigasi utama"
            className="sticky top-0 hidden h-screen w-16 shrink-0 lg:block"
        >
            <nav className="flex h-full flex-col items-center gap-1 py-4">
                {mainNavItems(activeTenant).map((item) => (
                    <RailLink key={item.title} item={item} />
                ))}

                <div className="mt-auto flex flex-col items-center gap-1 border-t border-border/60 pt-4">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span
                                aria-disabled="true"
                                aria-label="Bantuan"
                                className="flex size-11 items-center justify-center rounded-md text-ink-subtle"
                            >
                                <LifeBuoy aria-hidden="true" className="size-[18px]" />
                            </span>
                        </TooltipTrigger>
                        <TooltipContent side="right">
                            Bantuan — segera hadir
                        </TooltipContent>
                    </Tooltip>
                    <RailLogout />
                </div>
            </nav>
        </aside>
    );
}
