import { cn } from '@/lib/utils';
import {
    CalendarClock,
    CircleCheck,
    CircleDashed,
    Eye,
    Hammer,
    Inbox,
    RotateCcw,
    ShieldAlert,
    TimerOff,
    UserCheck,
    type LucideIcon,
} from 'lucide-react';

/**
 * Work Order status vocabulary (DESIGN_BRIEF §7 StatusBadge).
 * P1 rule: status is always icon + text + color — color never alone.
 */
export type WoStatus =
    | 'draft'
    | 'waiting_hod'
    | 'scheduled'
    | 'assigned'
    | 'in_progress'
    | 'pending_verify'
    | 'revision'
    | 'closed'
    | 'overdue'
    | 'escalated';

const statusSpec: Record<
    WoStatus,
    { label: string; icon: LucideIcon; className: string }
> = {
    draft: {
        label: 'Draf',
        icon: CircleDashed,
        className: 'bg-surface-raised text-ink-muted border-border',
    },
    waiting_hod: {
        label: 'Menunggu Keputusan HOD',
        icon: Inbox,
        className: 'bg-info/10 text-info border-info/30',
    },
    scheduled: {
        label: 'Terjadwal',
        icon: CalendarClock,
        className: 'bg-info/10 text-info border-info/30',
    },
    assigned: {
        label: 'Ditugaskan',
        icon: UserCheck,
        className: 'bg-info/10 text-info border-info/30',
    },
    in_progress: {
        label: 'Dikerjakan',
        icon: Hammer,
        className: 'bg-info/10 text-info border-info/30',
    },
    pending_verify: {
        label: 'Menunggu Verifikasi',
        icon: Eye,
        className: 'bg-warning/10 text-warning border-warning/30',
    },
    revision: {
        label: 'Revisi',
        icon: RotateCcw,
        className: 'bg-warning/10 text-warning border-warning/30',
    },
    closed: {
        label: 'Selesai',
        icon: CircleCheck,
        className: 'bg-success/10 text-success border-success/30',
    },
    overdue: {
        label: 'Telat',
        icon: TimerOff,
        className: 'bg-danger/10 text-danger border-danger/30',
    },
    escalated: {
        label: 'Eskalasi',
        icon: ShieldAlert,
        className: 'bg-escalation/10 text-escalation border-escalation/30',
    },
};

/** Shorter labels for dense contexts (tables); meaning is preserved. */
const compactLabel: Partial<Record<WoStatus, string>> = {
    waiting_hod: 'Menunggu HOD',
    pending_verify: 'Verifikasi',
    in_progress: 'Dikerjakan',
};

export function StatusBadge({
    status,
    compact = false,
    className,
}: {
    status: WoStatus;
    compact?: boolean;
    className?: string;
}) {
    const { label, icon: Icon, className: tone } = statusSpec[status];
    const display = (compact && compactLabel[status]) || label;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-sm border px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
                tone,
                className,
            )}
        >
            <Icon aria-hidden="true" className="size-3.5" />
            {display}
        </span>
    );
}
