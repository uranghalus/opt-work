import { usePage } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { navGroups, navIcon, navToneStyles } from '@/components/nav-items';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { greetingName, timeGreeting } from '@/lib/greeting';
import { cn } from '@/lib/utils';
import type { AppActions } from '@/types';
import type { InertiaConfig } from '@inertiajs/core';

/**
 * Section heading band rendered by the shell (one heading owner per page):
 * module chip in its group tint, the title or the time-aware greeting, one
 * supporting line, and the page's primary actions on the right.
 */
export function PageHeader({
    title,
    description,
    greeting = false,
    actions,
}: {
    title?: string;
    description?: string;
    greeting?: boolean;
    actions?: AppActions;
}) {
    const page = usePage<InertiaConfig['sharedPageProps']>();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const tenant = page.props.activeTenant ?? null;

    const current = navGroups({
        activeTenant: tenant,
        isSuperAdmin: page.props.auth?.isSuperAdmin,
        canReadRoles: page.props.auth?.can?.['role.read'],
        canReadPermissions: page.props.auth?.can?.['permission.read'],
        canReadWorkData: page.props.auth?.can?.['work-data.read'],
        hasEmployeeLink: page.props.auth?.user?.employee_id != null,
        canDecideExtends:
            (page.props.auth?.can?.['work-order.extend-approve'] ?? false) ||
            (page.props.auth?.isSuperAdmin ?? false) ||
            (page.props.auth?.user?.roles?.includes('general_manager') ??
                false) ||
            (page.props.auth?.user?.roles?.includes('deputy_general_manager') ??
                false),
    })
        .flatMap((group) => group.items)
        .find((item) => !item.disabled && isCurrentOrParentUrl(item.href));

    const heading = greeting
        ? [timeGreeting(), greetingName(page.props.auth?.user?.name)]
              .filter(Boolean)
              .join(', ')
        : title;

    if (!heading && !description && !actions) {
        return null;
    }

    const Icon = current ? navIcon(current) : null;
    const tone = navToneStyles[current?.tone ?? 'ops'];
    const ActionsComponent =
        typeof actions === 'function' ? (actions as ComponentType) : null;
    const actionNode = ActionsComponent ? (
        <ActionsComponent />
    ) : typeof actions === 'function' ? null : (
        actions
    );

    return (
        <header className="relative overflow-hidden rounded-[24px] border border-white/10 bg-white/[0.04] p-6 backdrop-blur-xl supports-[backdrop-filter]:bg-white/[0.04]">
            <div className="absolute inset-0 -z-10 bg-gradient-to-br from-brand/10 via-transparent to-transparent" aria-hidden="true" />
            <div className="flex flex-wrap items-start justify-between gap-6"> 
                <div className="flex min-w-0 items-start gap-4">
                    {current && Icon && (
                        <span
                            aria-hidden="true"
                            className={cn(
                                'mt-0.5 flex size-12 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset',
                                tone.chip,
                                tone.chipRing,
                                tone.icon,
                            )}
                        >
                            <Icon className="size-5" />
                        </span>
                    )}
                    <div className="min-w-0">
                        {heading && (
                            <h1 className="text-[1.875rem] leading-tight font-semibold tracking-[-0.02em] text-balance text-ink">
                                {heading}
                            </h1>
                        )}
                        {description && (
                            <p className="mt-2 max-w-[65ch] text-[15px] leading-relaxed text-ink-muted">
                                {description}
                            </p>
                        )}
                    </div>
                </div>

                {actionNode && (
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        {actionNode}
                    </div>
                )}
            </div>

            {typeof window !== 'undefined' && window.location.pathname.includes('/dashboard') && (
                <div className="mt-4 flex items-center gap-3 rounded-xl border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-ink">
                    <span className="flex size-6 shrink-0 items-center justify-center rounded-lg bg-danger/20 text-danger">!</span>
                    <p className="min-w-0 flex-1">6 WO telat dan 2 eskalasi aktif — tinjau sekarang agar SLA tidak memburuk.</p>
                    <a href="#" className="shrink-0 font-medium text-brand hover:underline">Lihat detail</a>
                </div>
            )}
        </header>
    );
}
