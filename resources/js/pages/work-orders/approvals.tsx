import { Head, Link, router, useForm } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import React, { useState } from 'react';
import {
    ChevronLeft,
    ChevronRight,
    TriangleAlert,
} from 'lucide-react';
import { show as showWorkOrder } from '@/routes/work-orders';
import {
    approve as approveRequest,
    reject as rejectRequest,
} from '@/routes/work-orders/request';
import { CategoryBadge } from '@/components/category-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ConfirmDialog } from '@/components/confirm-dialog';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useRequiredTenant } from '@/lib/tenant';

/**
 * Approval inbox (R20, ADR-0011): permintaan WO lintas-department dari
 * department PENGAJU yang menunggu keputusan — terpisah dari antrian WO
 * masuk department tujuan. Keputusan tetap dipaksa server-side di
 * WorkOrderRequestService; UI hanya cermin.
 */
type ApprovalRequest = {
    id: string;
    nomor_wo: string;
    title: string;
    description: string;
    category: 'normal' | 'accident' | 'owner';
    requester_name: string | null;
    target_department: string | null;
    created_at: string;
    /** Nomor WO kandidat duplikat (OQ-4b) — penanda, keputusan tetap manusia. */
    duplicate_numbers: string[];
};

type PaginatedData = {
    data: ApprovalRequest[];
    current_page: number;
    last_page: number;
};

type PageProps = InertiaConfig['sharedPageProps'] & {
    requests: PaginatedData;
};

const formatDate = (value: string): string =>
    new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });

function DuplicateHint({ numbers }: { numbers: string[] }) {
    if (numbers.length === 0) {
        return null;
    }

    return (
        <p className="flex items-start gap-1.5 rounded-md border border-warning/30 bg-warning/10 px-2.5 py-1.5 text-xs text-warning-ink">
            <TriangleAlert aria-hidden className="mt-0.5 size-3.5 shrink-0" />
            <span>
                Kemungkinan duplikat:{' '}
                <span className="font-mono">{numbers.join(', ')}</span> — pastikan
                sebelum menyetujui.
            </span>
        </p>
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
            aria-label="Halaman Approval Permintaan"
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

function ApprovalCard({
    request,
    tenant,
    onReject,
}: {
    request: ApprovalRequest;
    tenant: string;
    onReject: (request: ApprovalRequest) => void;
}) {
    const [confirmingApprove, setConfirmingApprove] = useState(false);

    const approve = () => {
        router.post(
            approveRequest({ tenant, workOrder: request.id }).url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <div className="relative rounded-2xl border border-border bg-card shadow-card p-5">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-2">
                    <CategoryBadge category={request.category} />
                    <span className="font-mono text-xs text-ink-muted tracking-wider">
                        {request.nomor_wo}
                    </span>
                    <span className="ml-auto text-xs tabular-nums text-ink-subtle">
                        {formatDate(request.created_at)}
                    </span>
                </div>

                <Link
                    href={showWorkOrder({ tenant, workOrder: request.id })}
                    className="text-base font-semibold text-ink leading-snug hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 rounded-sm"
                >
                    {request.title}
                </Link>

                <p className="line-clamp-2 text-sm leading-relaxed text-ink-muted">
                    {request.description}
                </p>

                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-subtle">
                    <span>
                        Pemohon:{' '}
                        <span className="font-medium text-ink-muted">
                            {request.requester_name ?? '—'}
                        </span>
                    </span>
                    <span>
                        Ditujukan ke:{' '}
                        <span className="font-medium text-ink-muted">
                            {request.target_department ?? '—'}
                        </span>
                    </span>
                </div>

                <DuplicateHint numbers={request.duplicate_numbers} />

                <div className="flex flex-wrap items-center gap-2 border-t border-border pt-4">
                    <Button size="sm" onClick={() => setConfirmingApprove(true)}>
                        Setujui
                    </Button>
                    <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => onReject(request)}
                    >
                        Tolak
                    </Button>
                    <span className="text-xs text-ink-subtle">
                        Setelah disetujui, permintaan diteruskan ke department
                        tujuan.
                    </span>
                </div>
            </div>

            <ConfirmDialog
                title="Setujui permintaan ini?"
                description={`Permintaan "${request.title}" akan dirilis ke ${request.target_department ?? 'department tujuan'} dan masuk antrian review HOD di sana.`}
                confirmLabel="Setujui"
                open={confirmingApprove}
                onOpenChange={setConfirmingApprove}
                onConfirm={() => {
                    setConfirmingApprove(false);
                    approve();
                }}
            />
        </div>
    );
}

function RejectDialog({
    request,
    onClose,
}: {
    request: ApprovalRequest | null;
    onClose: () => void;
}) {
    const tenant = useRequiredTenant('work-orders.request.reject');
    const form = useForm<{ reason: string }>({ reason: '' });

    const close = () => {
        form.reset();
        onClose();
    };

    return (
        <Dialog
            open={request !== null}
            onOpenChange={(open) => {
                if (!open) {
                    close();
                }
            }}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Tolak permintaan</DialogTitle>
                    <DialogDescription>
                        Alasan wajib diisi — requester menerima penjelasan ini
                        dan dapat merevisi lalu mengajukan ulang.
                    </DialogDescription>
                </DialogHeader>

                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (request === null) {
                            return;
                        }
                        form.post(
                            rejectRequest({
                                tenant,
                                workOrder: request.id,
                            }).url,
                            {
                                preserveScroll: true,
                                onSuccess: close,
                            },
                        );
                    }}
                >
                    <div className="space-y-1.5">
                        <Label htmlFor="reject-reason">Alasan penolakan</Label>
                        <Textarea
                            id="reject-reason"
                            rows={3}
                            required
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.reason)}
                        />
                        {form.errors.reason && (
                            <p className="text-xs text-danger">
                                {form.errors.reason}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={close}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            Tolak Permintaan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function WorkOrderApprovals({ requests }: PageProps) {
    const tenant = useRequiredTenant('work-orders.approvals');
    const [rejectTarget, setRejectTarget] = useState<ApprovalRequest | null>(
        null,
    );
    const baseUrl = `/${tenant}/work-orders/approvals`;

    return (
        <>
            <Head title="Approval Permintaan" />

            <div className="space-y-6">
                {requests.data.length === 0 ? (
                    <div className="rounded-2xl border border-border bg-card p-12 text-center">
                        <p className="text-ink-muted">
                            Tidak ada permintaan yang menunggu persetujuan
                            department Anda.
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="space-y-3">
                            {requests.data.map((request) => (
                                <ApprovalCard
                                    key={request.id}
                                    request={request}
                                    tenant={tenant}
                                    onReject={setRejectTarget}
                                />
                            ))}
                        </div>

                        <Pagination
                            currentPage={requests.current_page}
                            lastPage={requests.last_page}
                            baseUrl={baseUrl}
                        />
                    </>
                )}
            </div>

            <RejectDialog
                request={rejectTarget}
                onClose={() => setRejectTarget(null)}
            />
        </>
    );
}

WorkOrderApprovals.layout = {
    breadcrumbs: [{ title: 'Work Order' }, { title: 'Approval Permintaan' }],
    title: 'Approval Permintaan',
    description:
        'Permintaan Work Order lintas-department dari department Anda yang menunggu keputusan',
};
