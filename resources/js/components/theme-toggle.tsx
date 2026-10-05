import { Monitor, Moon, Sun, type LucideIcon } from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const OPTIONS: { value: Appearance; icon: LucideIcon; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Terang' },
    { value: 'dark', icon: Moon, label: 'Gelap' },
    { value: 'system', icon: Monitor, label: 'Sistem' },
];

type Props = {
    className?: string;
    /** Show the text label next to each icon (mobile sheet, settings). */
    showLabels?: boolean;
    /** `panel` inverts the control for the dark navigation stratum. */
    tone?: 'surface' | 'panel';
};

/**
 * Single segmented control for the appearance: light, dark, or follow the
 * system. The active chip is the only moving part — no dropdown detour.
 */
export function ThemeToggle({
    className,
    showLabels = false,
    tone = 'surface',
}: Props) {
    const { appearance, updateAppearance } = useAppearance();
    const onPanel = tone === 'panel';

    return (
        <div
            role="radiogroup"
            aria-label="Tampilan aplikasi"
            className={cn(
                'inline-flex items-center gap-0.5 rounded-full border p-0.5',
                onPanel
                    ? 'border-panel-border bg-white/5'
                    : 'border-border bg-surface-raised',
                className,
            )}
        >
            {OPTIONS.map(({ value, icon: Icon, label }) => {
                const active = appearance === value;

                return (
                    <button
                        key={value}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        aria-label={label}
                        title={label}
                        onClick={() => updateAppearance(value)}
                        className={cn(
                            'relative inline-flex h-8 cursor-pointer items-center justify-center gap-1.5 rounded-full text-xs font-medium',
                            'transition-[background-color,color,box-shadow] duration-200 ease-standard',
                            'after:absolute after:-inset-1.5 after:content-[""]',
                            'focus-visible:ring-2 focus-visible:outline-none',
                            'motion-reduce:transition-none',
                            showLabels ? 'min-w-8 px-2.5' : 'size-8',
                            onPanel
                                ? 'focus-visible:ring-brand-strong/70'
                                : 'focus-visible:ring-ring/60',
                            active
                                ? onPanel
                                    ? 'bg-panel-active text-panel-ink ring-1 ring-brand-strong/30'
                                    : 'bg-card text-foreground ring-1 ring-border'
                                : onPanel
                                  ? 'text-panel-ink-muted hover:bg-panel-hover hover:text-panel-ink'
                                  : 'text-ink-muted hover:bg-accent hover:text-foreground',
                        )}
                    >
                        <Icon
                            aria-hidden="true"
                            className={cn(
                                'size-3.5 shrink-0 transition-transform duration-200 ease-standard motion-reduce:transition-none',
                                active && 'scale-110',
                                active && onPanel && 'text-brand-strong',
                            )}
                        />
                        {showLabels && <span>{label}</span>}
                    </button>
                );
            })}
        </div>
    );
}
