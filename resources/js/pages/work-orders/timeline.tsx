import { Head, router, Link } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import FullCalendar, {
    type CalendarRef,
    type DayHeaderInfo,
    type DatesSetInfo,
    type EventClickInfo,
    type EventDisplayInfo,
} from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/react/daygrid';
import idLocale from '@fullcalendar/react/locales/id';
import { Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CategoryBadge, type WoCategory } from '@/components/category-badge';
import type { DeadlineState } from '@/components/deadline-rail';
import { StatusBadge, type WoStatus } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useRequiredTenant } from '@/lib/tenant';
import { create, timeline } from '@/routes/work-orders';
import '@fullcalendar/react/skeleton.css';
import '../../../css/fullcalendar-timeline.css';

/**
 * Timeline — scheduler calendar of scheduled Work Orders (FullCalendar v7
 * dayGrid; redesign 2026-10-24, see docs/audit/workorder-timeline-design-decisions.md).
 *
 * Date semantics stay server-side: the server (WITA, D2) owns "today" (sent as
 * the calendar's `now`), the visible range travels as `?start=&end=&view=`
 * (deep-linkable), and deadline states are precomputed server-side — the
 * browser never derives dates for data.
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

/** Local `Y-m-d` of a Date — never `toISOString()` (UTC would shift the day). */
function toYMD(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const dayNameFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'short' });
const dayMonthFormatter = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
});

