import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { create, destroy, edit, show } from '@/routes/tenants';
import { Alert, AlertDescription } from '@/components/ui/alert';
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

type Tenant = {
    id: string;
    name: string;
    code: string | null;
    is_active: boolean;
};

type PageProps = {
    tenants: { data: Tenant[] };
    errors?: Record<string, string>;
};

/** Primary action of this page, rendered in the shell's heading band. */
function TenantActions() {
    return (
        <Button asChild size="lg">
            <Link href={create()}>
                <Plus aria-hidden />
                Tambah Cabang
            </Link>
        </Button>
    );
}

Tenants.layout = {
    breadcrumbs: [{ title: 'Pengaturan' }, { title: 'Cabang' }],
    title: 'Cabang',
    description: 'Kelola cabang dan status aktifnya',
    actions: TenantActions,
};

export default function Tenants({ tenants, errors }: PageProps) {
    const { flash } = usePage<
        InertiaConfig['sharedPageProps'] & { flash?: { success?: string | null } }
    >().props;

    return (
        <>
            <Head title="Cabang" />

            <div className="space-y-6">
                {flash?.success && (
                    <Alert role="status">
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {errors?.tenant_id && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{errors.tenant_id}</AlertDescription>
                    </Alert>
                )}

                <div className="overflow-hidden rounded-lg border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Slug</TableHead>
                                <TableHead>Nama</TableHead>
                                <TableHead>Kode</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-24">
                                    <span className="sr-only">Aksi</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tenants.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="h-24 text-center text-ink-muted"
                                    >
                                        Belum ada cabang.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                tenants.data.map((tenant) => (
                                    <TableRow key={tenant.id}>
                                        <TableCell className="font-mono text-sm">
                                            {tenant.id}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            <Link
                                                href={show(tenant.id)}
                                                className="hover:underline"
                                            >
                                                {tenant.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="font-mono text-sm">
                                            {tenant.code ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                className={
                                                    tenant.is_active
                                                        ? 'inline-flex items-center gap-1.5 rounded-sm border border-success/30 bg-success/10 px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-success'
                                                        : 'inline-flex items-center gap-1.5 rounded-sm border border-border bg-surface-raised px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-ink-muted'
                                                }
                                            >
                                                {tenant.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={edit(tenant.id)}
                                                >
                                                    Edit
                                                </Link>
                                            </Button>
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