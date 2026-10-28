import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import { assign, review } from '@/routes/work-orders';
import { WorkflowStepper } from '@/components/workflow/WorkflowStepper';
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
import { Textarea } from '@/components/ui/textarea';
import { useRequiredTenant } from '@/lib/tenant';
import { Calendar, ClipboardCheck, RefreshCw, UserCheck } from 'lucide-react';

type EmployeeOption = {
    id: string;
    nama_employee: string;
};

type WorkOrderView = {
    id: string;
    nomor_wo: string;
    title: string;
    status: WoStatus;
    category: 'normal' | 'accident' | 'owner';
    hod_action: 'execute_immediately' | 'schedule' | null;
    scheduled_date: string | null;
    hod_notes: string | null;
    assigned_employees: string[] | null;
    assignment_notes: string | null;
    assigned_at: string | null;
};

type PageProps = {
    workOrder: WorkOrderView;
    employees: EmployeeOption[];
};

const todayISO = (): string => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const formatDate = (value: string): string =>
    new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });

const actionLabel = (action: 'execute_immediately' | 'schedule'): string =>
    action === 'schedule' ? 'Dijadwalkan' : 'Eksekusi Langsung';

const REVIEW_STEPS: {
    label: string;
    status: 'pending' | 'active' | 'completed';
}[] = [
    { label: 'Review HOD', status: 'active' },
    { label: 'Assign Karyawan', status: 'pending' },
    { label: 'Serahkan Hasil', status: 'pending' },
    { label: 'Verifikasi Hasil', status: 'pending' },
];

HodReviewPage.layout = {
    breadcrumbs: [{ title: 'Work Order' }, { title: 'Review HOD' }],
    title: 'HOD Review',
    description:
        'Keputusan eksekusi HOD (langsung atau dijadwalkan) untuk Work Order',
};

