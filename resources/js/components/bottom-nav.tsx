import { Link, usePage } from '@inertiajs/react';
import { Ellipsis, LogOut } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import {
    mobileNavItems,
    navGroups,
    navIcon,
    navToneStyles,
    type OptiNavItem,
} from '@/components/nav-items';
import { ThemeToggle } from '@/components/theme-toggle';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { submitLogout } from '@/lib/logout';
import type { InertiaConfig } from '@inertiajs/core';

/*
 * Phone navigation (<768px), field-first: a floating pill with the four
 * destinations a worker actually taps, and one sheet holding the rest of
 * the navigation. Nothing without a route is rendered as a fake link.
 */

function SheetNavRow({ item }: { item: OptiNavItem }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const Icon = navIcon(item);
    const tone = navToneStyles[item.tone ?? 'ops'];
    const active = !item.disabled && isCurrentOrParentUrl(item.href);

    const classes = cn(
        'flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors duration-150',
        active
            ? 'bg-brand-soft text-foreground'
            : 'text-ink-muted hover:bg-accent hover:text-foreground',
    );

    const icon = (
        <Icon
            aria-hidden="true"
            className={cn(
                'size-[18px] shrink-0',
                item.disabled ? 'text-ink-subtle' : tone.icon,
            )}
        />
    );

    if (item.disabled) {
        return (
            <span
                aria-disabled="true"
                title={item.hint}
                className={cn(classes, 'cursor-not-allowed opacity-60')}
            >
                {icon}
                {item.title}
                <span className="ms-auto rounded-full bg-surface-sunken px-1.5 py-0.5 text-[0.625rem] font-semibold tracking-wide text-ink-subtle uppercase">
                    Segera
                </span>
            </span>
        );
    }

    return (
        <SheetClose asChild>
            <Link
                href={item.href}
                prefetch
                aria-current={active ? 'page' : undefined}
                className={classes}
            >
                {icon}
                <span className="truncate">{item.title}</span>
            </Link>
        </SheetClose>
    );
}

export function BottomNav() {
    const { activeTenant, permissions, auth, name } =
        usePage<InertiaConfig['sharedPageProps']>().props;
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const items = mobileNavItems({ activeTenant, permissions });
    const groups = navGroups({ activeTenant, permissions });

    return (
        <nav
            aria-label="Navigasi utama"
            className="fixed inset-x-0 bottom-0 z-30 px-3 pb-[max(12px,env(safe-area-inset-bottom))] md:hidden"
        >
            <div className="mx-auto flex h-16 max-w-md items-stretch gap-1 rounded-2xl border border-border bg-surface/95 p-1.5 shadow-[var(--shadow-popover)] backdrop-blur-md">
                {items.map((item) => {
                    const Icon = navIcon(item);
                    const tone = navToneStyles[item.tone ?? 'ops'];
                    const active = isCurrentOrParentUrl(item.href);

                    return (
                        <Link
                            key={item.title}
                            href={item.href}
                            prefetch
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'flex min-h-12 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl text-[0.6875rem] font-semibold transition-all duration-200 ease-standard active:scale-95 motion-reduce:transition-none motion-reduce:active:scale-100',
                                'focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none',
                                active
                                    ? 'bg-brand text-on-brand shadow-[var(--shadow-card)]'
                                    : 'text-ink-muted hover:bg-accent hover:text-foreground',
                            )}
                        >
                            <Icon
                                aria-hidden="true"
                                className={cn(
                                    'size-5 shrink-0',
                                    active ? 'text-on-brand' : tone.icon,
                                )}
                            />
                            {item.shortTitle ?? item.title}
                        </Link>
                    );
                })}

                <Sheet>
                    <SheetTrigger asChild>
                        <button
                            type="button"
                            aria-label="Lainnya"
                            className="flex min-h-12 flex-1 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-xl text-[0.6875rem] font-semibold text-ink-muted transition-all duration-200 ease-standard hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none active:scale-95 motion-reduce:transition-none motion-reduce:active:scale-100"
                        >
                            <Ellipsis
                                aria-hidden="true"
                                className="size-5 shrink-0"
                            />
                            Lainnya
                        </button>
                    </SheetTrigger>
                    <SheetContent
                        side="bottom"
                        className="max-h-[85svh] gap-0 overflow-hidden rounded-t-2xl border-t border-border bg-surface px-0 pb-[calc(env(safe-area-inset-bottom)+12px)]"
                    >
                        <SheetHeader className="border-b border-border px-5 py-4">
                            <SheetTitle className="text-left">
                                <AppLogo className="text-base" />
                                <span className="sr-only">Navigasi {name}</span>
                            </SheetTitle>
                            {activeTenant && (
                                <p className="text-left text-xs text-ink-muted">
                                    Cabang aktif:{' '}
                                    <span className="font-semibold text-ink">
                                        {activeTenant}
                                    </span>
                                </p>
                            )}
                        </SheetHeader>

                        <div className="flex-1 overflow-y-auto px-3 py-4">
                            {groups.map((group) => (
                                <div key={group.id} className="mb-4 last:mb-0">
                                    <p className="px-3 pb-2 text-[0.6875rem] font-semibold tracking-[0.08em] text-ink-subtle uppercase">
                                        {group.label}
                                    </p>
                                    <div className="flex flex-col gap-1">
                                        {group.items.map((item) => (
                                            <SheetNavRow
                                                key={item.title}
                                                item={item}
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))}

                            <div className="mt-4 flex items-center justify-between gap-3 border-t border-border px-3 pt-4">
                                <span className="text-sm font-medium text-ink-muted">
                                    Tampilan
                                </span>
                                <ThemeToggle showLabels />
                            </div>
                        </div>

                        <div className="border-t border-border px-3 pt-3">
                            <SheetClose asChild>
                                <button
                                    type="button"
                                    onClick={submitLogout}
                                    className="flex min-h-11 w-full cursor-pointer items-center gap-3 rounded-lg px-3 text-sm font-medium text-danger transition-colors duration-150 hover:bg-danger-subtle focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                                >
                                    <LogOut
                                        aria-hidden="true"
                                        className="size-[18px] shrink-0"
                                    />
                                    Keluar
                                </button>
                            </SheetClose>
                            {auth?.user && (
                                <p className="px-3 pt-2 pb-1 text-xs text-ink-subtle">
                                    Masuk sebagai {auth.user.email}
                                </p>
                            )}
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </nav>
    );
}
