import { Head, Link, router, useForm } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import {
    Building2,
    CalendarDays,
    Camera,
    ChevronLeft,
    ChevronRight,
    MapPin,
    UserCheck,
} from 'lucide-react';
import { show as showWorkOrder } from '@/routes/work-orders';
import {
    accept as acceptTime,
    start as startTime,
} from '@/routes/work-orders/work-time';
import { CategoryBadge } from '@/components/category-badge';
import { StatusBadge, type WoStatus } from '@/components/status-badge';
import {
    LiveTimer,
    type TimerState,
} from '@/components/live-timer';
import { Button } from '@/components/ui/button';
import type { DeadlineState } from '@/types/deadline';
import { useRequiredTenant } from '@/lib/tenant';

/**
 * "Tugasan Saya" (R15): WO eksekusi aktif yang ditugaskan ke karyawan ini
 * — penugasi, department tujuan, urgensi, deadline, lokasi, deskripsi,
 * lampiran, dan aksi jelas (Terima / Mulai / Buka Detail untuk menyelesaikan).
 * Mobile-first: kartu tumpuk, target sentuh ≥44px.
 */
type Assignment = {
    id: string;
    nomor_wo: string;
    title: string;
    description: string;
    category: 'normal' | 'accident' | 'owner';
    status: WoStatus;
    location: string | null;
    assignment_notes: string | null;
    deadline_date: string | null;
    deadline_state: DeadlineState;
    deadline_label: string;
    target_department: string | null;
    assigner_name: string | null;
    attachments_count: number;
    time_log: { state: TimerState; elapsed_seconds: number } | null;
};

type PaginatedData = {
    data: Assignment[];
    current_page: number;
    last_page: number;
};

type PageProps = InertiaConfig['sharedPageProps'] & {
    assignments: PaginatedData;
};

const formatDate = (value: string): string =>
    new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });

function DeadlineRail({
    state,
    label,
}: {
    state: DeadlineState;
    label: string;
}) {
    const colors: Record<DeadlineState, string> = {
        on_track: 'border-success',
        due_soon: 'border-warning',
        overdue: 'border-danger',
        escalated: 'border-escalation',
    };

    return (
        <div
            className={`w-[3px] rounded-s-full ${colors[state]}`}
            aria-label={label}
            title={label}
        />
    );
}

function Pagination({
    currentPage,
    lastPage,
    baseUrl,
}: {
    currentPage: number;
    lastPage: number;
    baseUrl: string;
}) {
    if (lastPage <= 1) return null;

    const pages = Array.from({ length: lastPage }, (_, i) => i + 1);

    const goToPage = (page: number) => {
        router.get(baseUrl, { page: String(page) }, { preserveScroll: true });
    };

    return (
        <nav
            className="flex items-center justify-center gap-1"
            aria-label="Halaman Tugasan Saya"
        >
            <Button
                variant="secondary"
                size="sm"
                disabled={currentPage === 1}
                onClick={() => goToPage(currentPage - 1)}
                aria-label="Halaman sebelumnya"
            >
                <ChevronLeft aria-hidden className="h-4 w-4" />
            </Button>

            <div className="flex items-center gap-1" role="group" aria-label="Nomor halaman">
                {pages.map((page) => (
                    <Button
                        key={page}
                        variant={page === currentPage ? 'default' : 'secondary'}
                        size="sm"
                        onClick={() => {
                            if (page !== currentPage) {
                                goToPage(page);
                            }
                        }}
                        aria-label={`Halaman ${page}`}
                        aria-current={page === currentPage ? 'page' : undefined}
                    >
                        {page}
                    </Button>
                ))}
            </div>

            <Button
                variant="secondary"
                size="sm"
                disabled={currentPage === lastPage}
                onClick={() => goToPage(currentPage + 1)}
                aria-label="Halaman selanjutnya"
            >
                <ChevronRight aria-hidden className="h-4 w-4" />
            </Button>
        </nav>
    );
}

