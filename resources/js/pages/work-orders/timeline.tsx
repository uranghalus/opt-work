import { Head, Link, router } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import {
    Scheduler,
    type CalendarEvent,
    type CalendarTheme,
    type Resource,
    type ViewType,
} from 'calendarkit-pro';
import 'calendarkit-pro/styles.css';
import { Plus } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type MouseEvent as ReactMouseEvent } from 'react';
import type { DeadlineState } from '@/components/deadline-rail';
import type { WoCategory } from '@/components/category-badge';
import type { WoStatus } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useRequiredTenant } from '@/lib/tenant';
import { create, timeline } from '@/routes/work-orders';
import '../../../css/workorder-timeline.css';

/**
 * Timeline — CalendarKit Pro scheduler of scheduled Work Orders
 * (replaces the vis-timeline scheduler of 2026-10-27 and the FullCalendar
 * dayGrid calendar before it; see
 * docs/audit/workorder-timeline-calendarkit-decisions.md):
 *
 *   - Rows = target departments (resource view), columns = days.
 *   - Read-only: chips are real anchors to the WO detail page (middle-click
 *     and new-tab work); drag-reschedule is intentionally phase 2.
 *   - Server date semantics stay intact: the server (WITA, D2) owns "today",
 *     the visible window travels as ?start=&end= (end exclusive), and deadline
 *     states are precomputed server-side.
 *   - Zoom by granularity: the built-in view switcher (day/week/month) replaces
 *     the custom +/− zoom of the vis-timeline build (supersede D5–D7), and
 *     CalendarKit stacks overflow chips itself (supersede D11).
 */

type TimelineEventProps = {
    nomor_wo: string;
    title: string;
    status: WoStatus;
    category: WoCategory;
    deadline_state: DeadlineState;
    deadline_label: string;
    department_name: string | null;
};

type TimelineEvent = {
    id: string;
    title: string;
    start: string;
    end: string;
    url: string;
    extendedProps: TimelineEventProps;
};

type PageProps = InertiaConfig['sharedPageProps'] & {
    events: TimelineEvent[];
    totalScheduled: number;
    today: string;
    range: {
        start: string;
        end: string;
        view: string | null;
    };
};

const DAY_MS = 86_400_000;

/** Local `Y-m-d` of a Date — never `toISOString()` (UTC would shift the day). */
function toYMD(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');

    return `${year}-${month}-${day}`;
}

/** Monday of the week containing `date` (ISO weeks — the app speaks weeks). */
function startOfWeek(date: Date): Date {
    const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const dow = (d.getDay() + 6) % 7; // Mon=0 … Sun=6

    d.setDate(d.getDate() - dow);

    return d;
}

/**
 * Window served for a focus date + view. The server owns events per window,
 * so every navigation hits ?start=&end= (end exclusive, D2).
 */
function windowFor(
    view: ViewType,
    focus: Date,
): { start: Date; end: Date; bucket: 'day' | 'week' | 'month' | null } {
    const day = new Date(focus.getFullYear(), focus.getMonth(), focus.getDate());

    if (view === 'day') {
        return { start: day, end: new Date(day.getTime() + DAY_MS), bucket: 'day' };
    }

    if (view === 'month') {
        const start = new Date(day.getFullYear(), day.getMonth(), 1);
        const end = new Date(day.getFullYear(), day.getMonth() + 1, 1);

        return { start, end, bucket: 'month' };
    }

    // week, agenda, resource: a week window; agenda/resource have no bucket.
    const start = startOfWeek(day);

    return { start, end: new Date(start.getTime() + 7 * DAY_MS), bucket: view === 'week' ? 'week' : null };
}

/** Primary action of this page, rendered in the shell's heading band. */
function TimelineActions() {
    const tenant = useRequiredTenant('work-orders.create');

    return (
        <Button asChild size="lg">
            <Link href={create({ tenant })}>
                <Plus aria-hidden="true" className="size-4" />
                Buat Work Order
            </Link>
        </Button>
    );
}

const NO_DEPARTMENT_LABEL = 'Tanpa Departemen';

/**
 * Calendar theme — absorbs the app's own design tokens (light & dark aware)
 * instead of forcing a foreign palette on the page (ui-ux-pro-max audit:
 * Flat Design, dense dashboard, borders over shadows).
 */
