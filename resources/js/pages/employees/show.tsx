import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

type Employee = {
    id: string;
    nik_employee: string | null;
    nama_employee: string;
    email: string | null;
    number: string | null;
    department: { kode_department: string; nama_department: string | null } | null;
    position: { nama_position: string } | null;
    division: { nama_division: string } | null;
};

type PageProps = {
    employee: Employee;
};

export default function ShowEmployee({ employee }: PageProps) {
    return (
        <>
            <Head title={employee.nama_employee} />

            <div className="mx-auto w-full max-w-xl space-y-6 px-4 py-6">
                <Heading
                    title={employee.nama_employee}
                    description="Detail karyawan"
                />

                <dl className="overflow-hidden rounded-lg border bg-card">
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">NIK</dt>
                        <dd className="font-mono text-sm">
                            {employee.nik_employee ?? '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Nama Karyawan
                        </dt>
                        <dd className="text-sm font-semibold">
                            {employee.nama_employee}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">Email</dt>
                        <dd className="text-sm">{employee.email ?? '—'}</dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            No. Telepon
                        </dt>
                        <dd className="text-sm">{employee.number ?? '—'}</dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Department
                        </dt>
                        <dd className="text-sm">
                            {employee.department
                                ? `${employee.department.kode_department} — ${employee.department.nama_department ?? ''}`
                                : '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Position
                        </dt>
                        <dd className="text-sm">
                            {employee.position?.nama_position ?? '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 px-4 py-3">
                        <dt className="text-sm text-muted-foreground">Divisi</dt>
                        <dd className="text-sm">
                            {employee.division?.nama_division ?? '—'}
                        </dd>
                    </div>
                </dl>
            </div>
        </>
    );
}
