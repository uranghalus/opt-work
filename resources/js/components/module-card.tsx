import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

/*
 * Module card (DESIGN.md §Module Grid): rounded-square icon on a *-subtle
 * tinted background (Pastel Pair Rule — full-strength token as icon color),
 * primary count, muted label. Card hover lift is a signature motion.
 */
type Props = {
    icon: LucideIcon;
    /** Full-strength signal color for the icon, e.g. `text-info`. */
    iconClassName: string;
    /** Paired subtle tint for the icon square, e.g. `bg-info-subtle`. */
    iconBgClassName: string;
    count?: number | string;
    label: string;
    description?: string;
    href?: string;
    onClick?: () => void;
    pressed?: boolean;
    className?: string;
};

export function ModuleCard({
    icon: Icon,
    iconClassName,
    iconBgClassName,
    count,
    label,
    description,
    href,
    onClick,
    pressed,
    className,
}: Props) {
    const inner = (
        <>
            <span
                className={cn(
                    'flex size-11 shrink-0 items-center justify-center rounded-lg',
                    iconBgClassName,
                )}
            >
                <Icon aria-hidden="true" className={cn('size-5', iconClassName)} />
            </span>
            <span className="min-w-0 flex-1">
                {count !== undefined && (
                    <span className="block text-base leading-tight font-semibold">
                        {count}
                    </span>
                )}
                <span className="block truncate text-[0.8125rem] leading-[1.4] text-ink-muted">
                    {label}
                </span>
                {description && (
                    <span className="mt-0.5 block truncate text-xs text-ink-subtle">
                        {description}
                    </span>
                )}
            </span>
        </>
    );

    const base = cn(
        'group flex w-full items-center gap-3 rounded-xl border bg-card p-4 text-left',
        'transition-[box-shadow,translate] duration-120 ease-standard motion-reduce:transition-none motion-reduce:hover:translate-y-0',
        'hover:-translate-y-px hover:shadow-raised',
        'focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none',
        pressed
            ? 'border-border-strong ring-1 ring-ring/40'
            : 'border-border',
        className,
    );

    if (href) {
        return (
            <Link href={href} className={base}>
                {inner}
            </Link>
        );
    }

    return (
        <button type="button" onClick={onClick} aria-pressed={pressed} className={base}>
            {inner}
        </button>
    );
}
