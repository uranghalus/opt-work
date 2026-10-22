import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { CategoryBadge, type WoCategory } from '@/components/category-badge';
import { DeadlineRail, type DeadlineState } from '@/components/deadline-rail';
import { StatusBadge, type WoStatus } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { useRequiredTenant } from '@/lib/tenant';
import { create, show, timeline } from '@/routes/work-orders';

type TimelineWorkOrder = {
    id: string;
    nomor_wo: string;
    title: string;
    status: WoStatus;
    category: WoCategory;
    deadline_date: string | null;
    deadline_state: DeadlineState;
    deadline_label: string;
    requester_name: string | null;
    department_name: string | null;
};

type TimelineDay = {
    date: string;
    dayName: string;
    dayNumber: number;
    monthName: string;
    isToday: boolean;
    workOrders: TimelineWorkOrder[];
};

type PageProps = {
    weekStart: string;
    weekEnd: string;
    weekLabel: string;
    today: string;
    isCurrentWeek: boolean;
    prevWeek: string;
    nextWeek: string;
    totalScheduled: number;
    days: TimelineDay[];
};

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

/**
 * One scheduled Work Order as a timeline event block.
 *
 * The card keeps the WoCard anatomy from DESIGN.md — category badge, title,
 * status, deadline rail — but compressed, because a week grid gives each day
 * roughly one sixth of the content width.
 */
function TimelineCard({
    workOrder,
    tenant,
}: {
    workOrder: TimelineWorkOrder;
    tenant: string;
}) {
    return (
        <Link
            href={show({ tenant, workOrder: workOrder.id })}
            className="block rounded-xl focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
        >
            <Card className="gap-0 border-border py-3 shadow-card transition-[box-shadow,border-color] duration-150 ease-standard hover:border-border-strong hover:shadow-raised motion-reduce:transition-none">
                <CardContent className="flex gap-2 px-3">
                    <DeadlineRail
                        state={workOrder.deadline_state}
                        label={workOrder.deadline_label}
                    />

                    <div className="min-w-0 flex-1">
                        <p className="truncate font-mono text-xs text-ink-muted">
                            {workOrder.nomor_wo}
                        </p>

                        <p className="mt-0.5 line-clamp-2 text-[0.8125rem] leading-snug font-semibold text-ink">
                            {workOrder.title}
                        </p>

                        <div className="mt-2 flex min-w-0 flex-wrap items-center gap-1">
                            <StatusBadge status={workOrder.status} compact />
                            <CategoryBadge category={workOrder.category} />
                        </div>

                        <p className="mt-2 flex items-start gap-1 text-xs text-ink-muted">
                            <CalendarClock
                                aria-hidden="true"
                                className="mt-px size-3 shrink-0"
                            />
                            <span className="min-w-0">
                                <span className="font-semibold text-ink">
                                    {workOrder.deadline_label}
                                </span>
                                {workOrder.department_name && (
                                    <span className="block truncate">
                                        {workOrder.department_name}
                                    </span>
                                )}
                            </span>
                        </p>
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

/**
 * One day column.
 *
 * The "today" column is marked with a text label plus a rule, never with a tint
 * alone: a colour-only cue fails WCAG 1.4.1, and a tint near white fails 1.4.11.
 */
function DayColumn({ day, tenant }: { day: TimelineDay; tenant: string }) {
    const headingId = `timeline-day-${day.date}`;

    return (
        <section
            aria-labelledby={headingId}
            className={cn(
                'flex min-w-0 flex-col rounded-xl border bg-card p-2.5 shadow-card',
                day.isToday ? 'border-brand' : 'border-border',
            )}
        >
            <div
                aria-hidden="true"
                className={cn(
                    'h-0.5 w-full rounded-full',
                    day.isToday ? 'bg-brand' : 'bg-transparent',
                )}
            />

            <header className="mt-2 flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <h2
                        id={headingId}
                        aria-current={day.isToday ? 'date' : undefined}
                        className={cn(
                            'truncate text-xs font-semibold tracking-wide uppercase',
                            day.isToday ? 'text-brand' : 'text-ink-muted',
                        )}
                    >
                        {day.dayName}
                    </h2>
                    <p className="text-sm font-semibold tabular-nums text-ink">
                        {day.dayNumber} {day.monthName}
                    </p>
                </div>

                {day.isToday && (
                    <span className="shrink-0 rounded-sm bg-brand-soft px-1.5 py-0.5 text-xs font-semibold text-brand">
                        Hari ini
                    </span>
                )}
            </header>

            {day.workOrders.length === 0 ? (
                <p className="mt-3 rounded-lg bg-surface-sunken px-2 py-4 text-center text-xs text-ink-subtle">
                    Tidak ada WO terjadwal
                </p>
            ) : (
                <ul className="mt-2 flex flex-col gap-2">
                    {day.workOrders.map((workOrder) => (
                        <li key={workOrder.id}>
                            <TimelineCard workOrder={workOrder} tenant={tenant} />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export default function Timeline({
    weekLabel,
    isCurrentWeek,
    prevWeek,
    nextWeek,
    totalScheduled,
    days,
}: PageProps) {
    const tenant = useRequiredTenant('work-orders.timeline');

    const goToWeek = (week?: string) => {
        router.get(
            timeline({ tenant }),
            week ? { week } : {},
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Timeline" />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-1.5">
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Minggu sebelumnya"
                            onClick={() => goToWeek(prevWeek)}
                        >
                            <ChevronLeft aria-hidden="true" className="size-4" />
                        </Button>

                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Minggu berikutnya"
                            onClick={() => goToWeek(nextWeek)}
                        >
                            <ChevronRight aria-hidden="true" className="size-4" />
                        </Button>

                        <p className="ml-1 text-sm font-semibold text-ink">
                            {weekLabel}
                        </p>

                        {!isCurrentWeek && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="ml-1"
                                onClick={() => goToWeek()}
                            >
                                Minggu ini
                            </Button>
                        )}
                    </div>

                    <p className="text-xs text-ink-muted">
                        {totalScheduled} Work Order terjadwal
                    </p>
                </div>

                {/*
                  The week grid scrolls inside its own region instead of
                  widening the page (DESIGN.md: "board scrolls horizontally
                  inside its own region"). The minimum width keeps a day
                  column wide enough for the WoCard trio - status, category
                  and deadline - to stay readable.

                  The region is focusable and labelled: a scrollable area has
                  to be reachable by keyboard (WCAG 2.1.1), and a scrollbar is
                  not guaranteed to be drawn.
                */}
                <div
                    role="region"
                    aria-label="Jadwal Work Order per hari, geser mendatar untuk melihat ketujuh hari"
                    tabIndex={0}
                    className="overflow-x-auto rounded-xl pb-2 focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                >
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-7 xl:min-w-[1388px]">
                        {days.map((day) => (
                            <DayColumn key={day.date} day={day} tenant={tenant} />
                        ))}
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
