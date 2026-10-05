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
        permissions: page.props.permissions,
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
        <header className="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
            <div className="flex min-w-0 items-start gap-3">
                {current && Icon && (
                    <span
                        aria-hidden="true"
                        className={cn(
                            'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg ring-1',
                            tone.chip,
                            tone.chipRing,
                            tone.icon,
                        )}
                    >
                        <Icon className="size-4" />
                    </span>
                )}
                <div className="min-w-0">
                    {heading && (
                        <h1 className="text-[1.375rem] leading-tight font-semibold tracking-[-0.02em] text-balance sm:text-2xl">
                            {heading}
                        </h1>
                    )}
                    {description && (
                        <p className="mt-1.5 max-w-[65ch] text-[0.8125rem] leading-relaxed text-ink-muted">
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
        </header>
    );
}
