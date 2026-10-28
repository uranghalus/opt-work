import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import { assign } from '@/routes/work-orders';
import { WorkflowStepper } from '@/components/workflow/WorkflowStepper';
import {
    StatusBadge,
    statusLabel,
    type WoStatus,
} from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useRequiredTenant } from '@/lib/tenant';
import { RefreshCw, UserCheck } from 'lucide-react';

type EmployeeOption = {
    id: string;
    nama_employee: string;
};

type WorkOrderView = {
    id: string;
    nomor_wo: string;
    title: string;
    status: WoStatus;
    hod_action: 'execute_immediately' | 'schedule' | null;
    scheduled_date: string | null;
    hod_notes: string | null;
    assigned_employees: string[] | null;
    assignment_notes: string | null;
    assigned_at: string | null;
    deadline_date: string | null;
};

type PageProps = {
    workOrder: WorkOrderView;
    employees: EmployeeOption[];
};

const formatDate = (value: string): string =>
    new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });

const assignedNames = (
    assigned: string[] | null,
    employees: EmployeeOption[],
): string[] =>
    (assigned ?? []).map(
        (id) => employees.find((employee) => employee.id === id)?.nama_employee ?? id,
    );

const ASSIGN_STEPS: {
    label: string;
    status: 'pending' | 'active' | 'completed';
}[] = [
    { label: 'Review HOD', status: 'completed' },
    { label: 'Assign Karyawan', status: 'active' },
    { label: 'Serahkan Hasil', status: 'pending' },
    { label: 'Verifikasi Hasil', status: 'pending' },
];

