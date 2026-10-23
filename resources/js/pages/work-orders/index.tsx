import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Calendar, ChevronLeft, ChevronRight } from 'lucide-react';
import { create, show } from '@/routes/work-orders';
import { CategoryBadge } from '@/components/category-badge';
import { StatusBadge, type WoStatus } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import type { InertiaConfig } from '@inertiajs/core';
import { useRequiredTenant } from '@/lib/tenant';

type DeadlineState = 'on_track' | 'due_soon' | 'overdue' | 'escalated';

type WorkOrder = {
    id: string;
    nomor_wo: string;
    title: string;
    description: string;
    category: 'normal' | 'accident' | 'owner';
    status: WoStatus;
    requested_schedule_date: string | null;
    scheduled_date: string | null;
    deadline_date: string | null;
    created_at: string;
    requester: { id: string; name: string } | null;
    target_department: { id: string; nama_department: string | null } | null;
    deadline_state: DeadlineState;
    deadline_label: string;
};

type PaginatedData = {
    data: WorkOrder[];
    current_page: number;
    last_page: number;
};

type PageProps = {
    workOrders: PaginatedData;
};

/** Primary action of this page, rendered in the shell's heading band. */
function WorkOrderActions() {
    const tenant = useRequiredTenant('work-orders.create');

    return (
        <Button asChild size="lg">
            <Link href={create({ tenant })}>
                <Plus aria-hidden />
                Buat Work Order
            </Link>
        </Button>
    );
}

WorkOrders.layout = {
    breadcrumbs: [{ title: 'Work Order' }],
    title: 'Work Order',
    description:
        'Work Order yang Anda buat dan yang ditujukan ke department Anda',
    actions: WorkOrderActions,
};

function DeadlineRail({ state, label }: { state: DeadlineState; label: string }) {
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

function WorkOrderCard({
    workOrder,
    tenant,
}: {
    workOrder: WorkOrder;
    tenant: string;
}) {
    return (
        <Link
            href={show({ tenant, workOrder: workOrder.id })}
            className="group block relative rounded-2xl border border-border bg-card shadow-card p-5 transition-[border-color,box-shadow] duration-base hover:shadow-raised hover:border-border-strong focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
        >
            <DeadlineRail state={workOrder.deadline_state} label={workOrder.deadline_label} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                <div className="flex flex-col gap-2 flex-1 min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <CategoryBadge category={workOrder.category} />
                        <StatusBadge status={workOrder.status} />
                    </div>

                    <h3 className="text-base font-semibold text-ink leading-snug truncate">
                        {workOrder.title}
                    </h3>

                    <p className="text-sm text-ink-muted line-clamp-2">
                        {workOrder.description}
                    </p>

                    <div className="flex flex-wrap items-center gap-3 text-sm text-ink-muted">
                        <span className="flex items-center gap-1">
                            <Calendar aria-hidden className="h-3.5 w-3.5" />
                            <span className="font-mono">
                                {workOrder.deadline_date
                                    ? new Date(workOrder.deadline_date).toLocaleDateString('id-ID', {
                                          day: 'numeric',
                                          month: 'short',
                                          year: 'numeric',
                                      })
                                    : '—'}
                            </span>
                        </span>
                        <span className="font-medium text-ink-subtle">{workOrder.deadline_label}</span>
                        {workOrder.target_department?.nama_department && (
                            <span className="flex items-center gap-1">
                                <span>•</span>
                                {workOrder.target_department.nama_department}
                            </span>
                        )}
                    </div>
                </div>

                <div className="flex flex-col items-end gap-1 shrink-0 sm:items-end">
                    <span className="font-mono text-xs text-ink-muted tracking-wider">
                        {workOrder.nomor_wo}
                    </span>
                    {workOrder.requester?.name && (
                        <span className="text-xs text-ink-subtle">
                            Dimohon: {workOrder.requester.name}
                        </span>
                    )}
                </div>
            </div>
        </Link>
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
        // Inertia visit (bukan full reload) — state/progress tetap terjaga.
        router.get(baseUrl, { page: String(page) }, { preserveScroll: true });
    };

    return (
        <nav
            className="flex items-center justify-center gap-1"
            aria-label="Halaman Work Order"
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

export default function WorkOrders({ workOrders }: PageProps) {
    const tenant = useRequiredTenant('work-orders.index');
    const baseUrl = `/${tenant}/work-orders`;

    return (
        <>
            <Head title="Work Order" />

            <div className="space-y-6">
                {workOrders.data.length === 0 ? (
                    <div className="rounded-2xl border border-border bg-card p-12 text-center">
                        <p className="text-ink-muted mb-6">
                            Belum ada Work Order.
                        </p>
                        <WorkOrderActions />
                    </div>
                ) : (
                    <div className="space-y-3">
                        {workOrders.data.map((workOrder) => (
                            <WorkOrderCard
                                key={workOrder.id}
                                workOrder={workOrder}
                                tenant={tenant}
                            />
                        ))}
                    </div>
                )}

                <Pagination
                    currentPage={workOrders.current_page}
                    lastPage={workOrders.last_page}
                    baseUrl={baseUrl}
                />
            </div>
        </>
    );
}