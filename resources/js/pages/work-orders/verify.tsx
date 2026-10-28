import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import { verifySubmit } from '@/routes/work-orders';
import { WorkflowStepper } from '@/components/workflow/WorkflowStepper';
import {
    StatusBadge,
    statusLabel,
    type WoStatus,
} from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { useRequiredTenant } from '@/lib/tenant';
import { CheckCircle2, Eye, RotateCw, CircleX } from 'lucide-react';

type VerificationResult = 'pass' | 'needs_revision' | 'fail';

type WorkOrderView = {
    id: string;
    nomor_wo: string;
    title: string;
    status: WoStatus;
    hod_notes: string | null;
    result_notes: string | null;
};

type PageProps = {
    workOrder: WorkOrderView;
};

const VERIFY_OPTIONS: {
    value: VerificationResult;
    label: string;
    description: string;
}[] = [
    {
        value: 'pass',
        label: 'Lulus — setujui hasil dan tutup WO',
        description: 'Setujui hasil dan tutup Work Order.',
    },
    {
        value: 'needs_revision',
        label: 'Perlu Revisi — kembalikan ke pelaksanaan',
        description: 'Kembalikan hasil ke pelaksanaan untuk diedit ulang.',
    },
    {
        value: 'fail',
        label: 'Gagal — tolak hasil pekerjaan',
        description: 'Tolak hasil pekerjaan Work Order.',
    },
];

const VERIFY_RESULT_ICON: Record<VerificationResult, React.ElementType> = {
    pass: CheckCircle2,
    needs_revision: RotateCw,
    fail: CircleX,
};

const VERIFY_RESULT_TONE: Record<VerificationResult, string> = {
    pass: 'bg-emerald-600 text-white hover:bg-emerald-700',
    needs_revision: 'bg-amber-600 text-white hover:bg-amber-700',
    fail: '',
};

const VERIFY_STEPS: {
    label: string;
    status: 'pending' | 'active' | 'completed';
}[] = [
    { label: 'Review HOD', status: 'completed' },
    { label: 'Assign Karyawan', status: 'completed' },
    { label: 'Serahkan Hasil', status: 'completed' },
    { label: 'Verifikasi Hasil', status: 'active' },
];

const VERIFIED_STATUSES: WoStatus[] = ['closed', 'revision'];

const step4Status = (status: WoStatus): 'pending' | 'active' | 'completed' => {
    if (status === 'pending_verify') return 'active';
    if (VERIFIED_STATUSES.includes(status)) return 'completed';
    return 'pending';
};

const submitLabel = (result: VerificationResult): string => {
    switch (result) {
        case 'pass':
            return 'Setujui & Tutup WO';
        case 'needs_revision':
            return 'Kembalikan untuk Revisi';
        case 'fail':
            return 'Tolak Hasil';
    }
};

VerifyPage.layout = {
    breadcrumbs: [{ title: 'Work Order' }, { title: 'Verifikasi' }],
    title: 'Verifikasi Hasil',
    description:
        'Keputusan verifikasi HOD terhadap hasil pekerjaan Work Order (lulus / revisi / gagal).',
};

