import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { create, edit, index } from '@/routes/positions';
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

/** Primary action of this page, rendered in the shell's heading band. */
function PositionActions() {
    const { activeTenant } = usePage<
        InertiaConfig['sharedPageProps']
    >().props;
    return (
        <Button asChild size="lg">
            <Link href={create({ tenant: activeTenant!, })}>
                <Plus aria-hidden />
                Tambah Position
            </Link>
        </Button>
    );
}

Positions.layout = {
    breadcrumbs: [{ title: 'Data Master' }, { title: 'Position' }],
    title: 'Position',
    description: 'Jabatan karyawan dalam cabang aktif',
    actions: PositionActions,
};

export default function Positions(props: PageProps) {
    const { positions } = props;
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;
    const tenant = activeTenant!;

    return (
        <>
            <Head title="Position" />

            <div className="space-y-6">
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
                                            <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit({ tenant, position: position.id })}
                                                    >
                                                        Edit
                                                    </Link></Button>
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