const CALENDAR_THEME: CalendarTheme = {
    borderRadius: '0.75rem',
    colors: {
        primary: 'var(--brand)',
        primaryForeground: '#FFFFFF',
        secondary: 'var(--brand-soft)',
        background: 'var(--card)',
        foreground: 'var(--ink)',
        border: 'var(--border)',
        muted: 'var(--muted)',
        mutedForeground: 'var(--muted-foreground)',
        ring: 'var(--ring)',
    },
};

/** Chip accent per deadline state — matches the badge palette of the app. */
const DEADLINE_COLORS: Record<DeadlineState, string> = {
    // White text must clear 4.5:1 on every state (ui-ux audit 2026-10-27:
    // measured — the previous light shades failed at 3.2–3.5:1).
    on_track: '#15803d', // 5.02:1
    due_soon: '#b45309', // 5.02:1
    overdue: '#dc2626', // 4.83:1
    escalated: '#86198f', // 8.24:1
};

type WoCalendarEvent = CalendarEvent & {
    woUrl: string;
    nomorWo: string;
    woTitle: string;
    woStatus: WoStatus;
    deadlineState: DeadlineState;
    deadlineLabel: string;
    deptName: string;
};

const RESOURCE_PREFIX = 'dept:';

function resourceKey(deptName: string): string {
    return `${RESOURCE_PREFIX}${deptName}`;
}

/**
 * Map the server payload (FullCalendar-shaped, kept intact by the controller)
 * onto CalendarKit events: all-day (end exclusive, D2), colored by the
 * server-precomputed deadline state, pinned to their department row.
 */
function toCalendarEvents(events: TimelineEvent[]): CalendarEvent[] {
    return events.map((event) => {
        const props = event.extendedProps;
        const state = (props.deadline_state ?? 'on_track') as DeadlineState;

        // Server sends a numeric key; CalendarEvent.id is typed string —
        // cast here so the type (and React diffing) never sees a lie.
        return {
            id: String(event.id),
            title: `${props.nomor_wo} · ${props.title}`,
            start: new Date(`${event.start}T00:00:00`),
            end: new Date(`${event.end}T00:00:00`),
            allDay: true,
            color: DEADLINE_COLORS[state],
            resourceId: resourceKey(props.department_name ?? NO_DEPARTMENT_LABEL),
            woUrl: event.url,
            nomorWo: props.nomor_wo,
            woTitle: props.title,
            woStatus: props.status,
            deadlineState: state,
            deadlineLabel: props.deadline_label,
            deptName: props.department_name ?? NO_DEPARTMENT_LABEL,
        } satisfies WoCalendarEvent as unknown as CalendarEvent;
    });
}

function toResources(departmentNames: string[]): Resource[] {
    return departmentNames.map((name) => ({
        id: resourceKey(name),
        label: name,
    }));
}

/**
 * Chip content: a real anchor to the WO detail page so middle-click and
 * new-tab keep working (D3); primary clicks route through Inertia.
 */
function WoChip({ event }: { event: WoCalendarEvent }) {
    const open = (click: ReactMouseEvent<HTMLAnchorElement>) => {
        if (click.button !== 0 || click.metaKey || click.ctrlKey || click.shiftKey || click.altKey) {
            return; // let the browser do its native anchor navigation
        }

        click.preventDefault();
        click.stopPropagation();
        router.visit(event.woUrl);
    };

    return (
        <a
            href={event.woUrl}
            className={`wos-chip wotl-rail-${event.deadlineState}`}
            data-wo-chip
            onClick={open}
            aria-label={`${event.nomorWo} — ${event.woTitle}, status ${event.woStatus}, deadline ${event.deadlineLabel}, departemen ${event.deptName}`}
        >
            <span className="wos-chip-no">{event.nomorWo}</span>
            <span className="wos-chip-title">{event.woTitle}</span>
            <span className="wos-chip-meta">
                <span className="wos-chip-deadline">{event.deadlineLabel}</span>
                <span className="wos-chip-status">{event.woStatus}</span>
            </span>
        </a>
    );
}

