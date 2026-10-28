import { cn } from '@/lib/utils';
import {
    CalendarClock,
    Circle,
    CircleDot,
    Clock3,
    CheckCircle2,
    ShieldAlert,
    type LucideIcon,
} from 'lucide-react';

/**
 * Daily Work status vocabulary (.scratch/daily-work-verification/design.md §State machine).
 * P1 rule: status is always icon + text + color — color never alone (WCAG 1.4.1).
 */
export type DailyWorkStatus = 'pending' | 'in_progress' | 'pending_verify' | 'done';

export const STATUS_LABELS: Record<DailyWorkStatus, string> = {
    pending: 'Belum',
    in_progress: 'Proses',
    pending_verify: 'Perlu verifikasi HOD/TL',
    done: 'Telah selesai',
};

export const STATUS_ORDER: DailyWorkStatus[] = [
    'pending',
    'in_progress',
    'pending_verify',
    'done',
];

export const PROOF_MAX = 3;

const statusSpec: Record<
    DailyWorkStatus,
    { label: string; icon: LucideIcon; badge: string; rail: string }
> = {
    pending: {
        label: STATUS_LABELS.pending,
        icon: Circle,
        badge: 'bg-surface-raised text-ink-muted border-border',
        rail: 'border-border',
    },
    in_progress: {
        label: STATUS_LABELS.in_progress,
        icon: CircleDot,
        badge: 'bg-info-subtle text-info border-info/30',
        rail: 'border-info',
    },
    pending_verify: {
        label: STATUS_LABELS.pending_verify,
        icon: Clock3,
        badge: 'bg-warning-subtle text-warning-ink border-warning/30',
        rail: 'border-warning',
    },
    done: {
        label: STATUS_LABELS.done,
        icon: CheckCircle2,
        badge: 'bg-success-subtle text-success border-transparent',
        rail: 'border-success',
    },
};

/** Left 3px rail color for a status (card rail / hero segment). */
export function statusRail(status: DailyWorkStatus): string {
    return statusSpec[status].rail;
}

/** Read-only status chip: icon + label (+ verifier name when done). Non-interactive. */
export function StatusChip({
    item,
    size = 'md',
}: {
    item: { status: DailyWorkStatus; verified_by_name?: string | null };
    size?: 'sm' | 'md';
}) {
    const { label, icon: Icon, badge } = statusSpec[item.status];
    const display =
        item.status === 'done' && item.verified_by_name
            ? `${label} (${item.verified_by_name})`
            : label;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-sm border px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
                badge,
                size === 'sm' ? 'px-1.5 text-[10px]' : undefined,
            )}
        >
            <Icon aria-hidden="true" className="size-3.5" />
            {display}
        </span>
    );
}

/**
 * Status chip that doubles as a filter toggle button.
 * role="button", aria-pressed, min-h-11 (≥44px touch target), nowrap label.
 */
export function StatusFilterChip({
    value,
    label,
    active,
    onPress,
}: {
    value: 'all' | DailyWorkStatus;
    label: string;
    active: boolean;
    onPress: (next: 'all' | DailyWorkStatus) => void;
}) {
    return (
        <button
            type="button"
            role="button"
            aria-pressed={active}
            onClick={() => onPress(value)}
            className={cn(
                'inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium min-h-11 whitespace-nowrap',
                active
                    ? 'bg-primary text-primary-foreground border-primary'
                    : 'bg-surface text-ink-muted border-border hover:bg-surface-raised focus-visible:ring-ring focus-visible:ring-[3px] focus-visible:ring-offset-1',
            )}
        >
            {value !== 'all' && (
                <CalendarClock aria-hidden="true" className="size-3.5" />
            )}
            {label}
        </button>
    );
}

/**
 * Read-only callout shown when an item was rejected (status=in_progress +
 * rejection_note present). Warning-tinted surface; not a button.
 */
export function RejectionCallout({
    item,
}: {
    item: { rejection_note?: string | null; rejected_at?: string | null };
}) {
    if (!item.rejection_note) return null;

    return (
        <div
            className={cn(
                'flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-subtle px-3 py-2 text-xs',
            )}
            role="status"
        >
            <ShieldAlert aria-hidden="true" className="size-4 shrink-0 text-warning-ink" />
            <div className="leading-relaxed">
                <p className="font-semibold text-warning-ink">
                    Dikembalikan oleh HOD/TL
                </p>
                <p className="mt-0.5 text-warning-ink/80">
                    {item.rejection_note}
                </p>
            </div>
        </div>
    );
}
