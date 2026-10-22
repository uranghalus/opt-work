import { MessageCircle, Palette, Shield, User } from 'lucide-react';
import { Link, usePage } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as indexWhatsapp } from '@/routes/whatsapp';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profil',
        href: edit(),
    },
    {
        title: 'Keamanan',
        href: editSecurity(),
    },
    {
        title: 'Tampilan',
        href: editAppearance(),
    },
];

const navIconMap: Record<
    string,
    React.ComponentType<{ className?: string }>
> = {
    Profil: User,
    Keamanan: Shield,
    Tampilan: Palette,
    WhatsApp: MessageCircle,
};

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    // Gateway WhatsApp (ADR-0009): hanya untuk yang pegang whatsapp.read —
    // flag Gate, otomatis lolos untuk super admin (bypass ADR-0004).
    const { auth } = usePage<InertiaConfig['sharedPageProps']>().props;
    const canSeeWhatsApp = Boolean(auth?.can?.['whatsapp.read']);

    const navItems: NavItem[] = [
        ...sidebarNavItems,
        ...(canSeeWhatsApp
            ? [{ title: 'WhatsApp', href: indexWhatsapp() }]
            : []),
    ];

    return (
        <div className="space-y-6">
            <Heading
                title="Pengaturan"
                description="Kelola profil dan pengaturan akun Anda"
            />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[260px_minmax(0,1fr)] lg:gap-8">
                {/* Sidebar navigation card */}
                <aside className="self-start lg:sticky lg:top-24">
                    <div className="rounded-3xl border border-border bg-surface/70 shadow-glass backdrop-blur-xl">
                        <div className="border-b border-border px-4 py-3">
                            <p className="text-xs font-semibold tracking-wide text-ink-muted uppercase">
                                Menu Pengaturan
                            </p>
                        </div>
                        <nav aria-label="Pengaturan" className="p-2">
                            {navItems.map((item) => {
                                const hrefUrl = toUrl(item.href);
                                const isActive = isCurrentOrParentUrl(hrefUrl);
                                const Icon = navIconMap[item.title];
                                return (
                                    <Link
                                        key={hrefUrl}
                                        href={item.href}
                                        aria-current={
                                            isActive ? 'page' : undefined
                                        }
                                        className={cn(
                                            'group flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-brand/50 focus-visible:ring-offset-2 focus-visible:ring-offset-surface focus-visible:outline-none',
                                            isActive
                                                ? 'bg-brand/10 font-semibold text-brand'
                                                : 'text-ink-muted hover:bg-surface-raised hover:text-ink',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'inline-flex rounded-xl p-2 ring-1 transition',
                                                isActive
                                                    ? 'bg-brand/15 text-brand ring-brand/20'
                                                    : 'bg-surface-raised text-ink-muted ring-border group-hover:ring-border-strong',
                                            )}
                                        >
                                            {Icon ? (
                                                <Icon
                                                    className="h-[18px] w-[18px]"
                                                    aria-hidden="true"
                                                />
                                            ) : null}
                                        </span>
                                        <span className="truncate">
                                            {item.title}
                                        </span>
                                        {isActive && (
                                            <span
                                                className="ml-auto h-1.5 w-1.5 rounded-full bg-brand"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>
                </aside>

                {/* Content column — pages own their card surfaces. */}
                <section className="min-w-0">
                    <div className="space-y-6 sm:p-1">{children}</div>
                </section>
            </div>
        </div>
    );
}
