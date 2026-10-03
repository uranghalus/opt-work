import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { cn } from '@/lib/utils';

/*
 * Info banner (DESIGN.md §Info Banner): one rounded pill, surface-raised
 * background, accent-colored leading icon, single-line message, trailing
 * text link. One per page maximum; operational notices only.
 */
export function InfoBanner({
    message,
    actionLabel = 'Lihat detail',
    href,
    iconClassName = 'text-brand',
    onDismiss,
    className,
}: {
    message: string;
    actionLabel?: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    iconClassName?: string;
    onDismiss?: () => void;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-center gap-2.5 rounded-full border border-border bg-surface-raised py-2 pr-3 pl-3.5',
                className,
            )}
        >
            <Info aria-hidden="true" className={cn('size-4 shrink-0', iconClassName)} />
            <p className="min-w-0 flex-1 truncate text-sm text-ink">
                {message}
            </p>
            {href && (
                <Link
                    href={href}
                    className="shrink-0 text-sm font-medium whitespace-nowrap text-brand hover:underline"
                >
                    {actionLabel}
                </Link>
            )}
            {onDismiss && (
                <button
                    type="button"
                    onClick={onDismiss}
                    aria-label="Tutup notifikasi banner"
                    className="shrink-0 cursor-pointer rounded-sm p-1 text-ink-muted hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                >
                    <span aria-hidden="true" className="text-base leading-none">
                        ×
                    </span>
                </button>
            )}
        </div>
    );
}
