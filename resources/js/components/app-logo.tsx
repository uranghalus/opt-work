import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

/**
 * Splits a CamelCase brand name ("OptiWorks") into ["Opti", "Works"] for
 * the two-tone wordmark. Falls back to a single tone when the name has
 * fewer than two capitals.
 */
function splitBrand(name: string): [string, string | null] {
    const capitals = [...name].reduce<number[]>(
        (acc, ch, i) => (/[A-Z]/.test(ch) ? [...acc, i] : acc),
        [],
    );

    if (capitals.length >= 2) {
        return [name.slice(0, capitals[1]), name.slice(capitals[1])];
    }

    return [name, null];
}

type Props = {
    /**
     * `panel` sits on the dark navigation stratum (default shell),
     * `surface` on light/dark content surfaces (auth and marketing pages).
     */
    variant?: 'panel' | 'surface';
    /** Icon-only mark, used by the collapsed navigation rail. */
    compact?: boolean;
    className?: string;
};

export default function AppLogo({
    variant = 'surface',
    compact = false,
    className,
}: Props) {
    const { name } = usePage().props;
    const [head, tail] = splitBrand(name ?? '');
    const onPanel = variant === 'panel';

    return (
        <span
            data-slot="app-logo"
            className={cn('flex min-w-0 items-center gap-2.5', className)}
        >
            <span
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                    'bg-linear-to-br from-brand-strong to-brand-deep text-on-brand',
                    onPanel
                        ? 'ring-1 ring-brand-strong/40'
                        : 'ring-1 ring-black/5 dark:ring-white/10',
                )}
            >
                <AppLogoIcon className="size-4 fill-current" />
            </span>
            {!compact && (
                <span
                    data-slot="app-logo-wordmark"
                    className={cn(
                        'truncate text-base leading-tight font-semibold tracking-[-0.01em]',
                        onPanel ? 'text-panel-ink' : 'text-ink',
                    )}
                >
                    {head}
                    {tail && (
                        <span
                            className={
                                onPanel ? 'text-brand-strong' : 'text-brand'
                            }
                        >
                            {tail}
                        </span>
                    )}
                </span>
            )}
        </span>
    );
}
