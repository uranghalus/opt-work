import { Head, usePage, useForm } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import { ClipboardCheck, History, Send, ShieldCheck, UserCheck } from 'lucide-react';
import { show as showAttachment } from '@/routes/work-orders/attachments';
import { assign, reject, review, submit, verify } from '@/routes/work-orders';
import { CategoryBadge } from '@/components/category-badge';
import {
    StatusBadge,
    statusLabel,
    type WoStatus,
} from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { useRequiredTenant } from '@/lib/tenant';

type WorkOrder = {
    id: string;
    nomor_wo: string;
    title: string;
    description: string;
    category: 'normal' | 'accident' | 'owner';
    status: WoStatus;
    requested_schedule_date: string | null;
    hod_action: 'execute_immediately' | 'schedule' | null;
    scheduled_date: string | null;
    assigned_at: string | null;
    hod_notes: string | null;
    assigned_employees: string[] | null;
    assignment_notes: string | null;
    deadline_date: string | null;
    attachments: string[] | null;
    result_notes: string | null;
    result_attachments: string[] | null;
    requester: { name: string } | null;
    target_department: { nama_department: string | null } | null;
    created_at: string;
};

type EmployeeOption = { id: string; nama_employee: string };

/** One row of the WO audit trail (FR-1.9), newest first. */
type WoEvent = {
    id: string;
    action: string;
    from_status: string | null;
    to_status: string | null;
    meta: Record<string, unknown> | null;
    user_name: string | null;
    created_at: string;
};

type PageProps = InertiaConfig['sharedPageProps'] & {
    workOrder: WorkOrder;
    employees: EmployeeOption[];
    assignees: EmployeeOption[];
    events: WoEvent[];
};

/** Statuses where the HOD review decision is still open. */
const REVIEWABLE: WoStatus[] = ['waiting_hod', 'hod_approved', 'scheduled'];

/** Statuses where personnel can be (re-)assigned. */
const ASSIGNABLE: WoStatus[] = ['hod_approved', 'scheduled', 'assigned'];

/** Statuses where the pelaksana may submit execution results (FR-1.7). */
const SUBMITTABLE: WoStatus[] = ['assigned', 'revision'];

/** Statuses where the HOD may verify the submitted result (FR-1.8). */
const VERIFYABLE: WoStatus[] = ['pending_verify'];

/** Human wording per recorded WorkOrderEvent action (FR-1.9). */
const actionLabel: Record<string, string> = {
    created: 'Work Order dibuat',
    reviewed: 'Keputusan review HOD',
    assigned: 'Penugasan pelaksana',
    submitted: 'Hasil diserahkan',
    verified: 'Hasil terverifikasi — WO ditutup',
    rejected: 'Hasil dikembalikan untuk revisi',
};

const formatDate = (value: string): string =>
    new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });

const formatDateTime = (value: string): string =>
    new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

/**
 * Detail Work Order (FR-1.x). Execution actions live in the HOD panel:
 * the review decision (immediate vs scheduled — Urgent by Accident has no
 * schedule option) and personnel assignment, both hidden without the
 * matching hak-akses verb. The server remains authoritative.
 */