function AssignmentCard({
    assignment,
    tenant,
}: {
    assignment: Assignment;
    tenant: string;
}) {
    const acceptForm = useForm({});
    const startForm = useForm({});

    const canAccept =
        assignment.time_log === null ||
        assignment.time_log.state === 'not_started';
    const canStart = assignment.time_log?.state === 'accepted';

    return (
        <div className="relative rounded-2xl border border-border bg-card shadow-card p-5">
            <DeadlineRail
                state={assignment.deadline_state}
                label={assignment.deadline_label}
            />

            <div className="flex flex-col gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <CategoryBadge category={assignment.category} />
                    <StatusBadge status={assignment.status} />
                    {assignment.time_log !== null && (
                        <LiveTimer
                            elapsedSeconds={assignment.time_log.elapsed_seconds}
                            state={assignment.time_log.state}
                        />
                    )}
                </div>

                <Link
                    href={showWorkOrder({ tenant, workOrder: assignment.id })}
                    className="text-base font-semibold text-ink leading-snug hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 rounded-sm"
                >
                    {assignment.title}
                </Link>

                <p className="line-clamp-2 text-sm leading-relaxed text-ink-muted">
                    {assignment.description}
                </p>

                <dl className="space-y-1 text-xs text-ink-subtle">
                    <div className="flex items-center gap-1.5">
                        <UserCheck aria-hidden className="size-3.5 shrink-0" />
                        <span>
                            Ditugaskan oleh:{' '}
                            <span className="font-medium text-ink-muted">
                                {assignment.assigner_name ?? '—'}
                            </span>
                        </span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <Building2 aria-hidden className="size-3.5 shrink-0" />
                        <span>
                            Department:{' '}
                            <span className="font-medium text-ink-muted">
                                {assignment.target_department ?? '—'}
                            </span>
                        </span>
                    </div>
                    {assignment.location !== null && (
                        <div className="flex items-center gap-1.5">
                            <MapPin aria-hidden className="size-3.5 shrink-0" />
                            <span>
                                Lokasi:{' '}
                                <span className="font-medium text-ink-muted">
                                    {assignment.location}
                                </span>
                            </span>
                        </div>
                    )}
                    {assignment.deadline_date !== null && (
                        <div className="flex items-center gap-1.5">
                            <CalendarDays
                                aria-hidden
                                className="size-3.5 shrink-0"
                            />
                            <span>
                                Deadline:{' '}
                                <span className="font-mono text-ink-muted">
                                    {formatDate(assignment.deadline_date)}
                                </span>{' '}
                                ·{' '}
                                <span className="font-medium text-ink-muted">
                                    {assignment.deadline_label}
                                </span>
                            </span>
                        </div>
                    )}
                    {assignment.attachments_count > 0 && (
                        <div className="flex items-center gap-1.5">
                            <Camera aria-hidden className="size-3.5 shrink-0" />
                            <span>
                                {assignment.attachments_count} lampiran
                            </span>
                        </div>
                    )}
                </dl>

                <div className="flex flex-wrap items-center gap-2 border-t border-border pt-4">
                    {canAccept && (
                        <Button
                            size="sm"
                            disabled={acceptForm.processing}
                            onClick={() =>
                                acceptForm.post(
                                    acceptTime({
                                        tenant,
                                        workOrder: assignment.id,
                                    }).url,
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Terima Penugasan
                        </Button>
                    )}
                    {canStart && (
                        <Button
                            size="sm"
                            disabled={startForm.processing}
                            onClick={() =>
                                startForm.post(
                                    startTime({
                                        tenant,
                                        workOrder: assignment.id,
                                    }).url,
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Mulai Bekerja
                        </Button>
                    )}
                    <Button size="sm" variant="secondary" asChild>
                        <Link
                            href={showWorkOrder({
                                tenant,
                                workOrder: assignment.id,
                            })}
                        >
                            Buka Detail
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default function MyAssignments({ assignments }: PageProps) {
    const tenant = useRequiredTenant('work-orders.my');
    const baseUrl = `/${tenant}/work-orders/my`;

    return (
        <>
            <Head title="Tugasan Saya" />

            <div className="space-y-6">
                {assignments.data.length === 0 ? (
                    <div className="rounded-2xl border border-border bg-card p-12 text-center">
                        <p className="text-ink-muted">
                            Tidak ada penugasan aktif untuk Anda saat ini.
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="space-y-3">
                            {assignments.data.map((assignment) => (
                                <AssignmentCard
                                    key={assignment.id}
                                    assignment={assignment}
                                    tenant={tenant}
                                />
                            ))}
                        </div>

                        <Pagination
                            currentPage={assignments.current_page}
                            lastPage={assignments.last_page}
                            baseUrl={baseUrl}
                        />
                    </>
                )}
            </div>
        </>
    );
}

MyAssignments.layout = {
    breadcrumbs: [{ title: 'Work Order' }, { title: 'Tugasan Saya' }],
    title: 'Tugasan Saya',
    description:
        'Work Order yang ditugaskan kepada Anda — terima, mulai, dan selesaikan',
};