export default function Timeline({ events, totalScheduled, range, today }: PageProps) {
    const tenant = useRequiredTenant('work-orders.timeline');
    const [navigating, setNavigating] = useState(false);

    // The calendar's focus date; seeded from the server-owned window.
    const [date, setDate] = useState<Date>(() => new Date(`${range.start || today}T00:00:00`));
    // Month is the default: a pure date grid (product call 2026-10-27 — no
    // hour gutters; only views the scheduler can't draw without an hour axis
    // keep one, and the user can still pick them from the switcher).
    const [view, setView] = useState<ViewType>('month');

    /** Departments (rows), ordered like the old build: id-ID aware. */
    const departments = useMemo(() => {
        const names = new Set<string>();

        for (const event of events) {
            names.add(event.extendedProps.department_name ?? NO_DEPARTMENT_LABEL);
        }

        return [...names].sort(
            (a, b) =>
                a.toLocaleLowerCase('id-ID').localeCompare(b.toLocaleLowerCase('id-ID'), 'id') ||
                a.localeCompare(b),
        );
    }, [events]);

    const resources = useMemo(() => toResources(departments), [departments]);
    const calendarEvents = useMemo(() => toCalendarEvents(events), [events]);

    // Rows must survive an empty window: if the user slides to a week without
    // WOs, keep showing the department rows they know instead of a bare grid.
    const lastDepartmentsRef = useRef<string[]>([]);

    lastDepartmentsRef.current = departments.length > 0 ? departments : lastDepartmentsRef.current;

    /** Travel the visible window through the URL (server is the source of events). */
    const syncWindowToUrl = useCallback(
        (focus: Date, activeView: ViewType) => {
            const win = windowFor(activeView, focus);
            const start = toYMD(win.start);
            const end = toYMD(win.end);
            const current = range;

            if (start === current.start && end === current.end) {
                return;
            }

            const query: Record<string, string> = { start, end };

            if (win.bucket !== null) {
                query.view = win.bucket;
            }

            router.get(
                timeline.url({ tenant }),
                query,
                {
                    only: ['events', 'totalScheduled', 'range'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onStart: () => setNavigating(true),
                    onFinish: () => setNavigating(false),
                },
            );
        },
        [tenant, range],
    );    // Back/forward (and server-driven window changes) re-sync the focus date.
    useEffect(() => {
        if (!range.start) {
            return;
        }

        if (toYMD(date) !== range.start) {
            setDate(new Date(`${range.start}T00:00:00`));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [range.start]);

    return (
        <>
            <Head title="Timeline" />

            <div className="flex flex-col gap-3">
                <p className="text-xs text-ink-muted" aria-live="polite">
                    {totalScheduled} Work Order terjadwal pada rentang tampil
                </p>

                <div
                    className="wos relative rounded-xl"
                    aria-busy={navigating ? 'true' : undefined}
                >
                    {navigating && (
                        <div className="wos-loading" aria-hidden="true">
                            <Skeleton className="h-8 w-48 rounded-lg" />
                        </div>
                    )}

                    <Scheduler
                            events={calendarEvents}
                            resources={toResources(
                                departments.length > 0 ? departments : lastDepartmentsRef.current,
                            )}
                            date={date}
                            view={view}
                            onDateChange={(next: Date) => {
                                setDate(next);
                                syncWindowToUrl(next, view);
                            }}
                            onViewChange={(next: ViewType) => {
                                setView(next);
                                syncWindowToUrl(date, next);
                            }}
                            readOnly={true}
                            timezone="Asia/Makassar" // WITA — the server's clock, D2
                            theme={CALENDAR_THEME}
                            // The hour grids only earn their space during working
                            // hours (audit: all-day chips drowned in a 0–24 grid).
                            dayStartHour={7}
                            dayEndHour={18}
                            slotDuration={60}
                            hideViewSwitcher={false}
                            classNames={{
                                root: 'wos-cal',
                                toolbar: 'wos-cal-toolbar',
                                sidebar: 'wos-cal-sidebar',
                                event: 'wos-cal-event',
                                slot: 'wos-cal-slot',
                                allDayBar: 'wos-cal-allday',
                            }}
                    components={{
                        EventContent: ({ event }) => (
                            <WoChip event={event as unknown as WoCalendarEvent} />
                        ),
                    }}
                />
            </div>

            <div
                className="wos-empty"
                role="status"
                hidden={calendarEvents.length > 0}
            >
                <span className="wos-empty-title">
                    Belum ada Work Order terjadwal pada rentang ini
                </span>
                    <span className="wos-empty-hint">
                        Rentang ini mungkin memang kosong — coba geser ke minggu lain,
                        atau buat Work Order baru bila pekerjaan sudah siap dijadwalkan.
                    </span>
                </div>
            </div>
        </>
    );
}

Timeline.layout = {
    breadcrumbs: [{ title: 'Work Order' }, { title: 'Timeline' }],
    title: 'Timeline',
    description: 'Work Order terjadwal per hari untuk cabang aktif',
    actions: TimelineActions,
};
