import { Head, usePage } from '@inertiajs/react';
import { show as showAttachment } from '@/routes/work-orders/attachments';
import { CategoryBadge } from '@/components/category-badge';
import { StatusBadge, type WoStatus } from '@/components/status-badge';
import { Separator } from '@/components/ui/separator';
import type { InertiaConfig } from '@inertiajs/core';

type WorkOrder = {
    id: string;
    nomor_wo: string;
    title: string;
    description: string;
    category: 'normal' | 'accident' | 'owner';
    status: WoStatus;
    requested_schedule_date: string | null;
    attachments: string[] | null;
    requester: { name: string } | null;
    target_department: { nama_department: string | null } | null;
    created_at: string;
};

/**
 * Detail Work Order (FR-1.x). Read-only: execution actions live behind the
 * review/assign flow, which is not built yet.
 *
 * Visibility is scoped by branch, not by user — any authenticated user in the
 * owning branch may read the work order. See `.scratch/tenancy-reconfig/issues/01`.
 */
export default function ShowWorkOrder({ workOrder }: { workOrder: WorkOrder }) {
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;
    const tenant = activeTenant ?? '';

    const scheduledFor = workOrder.requested_schedule_date
        ? new Date(workOrder.requested_schedule_date).toLocaleDateString('id-ID', {
              day: '2-digit',
              month: 'long',
              year: 'numeric',
          })
        : null;

    return (
        <>
            <Head title={workOrder.nomor_wo} />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">Dimohon oleh {workOrder.requester?.name ?? '—'}</p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <CategoryBadge category={workOrder.category} />
                    <StatusBadge status={workOrder.status} />
                </div>

                <dl className="overflow-hidden rounded-lg border bg-card">
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Nomor Work Order
                        </dt>
                        <dd className="font-mono text-sm">
                            {workOrder.nomor_wo}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Department Tujuan
                        </dt>
                        <dd className="text-sm font-semibold">
                            {workOrder.target_department?.nama_department ?? '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Jadwal Diminta
                        </dt>
                        <dd className="text-sm tabular-nums">
                            {scheduledFor ?? '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Dibuat
                        </dt>
                        <dd className="text-sm tabular-nums">
                            {new Date(workOrder.created_at).toLocaleDateString('id-ID', {
                                day: '2-digit',
                                month: 'long',
                                year: 'numeric',
                            })}
                        </dd>
                    </div>
                </dl>

                <section className="space-y-2">
                    <h3 className="text-base font-semibold tracking-tight">
                        Detail Permintaan
                    </h3>
                    <Separator />
                    <p className="text-sm leading-relaxed whitespace-pre-line text-ink-muted">
                        {workOrder.description}
                    </p>
                </section>

                {workOrder.attachments && workOrder.attachments.length > 0 && (
                    <section className="space-y-2">
                        <h3 className="text-base font-semibold tracking-tight">
                            Lampiran ({workOrder.attachments.length})
                        </h3>
                        <Separator />
                        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {workOrder.attachments.map((path, index) => (
                                <li key={path}>
                                    <img
                                        src={showAttachment.url({
                                            tenant,
                                            workOrder: workOrder.id,
                                            index,
                                        })}
                                        alt={`Lampiran ${index + 1} Work Order ${workOrder.nomor_wo}`}
                                        loading="lazy"
                                        className="aspect-video w-full rounded-lg border bg-surface-raised object-cover"
                                    />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

ShowWorkOrder.layout = {
    breadcrumbs: [{ title: 'Work Order' }, { title: '' }],
    title: (workOrder: WorkOrder) => workOrder.title,
    description: (workOrder: WorkOrder) => `Dimohon oleh ${workOrder.requester?.name ?? '—'}`,
};
