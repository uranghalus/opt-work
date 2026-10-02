import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { create, edit, index, show } from '@/routes/departments';
import Heading from '@/components/heading';
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

type Department = {
    id: string;
    kode_department: string;
    nama_department: string | null;
    division: { nama_division: string } | null;
    hod: { name: string } | null;
    employees_count: number;
    positions_count: number;
};

type PageProps = {
    departments: { data: Department[] };
};

export default function Departments(props: PageProps) {
    const { departments } = props;
    const { can, activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;
    const tenant = activeTenant ?? '';

    return (
        <>
            <Head title="Department" />

            <div className="space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Department"
                        description="Department perusahaan dalam cabang aktif, termasuk HOD penanggung jawabnya"
                    />
                    {can.create && (
                        <Button asChild>
                            <Link href={create({ tenant })}>
                                <Plus aria-hidden />
                                Tambah Department
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-lg border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Kode</TableHead>
                                <TableHead>Nama</TableHead>
                                <TableHead>Divisi</TableHead>
                                <TableHead>HOD</TableHead>
                                <TableHead className="text-right">
                                    Karyawan
                                </TableHead>
                                <TableHead className="w-24">
                                    <span className="sr-only">Aksi</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {departments.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="h-24 text-center text-ink-muted"
                                    >
                                        Belum ada department.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                departments.data.map((department) => (
                                    <TableRow key={department.id}>
                                        <TableCell className="font-mono text-sm">
                                            {department.kode_department}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            <Link
                                                href={show({ tenant, department: department.id })}
                                                className="hover:underline"
                                            >
                                                {department.nama_department ?? '—'}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {department.division?.nama_division ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {department.hod?.name ?? (
                                                <span className="text-ink-subtle">
                                                    Belum di-set
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {department.employees_count}
                                        </TableCell>
                                        <TableCell>
                                            {can.update && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit({ tenant, department: department.id })}
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