export default function AssignPage({
    workOrder,
    employees,
}: PageProps): React.ReactElement {
    const tenant = useRequiredTenant('work-orders.assign');

    const assignForm = useForm<{
        employees: string[];
        assignment_notes: string;
    }>({
        employees: workOrder.assigned_employees ?? [],
        assignment_notes: workOrder.assignment_notes ?? '',
    });

    const names = assignedNames(workOrder.assigned_employees, employees);

    const submitAssign = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        assignForm.post(
            assign({ tenant, workOrder: workOrder.id }).url,
            {
                preserveScroll: true,
            },
        );
    };

    const toggleAssign = (id: string, checked: boolean): void => {
        const next = checked
            ? [...assignForm.data.employees, id]
            : assignForm.data.employees.filter((employeeId) => employeeId !== id);
        assignForm.setData('employees', next);
    };

    return (
        <>
            <Head title={`Assign Team — ${workOrder.nomor_wo}`} />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge status={workOrder.status} />
                </div>

                <p className="text-sm text-ink-muted">
                    {statusLabel(workOrder.status)} — {workOrder.title}
                </p>

                <WorkflowStepper currentStep={2} steps={ASSIGN_STEPS} />

                {/* ================= hod_approved: assign form ================= */}
                {workOrder.status === 'hod_approved' && (
                    <section className="rounded-xl border border-border bg-card p-6">
                        <div className="flex items-center gap-2">
                            <UserCheck aria-hidden className="size-4 text-info" />
                            <h3 className="text-base font-semibold tracking-tight">
                                Tetapkan Tim Pelaksana
                            </h3>
                        </div>
                        <p className="text-sm text-ink-muted">
                            Pilih satu atau lebih karyawan pelaksanaan dan masukkan
                            catatan penugasan (opsional).
                        </p>

                        <form onSubmit={submitAssign} className="space-y-5">
                            <fieldset className="space-y-2">
                                <legend className="text-sm font-semibold">
                                    Karyawan Pelaksana
                                </legend>
                                {employees.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada karyawan pada department tujuan.
                                    </p>
                                )}
                                {employees.map((employee) => (
                                    <div
                                        key={employee.id}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={`assign-employee-${employee.id}`}
                                            checked={
                                                assignForm.data.employees.includes(
                                                    employee.id,
                                                )
                                            }
                                            onCheckedChange={(checked) => {
                                                toggleAssign(employee.id, checked === true);
                                            }}
                                        />
                                        <Label htmlFor={`assign-employee-${employee.id}`}>
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

                            <p className="text-sm text-ink-muted">
                                Personnel count:
                                <span className="text-ink font-medium">
                                    {assignForm.data.employees.length}
                                </span>
                            </p>

                            <Button type="submit" disabled={assignForm.processing}>
                                {assignForm.processing ? 'Menugaskan…' : 'Simpan Penugasan'}
                            </Button>
                        </form>
                    </section>
                )}

                {/* ================= scheduled: read-only assigned team + re-assign ================= */}
                {workOrder.status === 'scheduled' && (
                    <section className="rounded-xl border border-border bg-card p-6 space-y-6">
                        <div className="flex items-center gap-2">
                            <StatusBadge status="scheduled" />
                            <h3 className="text-base font-semibold tracking-tight">
                                Terjadwal
                            </h3>
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
                                {workOrder.hod_action === 'schedule'
                                    ? 'Dijadwalkan'
                                    : 'Eksekusi Langsung'}
                            </dd>
                        </dl>

                        {workOrder.hod_notes && (
                            <div className="space-y-1">
                                <p className="text-sm text-ink-muted">Catatan HOD</p>
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
                            {names.length > 0 ? (
                                <ul className="space-y-1">
                                    {names.map((name) => (
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
                                            Belum ada karyawan pada department ini.
                                        </p>
                                    )}
                                    {employees.map((employee) => (
                                        <div
                                            key={employee.id}
                                            className="flex items-center gap-2"
                                        >
                                            <Checkbox
                                                id={`assign-reassign-${employee.id}`}
                                                checked={
                                                    assignForm.data.employees.includes(
                                                        employee.id,
                                                    )
                                                }
                                                onCheckedChange={(checked) => {
                                                    toggleAssign(
                                                        employee.id,
                                                        checked === true,
                                                    );
                                                }}
                                            />
                                            <Label
                                                htmlFor={`assign-reassign-${employee.id}`}
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

                {/* ================= assigned: revision mode ================= */}
                {workOrder.status === 'assigned' && (
                    <section className="rounded-xl border border-border bg-card p-6 space-y-6">
                        <div className="flex items-center gap-2">
                            <StatusBadge status="assigned" />
                            <h3 className="text-base font-semibold tracking-tight">
                                Revisi Penugasan
                            </h3>
                        </div>
                        <div className="bg-warning/50 border border-warning/200 rounded-lg p-4">
                            <p className="text-sm text-warning-foreground">
                                Mengganti karyawan pelaksanaan tanpa menggeser jadwal.
                                Tanggal assign dan deadline tetap dari penugasan
                                pertama.
                            </p>
                        </div>

                        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <dt className="text-ink-muted">Tanggal Assign</dt>
                            <dd className="text-ink font-medium">
                                {workOrder.assigned_at ? (
                                    formatDate(workOrder.assigned_at)
                                ) : (
                                    <span className="text-ink-muted">—</span>
                                )}
                            </dd>
                            <dt className="text-ink-muted">Deadline</dt>
                            <dd className="text-ink font-medium">
                                {workOrder.deadline_date ? (
                                    formatDate(workOrder.deadline_date)
                                ) : (
                                    <span className="text-ink-muted">—</span>
                                )}
                            </dd>
                        </dl>

                        <div className="space-y-1.5">
                            <div className="flex items-center gap-2">
                                <UserCheck aria-hidden className="size-4 text-info" />
                                <span className="text-sm font-semibold">
                                    Tim Pelaksana Saat Ini
                                </span>
                            </div>
                            {names.length > 0 ? (
                                <ul className="space-y-1">
                                    {names.map((name) => (
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

                        <form onSubmit={submitAssign} className="space-y-5">
                            <fieldset className="space-y-2">
                                <legend className="text-sm font-semibold">
                                    Ubah Karyawan Pelaksana
                                </legend>
                                {employees.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada karyawan pada department tujuan.
                                    </p>
                                )}
                                {employees.map((employee) => (
                                    <div
                                        key={employee.id}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={`assign-revision-${employee.id}`}
                                            checked={
                                                assignForm.data.employees.includes(
                                                    employee.id,
                                                )
                                            }
                                            onCheckedChange={(checked) => {
                                                toggleAssign(employee.id, checked === true);
                                            }}
                                        />
                                        <Label htmlFor={`assign-revision-${employee.id}`}>
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
                                    Catatan revisi (opsional)
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

                            <Button type="submit" disabled={assignForm.processing}>
                                {assignForm.processing ? 'Menyimpan…' : 'Simpan Revisi'}
                            </Button>
                        </form>
                    </section>
                )}
            </div>
        </>
    );
}