export default function HodReviewPage({
    workOrder,
    employees,
}: PageProps): React.ReactElement {
    const tenant = useRequiredTenant('work-orders.review');
    const isAccident = workOrder.category === 'accident';

    const reviewForm = useForm<{
        action: 'execute_immediately' | 'schedule';
        scheduled_date: string;
        hod_notes: string;
        // Dikirim bersama keputusan review (terima server untuk kompatibilitas
        // ke depan); penugasan resmi tetap lewat endpoint work-orders.assign.
        employees: string[];
    }>({
        action: 'execute_immediately',
        scheduled_date: '',
        hod_notes: '',
        employees: [],
    });

    const assignForm = useForm<{
        employees: string[];
        assignment_notes: string;
    }>({
        employees: workOrder.assigned_employees ?? [],
        assignment_notes: workOrder.assignment_notes ?? '',
    });

    const scheduleForm = useForm<{
        scheduled_date: string;
        hod_notes: string;
    }>({
        scheduled_date: workOrder.scheduled_date ?? '',
        hod_notes: workOrder.hod_notes ?? '',
    });

    const showScheduleDecision = reviewForm.data.action === 'schedule' && !isAccident;

    const assignedNames = (workOrder.assigned_employees ?? []).map(
        (id) => employees.find((employee) => employee.id === id)?.nama_employee ?? id,
    );

    const submitReview = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        reviewForm.post(review({ tenant, workOrder: workOrder.id }).url, {
            preserveScroll: true,
            onSuccess: () => reviewForm.reset(),
        });
    };

    const submitAssign = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        assignForm.post(
            assign({ tenant, workOrder: workOrder.id }).url,
            {
                preserveScroll: true,
            },
        );
    };

    const submitSchedule = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        reviewForm.setData('action', 'schedule');
        reviewForm.setData('scheduled_date', scheduleForm.data.scheduled_date);
        reviewForm.setData('hod_notes', scheduleForm.data.hod_notes);
        reviewForm.post(review({ tenant, workOrder: workOrder.id }).url, {
            preserveScroll: true,
            onSuccess: () => reviewForm.reset(),
        });
    };

    const toggleAssign = (id: string, checked: boolean): void => {
        const next = checked
            ? [...assignForm.data.employees, id]
            : assignForm.data.employees.filter((employeeId) => employeeId !== id);
        assignForm.setData('employees', next);
    };

    return (
        <>
            <Head title={`HOD Review — ${workOrder.nomor_wo}`} />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <div className="flex flex-wrap items-center gap-2">
                    <CategoryBadge category={workOrder.category} />
                    <StatusBadge status={workOrder.status} />
                </div>

                <p className="text-sm text-ink-muted">
                    {statusLabel(workOrder.status)} — {workOrder.title}
                </p>

                <WorkflowStepper currentStep={1} steps={REVIEW_STEPS} />

                {/* ================= waiting_hod: review decision form ================= */}
                {workOrder.status === 'waiting_hod' && (
                    <section className="rounded-xl border border-border bg-card p-6">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck aria-hidden className="size-4 text-info" />
                            <h3 className="text-base font-semibold tracking-tight">
                                Keputusan Review HOD
                            </h3>
                        </div>
                        <p className="text-sm text-ink-muted">
                            Tentukan apakah pekerjaan dieksekusi langsung atau
                            dijadwalkan.
                        </p>

                        <form onSubmit={submitReview} className="space-y-5">
                            <fieldset className="space-y-2">
                                <legend className="text-sm font-semibold">
                                    Eksekusi pekerjaan
                                </legend>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        name="action"
                                        value="execute_immediately"
                                        checked={
                                            reviewForm.data.action ===
                                            'execute_immediately'
                                        }
                                        onChange={() =>
                                            reviewForm.setData(
                                                'action',
                                                'execute_immediately',
                                            )
                                        }
                                        className="accent-primary"
                                    />
                                    <Label htmlFor="action-immediate">
                                        Eksekusi Langsung
                                    </Label>
                                </div>
                                {!isAccident && (
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            name="action"
                                            value="schedule"
                                            checked={reviewForm.data.action === 'schedule'}
                                            onChange={() =>
                                                reviewForm.setData(
                                                    'action',
                                                    'schedule',
                                                )
                                            }
                                            className="accent-primary"
                                        />
                                        <Label htmlFor="action-schedule">
                                            Dijadwalkan
                                        </Label>
                                    </div>
                                )}
                                {isAccident && (
                                    <p className="text-xs text-muted-foreground">
                                        Urgent by Accident wajib dieksekusi
                                        langsung — opsi jadwal disembunyikan.
                                    </p>
                                )}
                                {reviewForm.errors.action && (
                                    <p className="text-xs text-danger">
                                        {reviewForm.errors.action}
                                    </p>
                                )}
                            </fieldset>

                            {showScheduleDecision && (
                                <div className="space-y-1.5">
                                    <Label htmlFor="scheduled_date">
                                        Tanggal Jadwal
                                    </Label>
                                    <Input
                                        id="scheduled_date"
                                        type="date"
                                        min={todayISO()}
                                        value={reviewForm.data.scheduled_date}
                                        onChange={(event) =>
                                            reviewForm.setData(
                                                'scheduled_date',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(
                                                reviewForm.errors
                                                    .scheduled_date,
                                            )
                                        }
                                    />
                                    {reviewForm.errors.scheduled_date && (
                                        <p className="text-xs text-danger">
                                            {reviewForm.errors.scheduled_date}
                                        </p>
                                    )}
                                </div>
                            )}

                            <fieldset className="space-y-2">
                                <legend className="text-sm font-semibold">
                                    Karyawan Pelaksana
                                </legend>
                                {employees.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada karyawan pada department
                                        tujuan.
                                    </p>
                                )}
                                {employees.map((employee) => (
                                    <div
                                        key={employee.id}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={`review-employee-${employee.id}`}
                                            checked={
                                                reviewForm.data.employees.includes(
                                                    employee.id,
                                                )
                                            }
                                            onCheckedChange={(checked) => {
                                                const next = checked === true
                                                    ? [
                                                            ...reviewForm.data.employees,
                                                            employee.id,
                                                        ]
                                                    : reviewForm.data.employees.filter(
                                                          (employeeId) => employeeId !== employee.id,
                                                      );
                                                reviewForm.setData(
                                                    'employees',
                                                    next,
                                                );
                                            }}
                                        />
                                        <Label
                                            htmlFor={`review-employee-${employee.id}`}
                                        >
                                            {employee.nama_employee}
                                        </Label>
                                    </div>
                                ))}
                                {reviewForm.errors.employees && (
                                    <p className="text-xs text-danger">
                                        {reviewForm.errors.employees}
                                    </p>
                                )}
                            </fieldset>

                            <div className="space-y-1.5">
                                <Label htmlFor="hod_notes">Catatan HOD (opsional)</Label>
                                <Textarea
                                    id="hod_notes"
                                    rows={2}
                                    value={reviewForm.data.hod_notes}
                                    onChange={(event) =>
                                        reviewForm.setData(
                                            'hod_notes',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <Button type="submit" disabled={reviewForm.processing}>
                                {reviewForm.processing
                                    ? 'Menyimpan…'
                                    : 'Simpan Keputusan'}
                            </Button>
                        </form>
                    </section>
                )}

                {/* ================= hod_approved: read-only + re-assign option ================= */}
                {workOrder.status === 'hod_approved' && (
                    <section className="space-y-6 rounded-xl border border-border bg-card p-6">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck aria-hidden className="size-4 text-success" />
                            <h3 className="text-base font-semibold tracking-tight">
                                Disetujui HOD
                            </h3>
                            <StatusBadge status="hod_approved" />
                        </div>

                        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <dt className="text-ink-muted">Keputusan</dt>
                            <dd className="text-ink font-medium">
                                {actionLabel(
                                    workOrder.hod_action ??
                                        'execute_immediately',
                                )}
                            </dd>
                            {workOrder.scheduled_date && (
                                <>
                                    <dt className="text-ink-muted">
                                        Tanggal Jadwal
                                    </dt>
                                    <dd>{formatDate(workOrder.scheduled_date)}</dd>
                                </>
                            )}
                            {workOrder.assigned_at && (
                                <>
                                    <dt className="text-ink-muted">Disetujui pada</dt>
                                    <dd>{formatDate(workOrder.assigned_at)}</dd>
                                </>
                            )}
                        </dl>

                        {workOrder.hod_notes && (
                            <div className="space-y-1">
                                <p className="text-sm text-ink-muted">
                                    Catatan HOD
                                </p>
                                <p className="text-sm whitespace-pre-wrap">
                                    {workOrder.hod_notes}
                                </p>
                            </div>
                        )}

                        <div className="space-y-1.5">
                            <div className="flex items-center gap-2">
                                <UserCheck aria-hidden className="size-4 text-info" />
                                <span className="text-sm font-semibold">
                                    Tim Pelaksana
                                </span>
                            </div>
                            {assignedNames.length > 0 ? (
                                <ul className="space-y-1">
                                    {assignedNames.map((name) => (
                                        <li
                                            key={name}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <span
                                                className="rounded-md border border-border bg-surface-raised px-2 py-0.5 text-xs"
                                            >
                                                {name}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada karyawan yang ditugaskan.
                                </p>
                            )}
                        </div>

                        <section className="rounded-lg border bg-surface-raised p-4">
                            <div className="flex items-center gap-2 mb-3">
                                <RefreshCw aria-hidden className="size-4 text-info" />
                                <h4 className="text-sm font-semibold">
                                    Re-assign Tim Pelaksana
                                </h4>
                            </div>
                            <p className="text-xs text-muted-foreground mb-4">
                                Mengganti daftar pelaksanaan. Tanggal assign dan
                                deadline tidak bergeser.
                            </p>
                            <form onSubmit={submitAssign} className="space-y-3">
                                <fieldset className="space-y-2">
                                    <legend className="text-xs font-semibold text-ink-muted">
                                        Karyawan pelaksana
                                    </legend>
                                    {employees.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Belum ada karyawan pada department
                                            ini.
                                        </p>
                                    )}
                                    {employees.map((employee) => (
                                        <div
                                            key={employee.id}
                                            className="flex items-center gap-2"
                                        >
                                            <Checkbox
                                                id={`review-reassign-${employee.id}`}
                                                checked={
                                                    assignForm.data.employees.includes(
                                                        employee.id,
                                                    )
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleAssign(
                                                        employee.id,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor={`review-reassign-${employee.id}`}
                                            >
                                                {employee.nama_employee}
                                            </Label>
                                        </div>
                                    ))}
                                    {assignForm.errors.employees && (
                                        <p className="text-xs text-danger">
                                            {assignForm.errors.employees}
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
                                        value={assignForm.data.assignment_notes}
                                        onChange={(event) =>
                                            assignForm.setData(
                                                'assignment_notes',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    disabled={
                                        assignForm.processing ||
                                        assignForm.data.employees.length === 0
                                    }
                                >
                                    {assignForm.processing
                                        ? 'Menyimpan…'
                                        : 'Simpan Re-assign'}
                                </Button>
                            </form>
                        </section>
                    </section>
                )}

                {/* ================= scheduled: read-only schedule + update option ================= */}
                {workOrder.status === 'scheduled' && (
                    <section className="space-y-6 rounded-xl border border-border bg-card p-6">
                        <div className="flex items-center gap-2">
                            <Calendar aria-hidden className="size-4 text-info" />
                            <h3 className="text-base font-semibold tracking-tight">
                                Terjadwal
                            </h3>
                            <StatusBadge status="scheduled" />
                        </div>

                        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <dt className="text-ink-muted">Tanggal Jadwal</dt>
                            <dd className="text-ink font-medium">
                                {workOrder.scheduled_date ? (
                                    formatDate(workOrder.scheduled_date)
                                ) : (
                                    <span className="text-ink-muted">—</span>
                                )}
                            </dd>
                            <dt className="text-ink-muted">Metode Eksekusi</dt>
                            <dd className="text-ink font-medium">
                                {actionLabel(
                                    workOrder.hod_action ??
                                        'execute_immediately',
                                )}
                            </dd>
                        </dl>

                        {workOrder.hod_notes && (
                            <div className="space-y-1">
                                <p className="text-sm text-ink-muted">
                                    Catatan HOD
                                </p>
                                <p className="text-sm whitespace-pre-wrap">
                                    {workOrder.hod_notes}
                                </p>
                            </div>
                        )}

                        {assignedNames.length > 0 && (
                            <div className="space-y-1.5">
                                <div className="flex items-center gap-2">
                                    <UserCheck aria-hidden className="size-4 text-info" />
                                    <span className="text-sm font-semibold">
                                        Tim Pelaksana
                                    </span>
                                </div>
                                <ul className="space-y-1">
                                    {assignedNames.map((name) => (
                                        <li
                                            key={name}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <span
                                                className="rounded-md border border-border bg-surface-raised px-2 py-0.5 text-xs"
                                            >
                                                {name}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <section className="rounded-lg border bg-surface-raised p-4">
                            <div className="flex items-center gap-2 mb-3">
                                <RefreshCw aria-hidden className="size-4 text-info" />
                                <h4 className="text-sm font-semibold">
                                    Perbarui Jadwal
                                </h4>
                            </div>
                            <form onSubmit={submitSchedule} className="space-y-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="update_scheduled_date">
                                        Tanggal Jadwal Baru
                                    </Label>
                                    <Input
                                        id="update_scheduled_date"
                                        type="date"
                                        min={todayISO()}
                                        value={scheduleForm.data.scheduled_date}
                                        onChange={(event) =>
                                            scheduleForm.setData(
                                                'scheduled_date',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={false}
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="update_hod_notes">
                                        Catatan (opsional)
                                    </Label>
                                    <Textarea
                                        id="update_hod_notes"
                                        rows={2}
                                        value={scheduleForm.data.hod_notes}
                                        onChange={(event) =>
                                            scheduleForm.setData(
                                                'hod_notes',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    disabled={scheduleForm.processing}
                                >
                                    {scheduleForm.processing ? 'Menyimpan…' : 'Simpan Jadwal'}
                                </Button>
                            </form>
                        </section>
                    </section>
                )}
            </div>
        </>
    );
}