export default function VerifyPage({
    workOrder,
}: PageProps): React.ReactElement {
    const tenant = useRequiredTenant('work-orders.verify-submit');

    const steps = [
        { ...VERIFY_STEPS[0] },
        { ...VERIFY_STEPS[1] },
        { ...VERIFY_STEPS[2] },
        { ...VERIFY_STEPS[3], status: step4Status(workOrder.status) },
    ];

    const verifyForm = useForm<{
        result: VerificationResult;
        notes: string;
    }>({
        result: 'pass',
        notes: '',
    });

    const needsNotes = verifyForm.data.result !== 'pass';

    const submitVerify = (
        event: React.FormEvent<HTMLFormElement>,
    ): void => {
        event.preventDefault();

        if (needsNotes && !verifyForm.data.notes.trim()) {
            verifyForm.setError(
                'notes',
                'Catatan verifikasi wajib diisi untuk hasil ini.',
            );
            return;
        }

        verifyForm.post(
            verifySubmit({ tenant, workOrder: workOrder.id }).url,
            {
                preserveScroll: true,
                onSuccess: () => verifyForm.reset(),
            },
        );
    };

    const resultIcon = VERIFY_RESULT_ICON[verifyForm.data.result];

    return (
        <>
            <Head title={`Verify — ${workOrder.nomor_wo}`} />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge status={workOrder.status} />
                </div>

                <p className="text-sm text-ink-muted">
                    {statusLabel(workOrder.status)} — {workOrder.title}
                </p>

                <WorkflowStepper currentStep={4} steps={steps} />

                {/* ================= pending_verify: verification decision form ================= */}
                {workOrder.status === 'pending_verify' && (
                    <section
                        className="rounded-xl border border-border bg-card p-6 space-y-6"
                    >
                        <div className="flex items-center gap-2">
                            <Eye aria-hidden className="size-4 text-info" />
                            <h3 className="text-base font-semibold tracking-tight">
                                Keputusan Verifikasi HOD
                            </h3>
                        </div>
                        <p className="text-sm text-ink-muted">
                            Tentukan keputusan atas hasil pekerjaan yang telah
                            diserahkan pelaksanaan.
                        </p>

                        <form onSubmit={submitVerify} className="space-y-5">
                            <fieldset className="space-y-2">
                                <legend className="text-sm font-semibold">
                                    Keputusan Verifikasi
                                </legend>
                                <RadioGroup
                                    value={verifyForm.data.result}
                                    onValueChange={(value) =>
                                        verifyForm.setData(
                                            'result',
                                            value as VerificationResult,
                                        )
                                    }
                                    name="verification_result"
                                    className="grid gap-3"
                                >
                                    {VERIFY_OPTIONS.map((option) => {
                                        const Icon =
                                            VERIFY_RESULT_ICON[option.value];
                                        const isChecked =
                                            verifyForm.data.result ===
                                            option.value;
                                        return (
                                            <RadioGroupItem
                                                key={option.value}
                                                value={option.value}
                                                className={cn(
                                                    isChecked
                                                        ? 'border-brand bg-brand-subtle shadow-raised'
                                                        : '',
                                                )}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <Icon
                                                        aria-hidden
                                                        className={`size-4 ${
                                                            isChecked
                                                                ? 'text-brand'
                                                                : 'text-ink-muted'
                                                        }`}
                                                    />
                                                    <div>
                                                        <div className="font-medium text-ink">
                                                            {option.label}
                                                        </div>
                                                        <div className="text-xs text-ink-muted">
                                                            {option.description}
                                                        </div>
                                                    </div>
                                                </div>
                                            </RadioGroupItem>
                                        );
                                    })}
                                </RadioGroup>
                                {verifyForm.errors.result && (
                                    <p className="text-xs text-danger">
                                        {verifyForm.errors.result}
                                    </p>
                                )}
                            </fieldset>

                            <div className="space-y-1.5">
                                <Label htmlFor="verification_notes">
                                    Catatan Verifikasi
                                </Label>
                                <Textarea
                                    id="verification_notes"
                                    rows={3}
                                    value={verifyForm.data.notes}
                                    onChange={(event) =>
                                        verifyForm.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={
                                        Boolean(verifyForm.errors.notes) &&
                                        needsNotes
                                    }
                                    placeholder={
                                        needsNotes
                                            ? 'Wajib diisi untuk revisi atau gagal'
                                            : 'Opsional'
                                    }
                                />
                                {verifyForm.errors.notes && (
                                    <p className="text-xs text-danger">
                                        {verifyForm.errors.notes}
                                    </p>
                                )}
                                <p className="text-xs text-ink-muted">
                                    {needsNotes
                                        ? 'Wajib untuk hasil revisi atau gagal.'
                                        : 'Opsional untuk keputusan lulus.'}
                                </p>
                            </div>

                            <Button
                                type="submit"
                                disabled={verifyForm.processing}
                                variant={
                                    verifyForm.data.result === 'fail'
                                        ? 'destructive'
                                        : 'default'
                                }
                                className={VERIFY_RESULT_TONE[
                                    verifyForm.data.result
                                ]}
                            >
                                {verifyForm.processing
                                    ? 'Memproses…'
                                    : submitLabel(verifyForm.data.result)}
                            </Button>
                        </form>
                    </section>
                )}

                {/* ================= closed: read-only verified result ================= */}
                {workOrder.status === 'closed' && (
                    <section
                        className="rounded-xl border border-border bg-card p-6 space-y-6"
                    >
                        <div className="flex items-center gap-2">
                            <CheckCircle2
                                aria-hidden
                                className="size-4 text-success"
                            />
                            <h3 className="text-base font-semibold tracking-tight">
                                Hasil Diverifikasi — Lulus
                            </h3>
                        </div>
                        <p className="text-sm text-ink-muted">
                            Hasil pekerjaan telah diverifikasi HOD dan Work
                            Order ditutup (selesai).
                        </p>
                        {workOrder.result_notes && (
                            <div className="space-y-1">
                                <p className="text-sm text-ink-muted">
                                    Hasil Pekerjaan
                                </p>
                                <p className="text-sm whitespace-pre-wrap text-ink">
                                    {workOrder.result_notes}
                                </p>
                            </div>
                        )}
                        {workOrder.hod_notes && (
                            <div className="space-y-1">
                                <p className="text-sm text-ink-muted">
                                    Catatan Verifikasi
                                </p>
                                <p className="text-sm whitespace-pre-wrap text-ink">
                                    {workOrder.hod_notes}
                                </p>
                            </div>
                        )}
                    </section>
                )}

                {/* ================= revision: read-only rejected result ================= */}
                {workOrder.status === 'revision' && (
                    <section
                        className="rounded-xl border border-border bg-card p-6 space-y-6"
                    >
                        <div className="flex items-center gap-2">
                            <RotateCw
                                aria-hidden
                                className="size-4 text-warning"
                            />
                            <h3 className="text-base font-semibold tracking-tight">
                                Hasil Dikembalikan untuk Revisi
                            </h3>
                        </div>
                        <p className="text-sm text-ink-muted">
                            HOD menolak hasil — Work Order dikembalikan ke
                            pelaksanaan untuk diedit ulang. Deadline tidak
                            bergeser.
                        </p>
                        {workOrder.hod_notes && (
                            <div className="space-y-1">
                                <p className="text-sm text-ink-muted">
                                    Catatan Revisi
                                </p>
                                <p className="text-sm whitespace-pre-wrap text-ink">
                                    {workOrder.hod_notes}
                                </p>
                            </div>
                        )}
                    </section>
                )}

                {/* ================= other statuses: empty state ================= */}
                {!['pending_verify', 'closed', 'revision'].includes(
                    workOrder.status,
                ) && (
                    <section
                        className="rounded-xl border border-border bg-card p-6 space-y-6"
                    >
                        <div className="flex flex-col items-center justify-center py-8 text-center">
                            <Eye aria-hidden className="size-6 text-ink-muted mx-auto mb-2" />
                            <h3 className="text-base font-semibold tracking-tight">
                                Belum Ada Verifikasi
                            </h3>
                            <p className="text-sm text-ink-muted">
                                Hasil pekerjaan belum diverifikasi HOD.
                                Verifikasi dilakukan setelah pelaksanaan
                                menyerahkan hasil (Work Order status{' '}
                                <span className="font-medium">
                                    Menunggu Verifikasi
                                </span>
                                ).
                            </p>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}
