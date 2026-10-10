import { Link, usePage } from '@inertiajs/react';
import { LifeBuoy, LogOut } from 'lucide-react';
import {
    mainNavItems,
    navIcon,
    type OptiNavItem,
} from '@/components/nav-items';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { submitLogout } from '@/lib/logout';
import { cn } from '@/lib/utils';

/*
 * Desktop icon rail (>=1024px), Glassy Modern shell: a floating frosted
 * capsule rail over the concrete canvas with a luminous top-edge
 * highlight. The active item is a gradient-teal chip with a soft brand
 * glow. Bottom group: Bantuan (disabled, coming soon), Keluar.
 */

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
                        'flex size-11 items-center justify-center rounded-full transition-all duration-200 ease-standard active:scale-95 motion-reduce:transition-none motion-reduce:active:scale-100',
                        'focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none',
                        active
                            ? 'bg-gradient-to-br from-brand-strong to-brand shadow-glow-brand ring-1 ring-white/30'
                            : 'text-ink-muted hover:bg-white/70 hover:text-foreground',
                    )}
                >
                    <Icon
                        aria-hidden="true"
                        className={cn(
                            'size-[18px] shrink-0',
                            active ? 'text-on-brand' : 'opacity-80',
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
                <button
                    type="button"
                    onClick={submitLogout}
                    aria-label="Keluar"
                    className="flex size-11 items-center justify-center rounded-full text-ink-muted transition-all duration-200 ease-standard hover:bg-white/70 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none motion-reduce:transition-none"
                >
                    <LogOut aria-hidden="true" className="size-[18px]" />
                </button>
            </TooltipTrigger>
            <TooltipContent side="right">Keluar</TooltipContent>
        </Tooltip>
    );
}

export function IconRail() {
    const { activeTenant, auth } = usePage().props;

    return (
        <aside
            aria-label="Navigasi utama"
            className="sticky top-0 hidden h-screen w-20 shrink-0 lg:block"
        >
            <nav
                className={cn(
                    'mx-3 my-4 flex h-[calc(100%-32px)] flex-col items-center gap-1.5 rounded-[28px] py-4',
                    'border border-white/70 bg-card/60 backdrop-blur-xl',
                    'shadow-[0_8px_32px_-8px_rgba(21,32,43,0.14),0_2px_6px_rgba(21,32,43,0.05),inset_0_1px_0_rgba(255,255,255,0.7)]',
                )}
            >
                {mainNavItems({
                    activeTenant,
                    isSuperAdmin: auth?.isSuperAdmin,
                }).map((item) => (
                    <RailLink key={item.title} item={item} />
                ))}

                <div className="mt-auto flex flex-col items-center gap-1.5 border-t border-border/50 pt-4">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span
                                aria-disabled="true"
                                aria-label="Bantuan"
                                className="flex size-11 items-center justify-center rounded-full text-ink-subtle"
                            >
                                <LifeBuoy
                                    aria-hidden="true"
                                    className="size-[18px]"
                                />
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
