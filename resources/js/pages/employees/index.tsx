import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { create, edit, index, show } from '@/routes/employees';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { InertiaConfig } from '@inertiajs/core';

type Employee = {
    id: string;
    nik_employee: string | null;
    nama_employee: string;
    email: string | null;
    number: string | null;
    department: { kode_department: string } | null;
    position: { nama_position: string } | null;
    division: { nama_division: string } | null;
};

type PageProps = {
    employees: { data: Employee[] };
};

/** Primary action of this page, rendered in the shell's heading band. */
function EmployeeActions() {
    const { can, activeTenant } = usePage<
        InertiaConfig['sharedPageProps']
    >().props;

    if (!can?.create) {
        return null;
    }

    return (
        <Button asChild size="lg">
            <Link href={create({ tenant: activeTenant ?? '' })}>
                <Plus aria-hidden />
                Tambah Karyawan
            </Link>
        </Button>
    );
}

Employees.layout = {
    breadcrumbs: [{ title: 'Data Master' }, { title: 'Karyawan' }],
    title: 'Karyawan',
    description: 'Data karyawan dalam cabang aktif',
    actions: EmployeeActions,
};

export default function Employees(props: PageProps) {
    const { employees } = props;
    const { can, activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;
    const tenant = activeTenant ?? '';

    return (
        <>
            <Head title="Karyawan" />

            <div className="space-y-6">
                <div className="overflow-hidden rounded-lg border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>NIK</TableHead>
                                <TableHead>Nama</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Position</TableHead>
                                <TableHead>Kontak</TableHead>
                                <TableHead className="w-24">
                                    <span className="sr-only">Aksi</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {employees.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="h-24 text-center text-ink-muted"
                                    >
                                        Belum ada karyawan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                employees.data.map((employee) => (
                                    <TableRow key={employee.id}>
                                        <TableCell className="font-mono text-sm">
                                            {employee.nik_employee ?? '—'}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            <Link
                                                href={show({ tenant, employee: employee.id })}
                                                className="hover:underline"
                                            >
                                                {employee.nama_employee}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="font-mono text-sm text-muted-foreground">
                                            {employee.department?.kode_department ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {employee.position?.nama_position ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {employee.email ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {can.update && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit({ tenant, employee: employee.id })}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}