/** Weekends get a muted treatment (SLA counts business days); never hidden. */
function isWeekend(date: Date): boolean {
    const day = date.getDay();

    return day === 0 || day === 6;
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

/** Month view: a single-line chip — deadline-state dot + nomor + title. */
function MonthEventChip({ props }: { props: TimelineEventProps }) {
    return (
        <span className="wotl-event-chip" title={props.deadline_label}>
            <span
                aria-hidden="true"
                className={`wotl-rail wotl-rail-${props.deadline_state} size-2 shrink-0 rounded-full`}
            />
            <span className="wotl-chip-title">
                {props.nomor_wo} · {props.title}
            </span>
        </span>
    );
}

/** Week view: the compact WoCard — deadline rail, nomor, title, badges. */
function WeekEventCard({ props }: { props: TimelineEventProps }) {
    return (
        <div className="wotl-event-card">
            <span
                aria-hidden="true"
                className={`wotl-rail wotl-rail-${props.deadline_state}`}
            />
            <div className="wotl-card-body">
                <span className="wotl-card-number">{props.nomor_wo}</span>
                <span className="wotl-card-title">{props.title}</span>
                <span className="wotl-card-badges">
                    <StatusBadge status={props.status} compact />
                    <CategoryBadge category={props.category} />
                </span>
                <span className="wotl-card-deadline">{props.deadline_label}</span>
            </div>
        </div>
    );
}

export default function Timeline({ events, totalScheduled, today, range }: PageProps) {
    const tenant = useRequiredTenant('work-orders.timeline');
    const calendarRef = useRef<CalendarRef>(null);
    const [navigating, setNavigating] = useState(false);

    // One-time initial view: the URL wins; otherwise phones start on the
    // month grid (a 7-column week is unreadable at phone width — audit R2).
    const [initialView] = useState(() => {
        if (range.view) {
            return range.view;
        }

        return typeof window !== 'undefined' && window.innerWidth < 768
            ? 'dayGridMonth'
            : 'dayGridWeek';
    });

    const rangeRef = useRef(range);
    rangeRef.current = range;

    /**
     * Range changes come from the calendar (datesSet) — mirror them into the
     * URL with a partial reload so views stay deep-linkable. The guard keeps
     * the initial render and the post-reload render from looping.
     */
    const handleDatesSet = (info: DatesSetInfo) => {
        const start = toYMD(info.start);
        const end = toYMD(info.end);
        const view = info.view.type;
        const current = rangeRef.current;

        if (start === current.start && end === current.end && view === current.view) {
            return;
        }

        router.get(
            timeline.url({ tenant }, { query: { start, end, view } }),
            {},
            {
                only: ['events', 'totalScheduled', 'range'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setNavigating(true),
                onFinish: () => setNavigating(false),
            },
        );
    };

    // Back/forward (and server-driven view changes) re-sync the calendar.
    useEffect(() => {
        const api = calendarRef.current?.getApi();

        if (!api || !range.start) {
            return;
        }

        const activeStart = toYMD(api.view.activeStart);
        const viewChanged = range.view !== null && api.view.type !== range.view;

        if (activeStart !== range.start || viewChanged) {
            if (viewChanged) {
                api.changeView(range.view ?? api.view.type);
            }

            if (activeStart !== range.start) {
                api.gotoDate(range.start);
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [range.start, range.view]);

    const handleEventClick = (info: EventClickInfo) => {
        // The event is a real anchor (url prop) — keep semantics for
        // middle-click/new-tab, route via Inertia for a client-side visit.
        info.jsEvent.preventDefault();
        router.visit(info.event.url);
    };

    const renderEventContent = (arg: EventDisplayInfo) => {
        const props = arg.event.extendedProps as unknown as TimelineEventProps;

        return arg.view.type === 'dayGridMonth'
            ? <MonthEventChip props={props} />
            : <WeekEventCard props={props} />;
    };

    const renderDayHeader = (arg: DayHeaderInfo) => (
        <span className="flex flex-col items-center gap-0.5">
            <span>{dayNameFormatter.format(arg.date)}</span>
            <span className="text-[0.8125rem] font-semibold tracking-normal normal-case text-ink">
                {dayMonthFormatter.format(arg.date)}
            </span>
            {toYMD(arg.date) === today && (
                <span className="rounded-sm bg-brand px-1.5 py-0.5 text-[0.625rem] font-semibold text-white">
                    Hari ini
                </span>
            )}
        </span>
    );

    return (
        <>
            <Head title="Timeline" />

            <div className="flex flex-col gap-3">
                <p className="text-xs text-ink-muted" aria-live="polite">
                    {totalScheduled} Work Order terjadwal pada rentang tampil
                </p>

                <div className="relative">
                    {navigating && (
                        <div className="wotl-loading" aria-hidden="true">
                            <Skeleton className="h-8 w-48 rounded-lg" />
                        </div>
                    )}

                    {/*
                      The grid scrolls inside its own region on narrow screens
                      (DESIGN.md: never page-level horizontal scroll). The
                      region is focusable and labelled (WCAG 2.1.1).
                    */}
                    <div
                        role="region"
                        aria-label="Jadwal Work Order — geser mendatar bila perlu untuk melihat seluruh hari"
                        tabIndex={0}
                        className="wotl-calendar overflow-x-auto rounded-xl focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                    >
                        <FullCalendar
                            ref={calendarRef}
                            plugins={[dayGridPlugin]}
                            locale={idLocale}
                            initialView={initialView}
                            initialDate={range.start || today}
                            now={today}
                            firstDay={1}
                            headerToolbar={{
                                start: 'today prev,next',
                                center: 'title',
                                end: 'dayGridWeek,dayGridMonth',
                            }}
                            buttons={{
                                today: { text: 'Hari ini', hint: 'Ke hari ini (zona WITA)' },
                                prev: { hint: 'Mundur satu periode' },
                                next: { hint: 'Maju satu periode' },
                                dayGridWeek: { text: 'Minggu', hint: 'Tampilan mingguan' },
                                dayGridMonth: { text: 'Bulan', hint: 'Tampilan bulanan' },
                            }}
                            moreLinkText="lagi"
                            dayMaxEvents
                            events={events}
                            eventContent={renderEventContent}
                            eventClick={handleEventClick}
                            datesSet={handleDatesSet}
                            dayHeaderContent={renderDayHeader}
                            dayCellClass={(arg) => (isWeekend(arg.date) ? 'fc-day-weekend' : undefined)}
                            dayHeaderClass={(arg) => (isWeekend(arg.date) ? 'fc-day-weekend' : undefined)}
                            height="auto"
                            fixedWeekCount={false}
                            editable={false}
                            droppable={false}
                        />
                    </div>
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
