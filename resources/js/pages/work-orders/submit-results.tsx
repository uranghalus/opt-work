import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import { submit } from '@/routes/work-orders';
import { show as showAttachment } from '@/routes/work-orders/attachments';
import { WorkflowStepper } from '@/components/workflow/WorkflowStepper';
import {
    StatusBadge,
    statusLabel,
    type WoStatus,
} from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useRequiredTenant } from '@/lib/tenant';
import { Image, Send } from 'lucide-react';

type WorkOrderView = {
    id: string;
    nomor_wo: string;
    title: string;
    status: WoStatus;
    result_notes: string | null;
    result_attachments: string[] | null;
};

type PageProps = {
    workOrder: WorkOrderView;
};

/** Statuses where the executing employee may (re)submit results. */
const SUBMITTABLE_STATUSES: WoStatus[] = [
    'assigned',
    'in_progress',
    'revision',
];

const SUBMIT_STEPS: {
    label: string;
    status: 'pending' | 'active' | 'completed';
}[] = [
    { label: 'Review HOD', status: 'completed' },
    { label: 'Assign Karyawan', status: 'completed' },
    { label: 'Serahkan Hasil', status: 'active' },
    { label: 'Verifikasi Hasil', status: 'pending' },
];

const MAX_ATTACHMENTS = 3;

SubmitResultsPage.layout = {
    breadcrumbs: [{ title: 'Work Order' }, { title: 'Serahkan Hasil' }],
    title: 'Serahkan Hasil',
    description:
        'Serahkan hasil pekerjaan Work Order (catatan hasil + foto bukti).',
};

export default function SubmitResultsPage({
    workOrder,
}: PageProps): React.ReactElement {
    const tenant = useRequiredTenant('work-orders.submit');

    const form = useForm<{
        result_notes: string;
        result_attachments: File[];
    }>({
        result_notes: workOrder.result_notes ?? '',
        result_attachments: [],
    });

    const isSubmittable = SUBMITTABLE_STATUSES.includes(workOrder.status);

    const hasResults =
        workOrder.result_notes !== null ||
        (workOrder.result_attachments?.length ?? 0) > 0;

    const onAttachmentsChange = (
        event: React.ChangeEvent<HTMLInputElement>,
    ): void => {
        const files = Array.from(event.target.files ?? []);
        if (files.length > MAX_ATTACHMENTS) {
            form.setError(
                'result_attachments',
                'Maksimal 3 foto bukti. Hapus berkas terlebih dahulu.',
            );
            return;
        }
        form.clearErrors('result_attachments');
        form.setData('result_attachments', files);
    };

    const submitResults = (
        event: React.FormEvent<HTMLFormElement>,
    ): void => {
        event.preventDefault();

        if (form.data.result_attachments.length > MAX_ATTACHMENTS) {
            form.setError(
                'result_attachments',
                'Maksimal 3 foto bukti. Hapus berkas terlebih dahulu.',
            );
            return;
        }

        form.post(
            submit({ tenant, workOrder: workOrder.id }).url,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => form.reset(),
            },
        );
    };

    return (
        <>
            <Head title={`Submit Results — ${workOrder.nomor_wo}`} />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge status={workOrder.status} />
                </div>

                <p className="text-sm text-ink-muted">
                    {statusLabel(workOrder.status)} — {workOrder.title}
                </p>

                <WorkflowStepper currentStep={3} steps={SUBMIT_STEPS} />

                {/* ================= submittable: result submission form ================= */}
                {isSubmittable && (
                    <section
                        className="rounded-xl border border-border bg-card p-6 space-y-6"
                    >
                        <div className="flex items-center gap-2">
                            <Send aria-hidden className="size-4 text-info" />
                            <h3 className="text-base font-semibold tracking-tight">
                                Serahkan Hasil Pekerjaan
                            </h3>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Catatan hasil wajib diisi; foto bukti maksimal 3
                            file (jpg/jpeg/png/webp, masing-masing maks 2MB).
                            WO akan menunggu verifikasi HOD.
                        </p>

                        <form onSubmit={submitResults} className="space-y-5">
                            <div className="space-y-1.5">
                                <Label htmlFor="result_notes">Catatan Hasil</Label>
                                <Textarea
                                    id="result_notes"
                                    rows={3}
                                    value={form.data.result_notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'result_notes',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={
                                        Boolean(form.errors.result_notes)
                                    }
                                />
                                {form.errors.result_notes && (
                                    <p className="text-xs text-danger">
                                        {form.errors.result_notes}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="result_attachments">
                                    Foto Bukti (opsional, maks 3)
                                </Label>
                                <input
                                    id="result_attachments"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                    onChange={onAttachmentsChange}
                                    className="block w-full text-sm file:mr-3 file:rounded-sm file:border file:border-border file:bg-surface-raised file:px-3 file:py-1.5 file:text-sm file:font-medium"
                                />
                                {form.errors.result_attachments && (
                                    <p className="text-xs text-danger">
                                        {form.errors.result_attachments}
                                    </p>
                                )}
                            </div>

                            <p className="text-sm text-ink-muted">
                                Berkas yang dipilih:{' '}
                                <span className="text-ink font-medium">
                                    {form.data.result_attachments.length}
                                </span>
                            </p>

                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Menyimpan…'
                                    : 'Serahkan Hasil'}
                            </Button>
                        </form>
                    </section>
                )}

                {/* ================= non-submittable: read-only results or empty state ================= */}
                {!isSubmittable && (
                    <section
                        className="rounded-xl border border-border bg-card p-6 space-y-6"
                    >
                        <div className="flex items-center gap-2">
                            <Image aria-hidden className="size-4 text-info" />
                            <h3 className="text-base font-semibold tracking-tight">
                                Hasil Pekerjaan
                            </h3>
                        </div>

                        {hasResults ? (
                            <>
                                {workOrder.result_notes !== null && (
                                    <div className="space-y-1">
                                        <p className="text-sm text-ink-muted">
                                            Catatan Hasil
                                        </p>
                                        <p className="text-sm whitespace-pre-wrap text-ink">
                                            {workOrder.result_notes}
                                        </p>
                                    </div>
                                )}

                                {(workOrder.result_attachments?.length ?? 0) >
                                    0 && (
                                    <div className="space-y-1">
                                        <p className="text-sm text-ink-muted">
                                            Foto Bukti
                                        </p>
                                        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                            {(workOrder.result_attachments ?? []).map(
                                                (path, index) => (
                                                    <li key={path}>
                                                        <a
                                                            href={showAttachment.url({
                                                                tenant,
                                                                workOrder: workOrder.id,
                                                                index,
                                                                type: 'result',
                                                            })}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="block"
                                                        >
                                                            <img
                                                                src={
                                                                    showAttachment.url({
                                                                        tenant,
                                                                        workOrder: workOrder.id,
                                                                        index,
                                                                        type: 'result',
                                                                    })
                                                                }
                                                                alt={`Foto hasil ${index + 1} WO ${workOrder.nomor_wo}`}
                                                                loading="lazy"
                                                                className="aspect-video w-full rounded-lg border bg-surface-raised object-cover"
                                                            />
                                                        </a>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                            </>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-8 text-center">
                                <p className="text-sm text-ink-muted">
                                    Belum ada hasil pekerjaan yang diserahkan
                                    untuk Work Order ini.
                                </p>
                            </div>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}
