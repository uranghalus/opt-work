import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { LoaderCircle, Plus, RefreshCw } from 'lucide-react';
import { useCallback, useState } from 'react';
import { index, show, sync } from '@/routes/departments';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useRequiredTenant } from '@/lib/tenant';
import type { InertiaConfig } from '@inertiajs/core';

type Department = {
    id: string;
    kode_department: string;
    nama_department: string | null;
    division: { nama_division: string } | null;
    hod: { name: string; email: string | null; employee: { photo_url: string | null } | null } | null;
    employees_count: number;
    positions_count: number;
};

type PageProps = {
    departments: { data: Department[] };
    last_synced_at: string | null;
};

type FlashProps = PageProps & InertiaConfig['sharedPageProps'] & {
    flash?: { success?: string | null; error?: string | null };
};

/** HOD cell: linked akun avatar + name, or an explicit empty state. */
function HodCell({
    hod,
}: {
    hod: {
        name: string;
        email: string | null;
        employee: { photo_url: string | null } | null;
    } | null;
}) {
    const getInitials = useInitials();

    if (!hod) {
        return <span className="text-ink-subtle">Belum di-set</span>;
    }

    return (
        <div className="flex items-center gap-2">
            <Avatar className="size-7 rounded-full ring-1 ring-border">
                <AvatarImage
                    src={hod.employee?.photo_url ?? undefined}
                    alt={hod.name}
                />
                <AvatarFallback className="rounded-full bg-brand-soft text-[11px] font-semibold text-brand">
                    {getInitials(hod.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid leading-tight">
                <span className="text-sm font-medium">{hod.name}</span>
                {hod.email && (
                    <span className="text-xs text-ink-muted">{hod.email}</span>
                )}
            </div>
        </div>
    );
}

/** Primary actions of this page, rendered in the shell's heading band. */
function DepartmentActions() {
    const tenant = useRequiredTenant('departments.create');
    const [syncing, setSyncing] = useState(false);

    const runSync = useCallback(() => {
        if (syncing) return;
        setSyncing(true);
        router.post(
            sync({ tenant }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSyncing(false),
            },
        );
    }, [tenant, syncing]);

    return (
        <div className="flex items-center gap-2">
            <Button
                onClick={runSync}
                disabled={syncing}
                variant="outline"
                size="lg"
            >
                {syncing ? (
                    <>
                        <LoaderCircle
                            aria-hidden
                            className="animate-spin"
                        />
                        Menyinkronkan…
                    </>
                ) : (
                    <>
                        <RefreshCw aria-hidden />
                        Sinkronkan
                    </>
                )}
            </Button>
            <Button asChild size="lg">
                      <Link href={create({ tenant })}>
                    
                </Link>
            </Button>
        </div>
    );
}

DepartmentActions.displayName = 'DepartmentActions';

Departments.layout = {
    breadcrumbs: [{ title: 'Data Master' }, { title: 'Department' }],
    title: 'Department',
    description:
        'Department perusahaan dalam cabang aktif, termasuk HOD penanggung jawabnya',
    actions: DepartmentActions,
};

export default function Departments(props: PageProps) {
    const { departments } = props;
    const tenant = useRequiredTenant('departments.index');
    const { flash, last_synced_at } = usePage<FlashProps>().props as FlashProps;

    return (
        <>
            <Head title="Department" />

            <div className="space-y-6">
                {flash?.success && (
                    <Alert role="status">
                        <AlertDescription>{String(flash.success)}</AlertDescription>
                    </Alert>
                )}

                {last_synced_at && typeof last_synced_at === 'string' && (
                    <p className="text-sm text-muted-foreground">
                        Last synced:{' '}
                        {new Date(last_synced_at).toLocaleString(undefined, {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        })}
                    </p>
                )}

                {flash?.error && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

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
                                            <HodCell hod={department.hodEmployee} />
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {department.employees_count}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                                                                      <Link href={show({ tenant, department: department.id })}
                                                    >
                                                                                        Lihat
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
