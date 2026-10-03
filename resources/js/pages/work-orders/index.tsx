import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { create, show } from '@/routes/work-orders';
import { CategoryBadge } from '@/components/category-badge';
import Heading from '@/components/heading';
import { StatusBadge, type WoStatus } from '@/components/status-badge';
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

type WorkOrder = {
    id: string;
    nomor_wo: string;
    title: string;
    category: 'normal' | 'accident' | 'owner';
    status: WoStatus;
    requested_schedule_date: string | null;
    requester: { name: string };
    target_department: { nama_department: string | null };
    created_at: string;
};

type PageProps = {
    workOrders: { data: WorkOrder[] };
};

export default function WorkOrders({ workOrders }: PageProps) {
    const { can, activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;
    const tenant = activeTenant ?? '';

    return (
        <>
            <Head title="Work Order" />

            <div className="space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Work Order"
                        description="Work Order yang Anda buat dan yang ditujukan ke department Anda"
                    />
                    {can['work-order.create'] && (
                        <Button asChild>
                            <Link href={create({ tenant })}>
                                <Plus aria-hidden />
                                Buat Work Order
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-lg border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Nomor</TableHead>
                                <TableHead>Judul</TableHead>
                                <TableHead>Kategori</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Department Tujuan</TableHead>
                                <TableHead>Pemohon</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {workOrders.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="h-24 text-center text-ink-muted"
                                    >
                                        Belum ada Work Order.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                workOrders.data.map((workOrder) => (
                                    <TableRow key={workOrder.id}>
                                        <TableCell className="font-mono text-sm">
                                            <Link
                                                href={show({
                                                    tenant,
                                                    workOrder: workOrder.id,
                                                })}
                                                className="hover:underline"
                                            >
                                                {workOrder.nomor_wo}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="max-w-48 truncate font-semibold">
                                            {workOrder.title}
                                        </TableCell>
                                        <TableCell>
                                            <CategoryBadge
                                                category={workOrder.category}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={workOrder.status} />
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {workOrder.target_department?.nama_department ??
                                                '—'}
                                        </TableCell>
                                        <TableCell>
                                            {workOrder.requester?.name}
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
