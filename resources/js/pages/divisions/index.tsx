import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { create, edit, index, show } from '@/routes/divisions';
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

type Division = {
    id: string;
    kode_division: string | null;
    nama_division: string;
    departments_count: number;
};

type PageProps = {
    divisions: { data: Division[] };
};

export default function Divisions(props: PageProps) {
    const { divisions } = props;
    const { can, activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;

    return (
        <>
            <Head title="Divisi" />

            <div className="space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Divisi"
                        description="Kelompok divisi perusahaan dalam cabang aktif"
                    />
                    {can.create && (
                        <Button asChild>
                            <Link
                                href={create({ tenant: activeTenant ?? '' })}
                            >
                                <Plus aria-hidden />
                                Tambah Divisi
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-lg border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Kode</TableHead>
                                <TableHead>Nama Divisi</TableHead>
                                <TableHead className="text-right">
                                    Department
                                </TableHead>
                                <TableHead className="w-24">
                                    <span className="sr-only">Aksi</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {divisions.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="h-24 text-center text-ink-muted"
                                    >
                                        Belum ada divisi.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                divisions.data.map((division) => (
                                    <TableRow key={division.id}>
                                        <TableCell className="font-mono text-sm">
                                            {division.kode_division ?? '—'}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            <Link
                                                href={show({
                                                    tenant: activeTenant ?? '',
                                                    division: division.id,
                                                })}
                                                className="hover:underline"
                                            >
                                                {division.nama_division}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {division.departments_count}
                                        </TableCell>
                                        <TableCell>
                                            {can.update && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit({
                                                            tenant: activeTenant ?? '',
                                                            division: division.id,
                                                        })}
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
