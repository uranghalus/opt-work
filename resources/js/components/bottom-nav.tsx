import { Link, usePage } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { CalendarClock, ClipboardList, Database, Ellipsis, House, LogOut, Settings, type LucideIcon } from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { dashboard, logout } from '@/routes';

/*
 * Mobile bottom navigation (DESIGN.md §Layout, <768px):
 * Beranda, WO, Harian, Data, Lainnya. "Lainnya" opens a bottom sheet
 * holding utility navigation and logout. 56px+ rows, safe-area padding.
 */

type BottomItem = {
    title: string;
    short: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
};

export function BottomNav() {
    const { activeTenant } = usePage().props;
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const tenant = activeTenant ?? '';

    const items: BottomItem[] = [
        { title: 'Beranda', short: 'Beranda', href: dashboard(), icon: House },
        {
            title: 'Work Order',
            short: 'WO',
            href: tenant ? `/${tenant}/work-orders` : dashboard(),
            icon: ClipboardList,
        },
        {
            title: 'Daily Work',
            short: 'Harian',
            href: dashboard(), // D01 Daily Work route lands with the module
            icon: CalendarClock,
        },
        {
            title: 'Work Data',
            short: 'Data',
            href: dashboard(), // WD01 history route lands with the module
            icon: Database,
        },
    ];

    return (
        <nav
            aria-label="Navigasi bawah"
            className="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-card pb-[env(safe-area-inset-bottom)] md:hidden"
        >
            <div className="mx-auto flex h-16 max-w-lg items-stretch">
                {items.map((item) => {
                    const active = isCurrentOrParentUrl(item.href);
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.title}
                            href={item.href}
                            prefetch
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'flex min-h-14 flex-1 flex-col items-center justify-center gap-1 rounded-md text-[0.75rem] font-semibold transition-colors duration-200 ease-standard motion-reduce:transition-none',
                                'focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none',
                                active ? 'text-brand' : 'text-ink-muted',
                            )}
                        >
                            <Icon
                                aria-hidden="true"
                                className={cn(
                                    'size-5 shrink-0',
                                    active ? 'text-brand' : 'opacity-75',
                                )}
                            />
                            {item.short}
                        </Link>
                    );
                })}

                <Sheet>
                    <SheetTrigger asChild>
                        <button
                            type="button"
                            aria-label="Lainnya"
                            className="flex min-h-14 flex-1 flex-col items-center justify-center gap-1 rounded-md text-[0.75rem] font-semibold text-ink-muted transition-colors duration-200 ease-standard hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none motion-reduce:transition-none"
                        >
                            <Ellipsis aria-hidden="true" className="size-5 shrink-0 opacity-75" />
                            Lainnya
                        </button>
                    </SheetTrigger>
                    <SheetContent
                        side="bottom"
                        className="rounded-t-2xl border-t border-border bg-card px-4 pt-4 pb-[calc(env(safe-area-inset-bottom)+16px)]"
                    >
                        <SheetHeader className="p-0 pb-3">
                            <SheetTitle className="text-left text-base font-semibold">
                                Lainnya
                            </SheetTitle>
                        </SheetHeader>
                        <div className="flex flex-col gap-1">
                            <SheetClose asChild>
                                <Link
                                    href={toUrl('/settings/profile')}
                                    className="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium text-ink-muted transition-colors duration-150 hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                                >
                                    <Settings aria-hidden="true" className="size-[18px] shrink-0" />
                                    Pengaturan
                                </Link>
                            </SheetClose>
                            <SheetClose asChild>
                                <Link
                                    href={logout()}
                                    method="post"
                                    as="button"
                                    className="flex min-h-11 w-full items-center gap-3 rounded-md px-3 text-sm font-medium text-ink-muted transition-colors duration-150 hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                                >
                                    <LogOut aria-hidden="true" className="size-[18px] shrink-0" />
                                    Keluar
                                </Link>
                            </SheetClose>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </nav>
    );
}
