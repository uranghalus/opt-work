import { useEffect, useState } from 'react';
import { Timer } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Timer durasi kerja (R17): dasarnya SELALU dihitung server dari timestamp
 * tersimpan (`elapsedSeconds` dari payload) — komponen ini hanya
 * men-tick tampilan tiap detik di antara request, sehingga tetap benar
 * setelah refresh atau dibuka dari perangkat lain. State `paused`
 * membeku pada nilai server; `finished` juga.
 */
export type TimerState =
    | 'not_started'
    | 'accepted'
    | 'running'
    | 'paused'
    | 'finished';

const stateStyles: Record<TimerState, string> = {
    not_started: 'bg-surface-raised text-ink-muted border-border',
    accepted: 'bg-info/10 text-info border-info/30',
    running: 'bg-info/10 text-info border-info/30',
    paused: 'bg-warning/10 text-warning-ink border-warning/30',
    finished: 'bg-success/10 text-success border-success/30',
};

const stateLabels: Record<TimerState, string> = {
    not_started: 'Belum dimulai',
    accepted: 'Diterima — belum mulai',
    running: 'Durasi berjalan',
    paused: 'Durasi dijeda',
    finished: 'Durasi selesai',
};

export function formatElapsed(totalSeconds: number): string {
    const safe = Math.max(0, totalSeconds);
    const hours = Math.floor(safe / 3600);
    const minutes = Math.floor((safe % 3600) / 60);
    const seconds = safe % 60;

    return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

export function LiveTimer({
    elapsedSeconds,
    state,
    className,
}: {
    elapsedSeconds: number;
    state: TimerState;
    className?: string;
}) {
    const [seconds, setSeconds] = useState(elapsedSeconds);

    useEffect(() => {
        setSeconds(elapsedSeconds);
    }, [elapsedSeconds]);

    useEffect(() => {
        if (state !== 'running') {
            return;
        }

        const interval = window.setInterval(() => {
            setSeconds((current) => current + 1);
        }, 1000);

        return () => window.clearInterval(interval);
    }, [state]);

    return (
        <span
            role="timer"
            aria-label={stateLabels[state]}
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap',
                stateStyles[state],
                className,
            )}
        >
            <Timer aria-hidden className="size-3.5" />
            <span className="font-mono tabular-nums">
                {formatElapsed(seconds)}
            </span>
            <span>{stateLabels[state]}</span>
        </span>
    );
}
