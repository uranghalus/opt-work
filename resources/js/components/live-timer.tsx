import { useEffect, useState } from 'react';
import { Clock, Pause, Play, Timer, XCircle } from 'lucide-react';

type LiveTimerState = 'not_started' | 'running' | 'paused' | 'finished' | 'accepted';

type LiveTimerProps = {
    elapsedSeconds: number;
    state: LiveTimerState;
};

function formatTime(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
}

const stateConfig: Record<LiveTimerState, { label: string; icon: React.ElementType; color: string }> = {
    not_started: { label: 'Belum dimulai', icon: Play, color: 'text-ink-muted' },
    running: { label: 'Bekerja', icon: Pause, color: 'text-success-ink' },
    paused: { label: 'Dijeda', icon: Play, color: 'text-warning-ink' },
    finished: { label: 'Selesai', icon: Timer, color: 'text-brand' },
    accepted: { label: 'Diterima', icon: XCircle, color: 'text-ink-muted' },
};

export function LiveTimer({ elapsedSeconds, state }: LiveTimerProps) {
    const [displaySeconds, setDisplaySeconds] = useState(elapsedSeconds);
    const { label, icon: Icon, color } = stateConfig[state];

    useEffect(() => {
        if (state !== 'running') {
            return;
        }

        const interval = setInterval(() => {
            setDisplaySeconds((prev) => prev + 1);
        }, 1000);

        return () => clearInterval(interval);
    }, [state]);

    return (
        <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-border bg-surface raised text-xs font-mono tabular-nums">
            <Icon className={`size-3.5 ${color}`} aria-hidden="true" />
            <span className={`${color} font-medium`}>{formatTime(displaySeconds)}</span>
            <span className="text-[10px] font-medium text-ink-muted uppercase">{label}</span>
        </div>
    );
}
