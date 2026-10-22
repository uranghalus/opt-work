import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type RegistryStat = {
    label: string;
    value: number;
    icon: LucideIcon;
    /** `warning` lights the numeral in amber — use it for a real exception. */
    tone?: 'default' | 'warning';
};

/**
 * The dark measurement plate at the top of a master-data page.
 *
 * It exists to give the page a subject before the record list starts: three
 * numbers set in one large tabular size, on the same deep stratum as the
 * navigation panel. One plate per page, three cells, no charts.
 */
export function RegistryBand({
    stats,
    label,
}: {
    stats: RegistryStat[];
    label: string;
}) {
    return (
        <section
            aria-label={label}
            className="overflow-hidden rounded-xl bg-panel shadow-raised ring-1 ring-panel-border"
        >
            <div aria-hidden="true" className="h-0.5 w-full bg-brand-strong" />

            <dl className="grid grid-cols-3 divide-x divide-panel-border">
                {stats.map((stat) => {
                    const Icon = stat.icon;
                    const isWarning = stat.tone === 'warning';

                    return (
                        <div
                            key={stat.label}
                            className="min-w-0 px-3.5 py-3.5 sm:px-6 sm:py-5"
                        >
                            <dt className="flex items-center gap-1.5 text-[9.5px] font-semibold tracking-[0.12em] text-panel-ink-muted uppercase sm:gap-2 sm:text-[11px] sm:tracking-[0.14em]">
                                <Icon
                                    aria-hidden="true"
                                    className={cn(
                                        'size-3 shrink-0 sm:size-3.5',
                                        isWarning
                                            ? 'text-warning-bright'
                                            : 'text-brand-strong',
                                    )}
                                />
                                <span className="truncate">{stat.label}</span>
                            </dt>
                            <dd
                                className={cn(
                                    'mt-1.5 text-[1.5rem] leading-none font-semibold tracking-[-0.03em] tabular-nums sm:mt-2 sm:text-[2rem]',
                                    isWarning
                                        ? 'text-warning-bright'
                                        : 'text-panel-ink',
                                )}
                            >
                                {stat.value.toLocaleString('id-ID')}
                            </dd>
                        </div>
                    );
                })}
            </dl>
        </section>
    );
}
