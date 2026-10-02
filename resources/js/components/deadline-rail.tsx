import { cn } from '@/lib/utils';

/**
 * DeadlineRail — the Dispatch Board signature (DESIGN_BRIEF §2).
 * A thin left rail whose token shifts schedule → warning → danger →
 * escalation, paired with day-count text. Motion is a 180-220ms color
 * crossfade, disabled under prefers-reduced-motion.
 */
export type DeadlineState = 'on_track' | 'due_soon' | 'overdue' | 'escalated';

const railToken: Record<DeadlineState, string> = {
    on_track: 'bg-ink-muted',
    due_soon: 'bg-warning',
    overdue: 'bg-danger',
    escalated: 'bg-escalation',
};

const railLabel: Record<DeadlineState, string> = {
    on_track: 'On track',
    due_soon: 'Deadline besok',
    overdue: 'Telat',
    escalated: 'Eskalasi',
};

export function DeadlineRail({
    state,
    label,
    className,
}: {
    state: DeadlineState;
    label?: string;
    className?: string;
}) {
    return (
        <span
            role="img"
            aria-label={`Deadline: ${label ?? railLabel[state]}`}
            className={cn(
                'flex w-1 shrink-0 self-stretch rounded-full transition-colors duration-200',
                'motion-reduce:transition-none',
                railToken[state],
                className,
            )}
        />
    );
}
