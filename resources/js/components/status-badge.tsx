import { cn } from '@/lib/utils';
import {
    Ban,
    CalendarClock,
    CircleCheck,
    CircleDashed,
    CircleX,
    ClipboardCheck,
    Eye,
    Hammer,
    Hourglass,
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
    | 'pending_dept_approval'
    | 'rejected_by_dept'
    | 'waiting_hod'
    | 'hod_approved'
    | 'scheduled'
    | 'assigned'
    | 'in_progress'
    | 'pending_verify'
    | 'revision'
    | 'closed'
    | 'cancelled'
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
    pending_dept_approval: {
        label: 'Menunggu Persetujuan Dept Pengaju',
        icon: Hourglass,
        className: 'bg-info/10 text-info border-info/30',
    },
    rejected_by_dept: {
        label: 'Ditolak Dept Pengaju',
        icon: CircleX,
        className: 'bg-danger/10 text-danger border-danger/30',
    },
    waiting_hod: {
        label: 'Menunggu Keputusan HOD',
        icon: Inbox,
        className: 'bg-info/10 text-info border-info/30',
    },
    hod_approved: {
        label: 'Disetujui HOD',
        icon: ClipboardCheck,
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
    cancelled: {
        label: 'Dibatalkan',
        icon: Ban,
        className: 'bg-surface-raised text-ink-muted border-border',
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
    pending_dept_approval: 'Menunggu Dept',
    rejected_by_dept: 'Ditolak Dept',
    waiting_hod: 'Menunggu HOD',
    pending_verify: 'Verifikasi',
    in_progress: 'Dikerjakan',
};

/** Full label for a status — for text contexts without the badge (timelines). */
export function statusLabel(status: WoStatus): string {
    return statusSpec[status].label;
}

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
