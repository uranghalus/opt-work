import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { create, edit, index } from '@/routes/positions';
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

type Position = {
    id: string;
    nama_position: string;
    department: { kode_department: string } | null;
    employees_count: number;
};

type PageProps = {
    positions: { data: Position[] };
};

export default function Positions(props: PageProps) {
    const { positions } = props;
    const { can, activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;
    const tenant = activeTenant ?? '';

    return (
        <>
            <Head title="Position" />

            <div className="space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Position"
                        description="Jabatan karyawan dalam cabang aktif"
                    />
                    {can.create && (
                        <Button asChild>
                            <Link href={create({ tenant })}>
                                <Plus aria-hidden />
                                Tambah Position
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-lg border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Nama Position</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead className="text-right">
                                    Karyawan
                                </TableHead>
                                <TableHead className="w-24">
                                    <span className="sr-only">Aksi</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {positions.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="h-24 text-center text-ink-muted"
                                    >
                                        Belum ada position.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                positions.data.map((position) => (
                                    <TableRow key={position.id}>
                                        <TableCell className="font-semibold">
                                            {position.nama_position}
                                        </TableCell>
                                        <TableCell className="font-mono text-sm text-muted-foreground">
                                            {position.department?.kode_department ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {position.employees_count}
                                        </TableCell>
                                        <TableCell>
                                            {can.update && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit({ tenant, position: position.id })}
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
