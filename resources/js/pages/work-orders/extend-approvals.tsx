import { Head, Link } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import { CalendarClock, Hourglass } from 'lucide-react';
import { show as showWorkOrder } from '@/routes/work-orders';
import { Button } from '@/components/ui/button';
import { useRequiredTenant } from '@/lib/tenant';

/**
 * R21: inbox "Persetujuan Extend" — dua antrean untuk rantai approver:
 * extend deadline WO telat (TL → HOD, FR-2.3) dan Extend Work Schedule
 * (DGM/GM, FR-2.4). Keputusan dipaksa server-side di ExtendRequestService /
 * WorkPlanningService; halaman ini hanya meneruskan ke panel keputusan di
 * detail WO.
 */
type DeadlineExtend = {
    id: string;
    work_order_id: string;
    nomor_wo: string | null;
    title: string | null;
    days: number;
    reason: string;
    stage: 'pending_team_leader' | 'pending_hod';
    requester_name: string | null;
    created_at: string;
};

type ScheduleExtend = {
    work_order_id: string;
    nomor_wo: string | null;
    title: string | null;
    tgl_jadwal: string | null;
    proposed_tgl_jadwal: string | null;
    extend_reason: string | null;
    requester_name: string | null;
};

type PageProps = InertiaConfig['sharedPageProps'] & {
    deadline_extends: DeadlineExtend[];
    schedule_extends: ScheduleExtend[];
    can_decide_schedule: boolean;
};

const formatDate = (value: string): string =>
    new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });

const stageLabel: Record<DeadlineExtend['stage'], string> = {
    pending_team_leader: 'Menunggu Team Leader',
    pending_hod: 'Menunggu HOD',
};

function EmptySection({ message }: { message: string }) {
    return (
        <div className="rounded-2xl border border-dashed border-border bg-surface-raised p-6 text-center">
            <p className="text-sm text-ink-muted">{message}</p>
        </div>
    );
}

export default function ExtendApprovals({
    deadline_extends,
    schedule_extends,
    can_decide_schedule,
}: PageProps) {
    const tenant = useRequiredTenant('work-orders.extend-approvals');

    return (
        <>
            <Head title="Persetujuan Extend" />

            <div className="space-y-8">
                <section className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Hourglass
                            aria-hidden
                            className="size-4 text-warning"
                        />
                        <h2 className="text-base font-semibold tracking-tight">
                            Extend Deadline (WO telat)
                        </h2>
                    </div>

                    {deadline_extends.length === 0 ? (
                        <EmptySection message="Tidak ada pengajuan extend deadline yang menunggu keputusan Anda." />
                    ) : (
                        <div className="space-y-3">
                            {deadline_extends.map((request) => (
                                <div
                                    key={request.id}
                                    className="rounded-2xl border border-border bg-card shadow-card p-5"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="inline-flex items-center gap-1.5 rounded-sm border border-warning/30 bg-warning/10 px-2 py-0.5 text-xs font-semibold text-warning-ink">
                                            <Hourglass
                                                aria-hidden
                                                className="size-3.5"
                                            />
                                            {stageLabel[request.stage]}
                                        </span>
                                        <span className="font-mono text-xs text-ink-muted tracking-wider">
                                            {request.nomor_wo ?? '—'}
                                        </span>
                                    </div>

                                    <Link
                                        href={showWorkOrder({
                                            tenant,
                                            workOrder: request.work_order_id,
                                        })}
                                        className="mt-2 block text-base font-semibold text-ink leading-snug hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 rounded-sm"
                                    >
                                        {request.title ?? '—'}
                                    </Link>

                                    <p className="mt-2 text-sm text-ink-muted">
                                        Pengajuan{' '}
                                        <span className="font-semibold text-ink">
                                            +{request.days} hari kerja
                                        </span>{' '}
                                        oleh {request.requester_name ?? '—'} —{' '}
                                        {request.reason}
                                    </p>

                                    <div className="mt-4 border-t border-border pt-4">
                                        <Button size="sm" variant="secondary" asChild>
                                            <Link
                                                href={showWorkOrder({
                                                    tenant,
                                                    workOrder: request.work_order_id,
                                                })}
                                            >
                                                Buka Detail untuk Memutuskan
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                {can_decide_schedule && (
                    <section className="space-y-3">
                        <div className="flex items-center gap-2">
                            <CalendarClock
                                aria-hidden
                                className="size-4 text-info"
                            />
                            <h2 className="text-base font-semibold tracking-tight">
                                Extend Jadwal (DGM/GM)
                            </h2>
                        </div>

                        {schedule_extends.length === 0 ? (
                            <EmptySection message="Tidak ada pengajuan extend jadwal yang menunggu approval DGM/GM." />
                        ) : (
                            <div className="space-y-3">
                                {schedule_extends.map((planning) => (
                                    <div
                                        key={planning.work_order_id}
                                        className="rounded-2xl border border-border bg-card shadow-card p-5"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono text-xs text-ink-muted tracking-wider">
                                                {planning.nomor_wo ?? '—'}
                                            </span>
                                        </div>

                                        <Link
                                            href={showWorkOrder({
                                                tenant,
                                                workOrder: planning.work_order_id,
                                            })}
                                            className="mt-2 block text-base font-semibold text-ink leading-snug hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 rounded-sm"
                                        >
                                            {planning.title ?? '—'}
                                        </Link>

                                        <p className="mt-2 flex flex-wrap items-center gap-1.5 text-sm text-ink-muted">
                                            <span className="font-mono">
                                                {planning.tgl_jadwal ?? '-'}
                                            </span>
                                            <span aria-hidden>→</span>
                                            <span className="font-mono font-semibold text-ink">
                                                {planning.proposed_tgl_jadwal ?? '-'}
                                            </span>
                                            <span className="text-ink-subtle">
                                                diajukan oleh{' '}
                                                {planning.requester_name ?? '—'}
                                            </span>
                                        </p>

                                        {planning.extend_reason !== null && (
                                            <p className="mt-1 text-sm text-ink-muted">
                                                {planning.extend_reason}
                                            </p>
                                        )}

                                        <div className="mt-4 border-t border-border pt-4">
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                asChild
                                            >
                                                <Link
                                                    href={showWorkOrder({
                                                        tenant,
                                                        workOrder: planning.work_order_id,
                                                    })}
                                                >
                                                    Buka Detail untuk Memutuskan
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

ExtendApprovals.layout = {
    breadcrumbs: [{ title: 'Work Order' }, { title: 'Persetujuan Extend' }],
    title: 'Persetujuan Extend',
    description:
        'Pengajuan extend deadline dan extend jadwal yang menunggu keputusan Anda',
};
