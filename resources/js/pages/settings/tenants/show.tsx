import { Head, Link, usePage } from '@inertiajs/react';
import { DeleteTenant } from '@/components/delete-tenant';
import { Button } from '@/components/ui/button';
import type { InertiaConfig } from '@inertiajs/core';

type Branch = {
    id: string;
    name: string;
    code: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

type PageProps = {
    branch: Branch;
    usage: Record<string, number>;
    errors?: Record<string, string>;
};

const USAGE_LABELS: Record<string, string> = {
    'work order': 'Work Order',
    karyawan: 'Karyawan',
    department: 'Department',
    divisi: 'Divisi',
    posisi: 'Posisi',
};

function BranchActions() {
    const { branch } = usePage<PageProps>().props;

    return (
        <div className="flex items-center gap-2">
            <Button variant="outline" asChild>
                <Link href={`/${branch.id}/work-orders`}>Buka work order</Link>
            </Button>
            <Button asChild>
                <Link href={`/settings/tenants/${branch.id}/edit`}>Edit</Link>
            </Button>
        </div>
    );
}

export default function ShowTenant({ branch, usage, errors }: PageProps) {
    const { flash } = usePage<
        InertiaConfig['sharedPageProps'] & { flash?: { success?: string | null } }
    >().props;

    const hasUsage = Object.values(usage).some((count) => count > 0);

    return (
        <>
            <Head title={branch.name} />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h2 className="text-xl font-semibold tracking-tight">
                            {branch.name}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Cabang dengan slug{' '}
                            <span className="font-mono">{branch.id}</span>
                        </p>
                    </div>

                    {hasUsage ? (
                        <span className="shrink-0 text-xs text-muted-foreground">
                            Nonaktifkan untuk menonaktifkan
                        </span>
                    ) : (
                        <DeleteTenant id={branch.id} name={branch.name} />
                    )}
                </div>

                {flash?.success && (
                    <p
                        role="status"
                        className="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success"
                    >
                        {flash.success}
                    </p>
                )}

                {errors?.tenant_id && (
                    <p
                        role="alert"
                        className="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"
                    >
                        {errors.tenant_id}
                    </p>
                )}

                <dl className="overflow-hidden rounded-lg border bg-card">
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Slug (URL)
                        </dt>
                        <dd className="font-mono text-sm">{branch.id}</dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">Kode</dt>
                        <dd className="font-mono text-sm">
                            {branch.code ?? '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Status
                        </dt>
                        <dd className="text-sm font-semibold">
                            {branch.is_active ? 'Aktif' : 'Nonaktif'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Dibuat
                        </dt>
                        <dd className="text-sm tabular-nums">
                            {new Date(
                                branch.created_at,
                            ).toLocaleDateString('id-ID', {
                                day: '2-digit',
                                month: 'long',
                                year: 'numeric',
                            })}
                        </dd>
                    </div>
                </dl>

                <section className="space-y-2">
                    <h3 className="text-base font-semibold tracking-tight">
                        Data cabang
                    </h3>
                    <dl className="overflow-hidden rounded-lg border bg-card">
                        {Object.entries(usage).map(([label, count], index) => (
                            <div
                                key={label}
                                className={`flex items-center justify-between gap-4 px-4 py-3 ${
                                    index === 0
                                        ? ''
                                        : 'border-t'
                                }`}
                            >
                                <dt className="text-sm text-muted-foreground">
                                    {USAGE_LABELS[label] ?? label}
                                </dt>
                                <dd className="text-sm font-semibold tabular-nums">
                                    {count}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    <p className="text-xs text-muted-foreground">
                        Cabang dengan data di atas tidak dapat dihapus. Ubah
                        status menjadi Nonaktif agar tetap dapat diakses lewat
                        URL tanpa muncul di pemilih cabang.
                    </p>
                </section>
            </div>
        </>
    );
}

ShowTenant.layout = {
    breadcrumbs: [
        { title: 'Pengaturan' },
        { title: 'Cabang', href: '/settings/tenants' },
    ],
    title: 'Detail Cabang',
    description: 'Identitas dan status cabang',
    actions: BranchActions,
};