export default function ShowWorkOrder(props: PageProps) {
    const { workOrder, employees, assignees, events } = props;
    const { auth } = usePage<PageProps>().props;
    const tenant = useRequiredTenant('work-orders.show');

    const canReview = auth.can?.['work-order.review'] ?? false;
    const canAssign = auth.can?.['work-order.assign'] ?? false;
    const canSubmit = auth.can?.['work-order.submit'] ?? false;
    const canVerify = auth.can?.['work-order.verify'] ?? false;
    const isAccident = workOrder.category === 'accident';
    const reviewable = REVIEWABLE.includes(workOrder.status);
    const assignable = ASSIGNABLE.includes(workOrder.status);
    const submittable = SUBMITTABLE.includes(workOrder.status);
    const verifyable = VERIFYABLE.includes(workOrder.status);

    return (
        <>
            <Head title={workOrder.nomor_wo} />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        Dimohon oleh {workOrder.requester?.name ?? '—'}
                    </p>
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
                            {workOrder.target_department?.nama_department ??
                                '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Jadwal Diminta
                        </dt>
                        <dd className="text-sm tabular-nums">
                            {workOrder.requested_schedule_date
                                ? formatDate(workOrder.requested_schedule_date)
                                : '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Dibuat
                        </dt>
                        <dd className="text-sm tabular-nums">
                            {formatDate(workOrder.created_at)}
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

                {(workOrder.hod_action !== null ||
                    workOrder.assigned_at !== null) && (
                    <section className="space-y-2">
                        <h3 className="text-base font-semibold tracking-tight">
                            Keputusan HOD
                        </h3>
                        <Separator />
                        <dl className="overflow-hidden rounded-lg border bg-card">
                            <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                                <dt className="text-sm text-muted-foreground">
                                    Keputusan Eksekusi
                                </dt>
                                <dd className="text-sm font-semibold">
                                    {workOrder.hod_action === 'schedule'
                                        ? 'Dijadwalkan'
                                        : 'Eksekusi Langsung'}
                                </dd>
                            </div>
                            {workOrder.scheduled_date && (
                                <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                                    <dt className="text-sm text-muted-foreground">
                                        Tanggal Jadwal
                                    </dt>
                                    <dd className="text-sm tabular-nums">
                                        {formatDate(workOrder.scheduled_date)}
                                    </dd>
                                </div>
                            )}
                            {workOrder.assigned_at && (
                                <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                                    <dt className="text-sm text-muted-foreground">
                                        Tanggal Assign
                                    </dt>
                                    <dd className="text-sm tabular-nums">
                                        {formatDate(workOrder.assigned_at)}
                                    </dd>
                                </div>
                            )}
                            {workOrder.deadline_date && (
                                <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                                    <dt className="text-sm text-muted-foreground">
                                        Deadline
                                    </dt>
                                    <dd className="text-sm font-semibold tabular-nums">
                                        {formatDate(workOrder.deadline_date)}
                                    </dd>
                                </div>
                            )}
                            {workOrder.assignment_notes && (
                                <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                                    <dt className="text-sm text-muted-foreground">
                                        Catatan Penugasan
                                    </dt>
                                    <dd className="text-sm">
                                        {workOrder.assignment_notes}
                                    </dd>
                                </div>
                            )}
                            {assignees.length > 0 && (
                                <div className="flex items-center justify-between gap-4 px-4 py-3">
                                    <dt className="text-sm text-muted-foreground">
                                        Karyawan Pelaksana
                                    </dt>
                                    <dd className="text-sm">
                                        {assignees
                                            .map((a) => a.nama_employee)
                                            .join(', ')}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </section>
                )}

                {(workOrder.result_notes !== null ||
                    (workOrder.result_attachments?.length ?? 0) > 0) && (
                    <section className="space-y-2">
                        <h3 className="text-base font-semibold tracking-tight">
                            Hasil Pekerjaan
                        </h3>
                        <Separator />
                        {workOrder.result_notes !== null && (
                            <p className="text-sm leading-relaxed whitespace-pre-line text-ink-muted">
                                {workOrder.result_notes}
                            </p>
                        )}
                        {(workOrder.result_attachments?.length ?? 0) > 0 && (
                            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {workOrder.result_attachments?.map(
                                    (path, index) => (
                                        <li key={path}>
                                            <img
                                                src={showAttachment.url({
                                                    tenant,
                                                    workOrder: workOrder.id,
                                                    index,
                                                    type: 'result',
                                                })}
                                                alt={`Foto hasil ${index + 1} Work Order ${workOrder.nomor_wo}`}
                                                loading="lazy"
                                                className="aspect-video w-full rounded-lg border bg-surface-raised object-cover"
                                            />
                                        </li>
                                    ),
                                )}
                            </ul>
                        )}
                    </section>
                )}

                {canReview && reviewable && (
                    <ReviewPanel
                        workOrderId={workOrder.id}
                        isAccident={isAccident}
                    />
                )}

                {canAssign && assignable && (
                    <AssignPanel
                        workOrderId={workOrder.id}
                        employees={employees}
                        isRevision={workOrder.status === 'assigned'}
                    />
                )}

                {canSubmit && submittable && (
                    <SubmitPanel workOrderId={workOrder.id} />
                )}

                {canVerify && verifyable && (
                    <VerifyPanel workOrderId={workOrder.id} />
                )}

                {events.length > 0 && (
                    <section className="space-y-2">
                        <div className="flex items-center gap-2">
                            <History aria-hidden className="size-4 text-ink-muted" />
                            <h3 className="text-base font-semibold tracking-tight">
                                Riwayat
                            </h3>
                        </div>
                        <Separator />
                        <ol className="space-y-3">
                            {events.map((event) => (
                                <li
                                    key={event.id}
                                    className="flex items-start justify-between gap-4"
                                >
                                    <div className="space-y-0.5">
                                        <p className="text-sm font-medium">
                                            {actionLabel[event.action] ??
                                                event.action}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {event.user_name ?? 'Sistem'}
                                            {event.from_status !== null &&
                                                event.to_status !== null && (
                                                    <>
                                                        {' — '}
                                                        {statusLabel(
                                                            event.from_status as WoStatus,
                                                        )}
                                                        {' → '}
                                                        {statusLabel(
                                                            event.to_status as WoStatus,
                                                        )}
                                                    </>
                                                )}
                                            {typeof event.meta?.notes ===
                                                'string' && (
                                                <>
                                                    {' — '}
                                                    {event.meta.notes as string}
                                                </>
                                            )}
                                        </p>
                                    </div>
                                    <time className="text-xs tabular-nums whitespace-nowrap text-muted-foreground">
                                        {formatDateTime(event.created_at)}
                                    </time>
                                </li>
                            ))}
                        </ol>
                    </section>
                )}
            </div>
        </>
    );
}

/**
 * HOD review decision (wo-slice-1 ticket 02). Urgent by Accident hides the
 * schedule option entirely — the server guard is the source of truth, this
 * is only the mirror.
 */
function ReviewPanel({
    workOrderId,
    isAccident,
}: {
    workOrderId: string;
    isAccident: boolean;
}) {
    const tenant = useRequiredTenant('work-orders.review');

    const form = useForm<{
        action: 'execute_immediately' | 'schedule';
        scheduled_date: string;
        hod_notes: string;
    }>({
        action: isAccident ? 'execute_immediately' : 'execute_immediately',
        scheduled_date: '',
        hod_notes: '',
    });

    const showScheduleFields = form.data.action === 'schedule' && !isAccident;

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <ClipboardCheck aria-hidden className="size-4 text-info" />
                <h3 className="text-base font-semibold tracking-tight">
                    Keputusan Review
                </h3>
            </div>

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(review({ tenant, workOrder: workOrderId }).url, {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">
                        Eksekusi pekerjaan
                    </legend>
                    {!isAccident && (
                        <div className="flex items-center gap-2">
                            <input
                                type="radio"
                                id="action-schedule"
                                name="action"
                                checked={form.data.action === 'schedule'}
                                onChange={() =>
                                    form.setData('action', 'schedule')
                                }
                                className="accent-primary"
                            />
                            <Label htmlFor="action-schedule">Dijadwalkan</Label>
                        </div>
                    )}
                    <div className="flex items-center gap-2">
                        <input
                            type="radio"
                            id="action-immediate"
                            name="action"
                            checked={form.data.action === 'execute_immediately'}
                            onChange={() =>
                                form.setData('action', 'execute_immediately')
                            }
                            className="accent-primary"
                        />
                        <Label htmlFor="action-immediate">
                            Eksekusi Langsung
                        </Label>
                    </div>
                    {isAccident && (
                        <p className="text-xs text-muted-foreground">
                            Urgent by Accident wajib dieksekusi langsung — opsi
                            jadwal tidak tersedia.
                        </p>
                    )}
                    {form.errors.action && (
                        <p className="text-xs text-danger">
                            {form.errors.action}
                        </p>
                    )}
                </fieldset>

                {showScheduleFields && (
                    <div className="space-y-1.5">
                        <Label htmlFor="scheduled_date">Tanggal Jadwal</Label>
                        <Input
                            id="scheduled_date"
                            type="date"
                            min={new Date().toISOString().slice(0, 10)}
                            value={form.data.scheduled_date}
                            onChange={(event) =>
                                form.setData(
                                    'scheduled_date',
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(form.errors.scheduled_date)}
                        />
                        {form.errors.scheduled_date && (
                            <p className="text-xs text-danger">
                                {form.errors.scheduled_date}
                            </p>
                        )}
                    </div>
                )}

                <div className="space-y-1.5">
                    <Label htmlFor="hod_notes">Catatan (opsional)</Label>
                    <Textarea
                        id="hod_notes"
                        rows={2}
                        value={form.data.hod_notes}
                        onChange={(event) =>
                            form.setData('hod_notes', event.target.value)
                        }
                    />
                </div>

                <Button type="submit" disabled={form.processing}>
                    Simpan Keputusan
                </Button>
            </form>
        </section>
    );
}

/**
 * Personnel assignment (wo-slice-1 ticket 03): 1+ karyawan pelaksana from
 * the target department. Assigning sets the deadline clock exactly once —
 * on an assigned WO this form is a revision of the personnel list.
 */
function AssignPanel({
    workOrderId,
    employees,
    isRevision,
}: {
    workOrderId: string;
    employees: EmployeeOption[];
    isRevision: boolean;
}) {
    const tenant = useRequiredTenant('work-orders.assign');

    const form = useForm<{
        employees: string[];
        assignment_notes: string;
    }>({
        employees: [],
        assignment_notes: '',
    });

    const toggle = (id: string, checked: boolean): void => {
        const next = checked
            ? [...form.data.employees, id]
            : form.data.employees.filter((existing) => existing !== id);

        form.setData('employees', next);
    };

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <UserCheck aria-hidden className="size-4 text-info" />
                <h3 className="text-base font-semibold tracking-tight">
                    {isRevision ? 'Revisi Penugasan' : 'Assign Karyawan'}
                </h3>
            </div>
            {isRevision && (
                <p className="text-xs text-muted-foreground">
                    Revisi mengganti daftar pelaksana — tanggal assign dan
                    deadline tidak berubah.
                </p>
            )}

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(assign({ tenant, workOrder: workOrderId }).url, {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">
                        Karyawan pelaksana
                    </legend>
                    {employees.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Belum ada karyawan pada department ini.
                        </p>
                    )}
                    {employees.map((employee) => (
                        <div
                            key={employee.id}
                            className="flex items-center gap-2"
                        >
                            <Checkbox
                                id={`employee-${employee.id}`}
                                checked={form.data.employees.includes(
                                    employee.id,
                                )}
                                onCheckedChange={(checked) =>
                                    toggle(employee.id, checked === true)
                                }
                            />
                            <Label htmlFor={`employee-${employee.id}`}>
                                {employee.nama_employee}
                            </Label>
                        </div>
                    ))}
                    {form.errors.employees && (
                        <p className="text-xs text-danger">
                            {form.errors.employees}
                        </p>
                    )}
                </fieldset>

                <div className="space-y-1.5">
                    <Label htmlFor="assignment_notes">
                        Catatan penugasan (opsional)
                    </Label>
                    <Textarea
                        id="assignment_notes"
                        rows={2}
                        value={form.data.assignment_notes}
                        onChange={(event) =>
                            form.setData('assignment_notes', event.target.value)
                        }
                    />
                </div>

                <Button
                    type="submit"
                    disabled={
                        form.processing || form.data.employees.length === 0
                    }
                >
                    {isRevision ? 'Simpan Revisi' : 'Assign Karyawan'}
                </Button>
            </form>
        </section>
    );
}

ShowWorkOrder.layout = {
    breadcrumbs: [{ title: 'Work Order' }, { title: '' }],
    title: (workOrder: WorkOrder) => workOrder.title,
    description: (workOrder: WorkOrder) =>
        `Dimohon oleh ${workOrder.requester?.name ?? '—'}`,
};

/**
 * FR-1.7: pelaksana menyerahkan hasil pekerjaan — catatan wajib, foto
 * bukti opsional (maks 3). The server guard (assigned/revision) is the
 * source of truth; this mirror hides the form elsewhere.
 */
function SubmitPanel({ workOrderId }: { workOrderId: string }) {
    const tenant = useRequiredTenant('work-orders.submit');

    const form = useForm<{
        result_notes: string;
        result_attachments: File[];
    }>({
        result_notes: '',
        result_attachments: [],
    });

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <Send aria-hidden className="size-4 text-info" />
                <h3 className="text-base font-semibold tracking-tight">
                    Serahkan Hasil Pekerjaan
                </h3>
            </div>
            <p className="text-xs text-muted-foreground">
                Catatan hasil wajib diisi; foto bukti maksimal 3 file
                (jpg/jpeg/png/webp, masing-masing maks 2MB). WO akan
                menunggu verifikasi HOD.
            </p>

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(
                        submit({ tenant, workOrder: workOrderId }).url,
                        {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        },
                    );
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="result_notes">Catatan Hasil</Label>
                    <Textarea
                        id="result_notes"
                        rows={3}
                        value={form.data.result_notes}
                        onChange={(event) =>
                            form.setData('result_notes', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.result_notes)}
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
                        onChange={(event) =>
                            form.setData(
                                'result_attachments',
                                Array.from(event.target.files ?? []),
                            )
                        }
                        className="block w-full text-sm file:mr-3 file:rounded-sm file:border file:border-border file:bg-surface-raised file:px-3 file:py-1.5 file:text-sm file:font-medium"
                    />
                    {form.errors.result_attachments && (
                        <p className="text-xs text-danger">
                            {form.errors.result_attachments}
                        </p>
                    )}
                </div>

                <Button type="submit" disabled={form.processing}>
                    Serahkan Hasil
                </Button>
            </form>
        </section>
    );
}

/**
 * FR-1.8: HOD memverifikasi hasil submit — approve closes the WO,
 * reject sends it back for revision with mandatory notes (deadline
 * stays untouched). The submitted result itself is rendered above as
 * the read-only "Hasil Pekerjaan" section.
 */
function VerifyPanel({ workOrderId }: { workOrderId: string }) {
    const tenant = useRequiredTenant('work-orders.verify');

    const approveForm = useForm({});
    const rejectForm = useForm<{ revision_notes: string }>({
        revision_notes: '',
    });

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <ShieldCheck aria-hidden className="size-4 text-warning" />
                <h3 className="text-base font-semibold tracking-tight">
                    Verifikasi Hasil
                </h3>
            </div>
            <p className="text-xs text-muted-foreground">
                Menyetujui menutup WO; menolak mengembalikannya ke pelaksana
                untuk dikerjakan ulang.
            </p>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    approveForm.post(
                        verify({ tenant, workOrder: workOrderId }).url,
                        { preserveScroll: true },
                    );
                }}
            >
                <Button type="submit" disabled={approveForm.processing}>
                    Setujui &amp; Tutup WO
                </Button>
            </form>

            <Separator />

            <form
                className="space-y-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    rejectForm.post(
                        reject({ tenant, workOrder: workOrderId }).url,
                        {
                            preserveScroll: true,
                            onSuccess: () => rejectForm.reset(),
                        },
                    );
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="revision_notes">
                        Catatan Revisi (wajib untuk penolakan)
                    </Label>
                    <Textarea
                        id="revision_notes"
                        rows={2}
                        value={rejectForm.data.revision_notes}
                        onChange={(event) =>
                            rejectForm.setData(
                                'revision_notes',
                                event.target.value,
                            )
                        }
                        aria-invalid={Boolean(rejectForm.errors.revision_notes)}
                    />
                    {rejectForm.errors.revision_notes && (
                        <p className="text-xs text-danger">
                            {rejectForm.errors.revision_notes}
                        </p>
                    )}
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={rejectForm.processing}
                >
                    Tolak &amp; Kembalikan untuk Revisi
                </Button>
            </form>
        </section>
    );
}
