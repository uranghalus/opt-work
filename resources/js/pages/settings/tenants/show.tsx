import { Head, Link, usePage } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { index } from '@/routes/tenants';
import { Button } from '@/components/ui/button';
import type { InertiaConfig } from '@inertiajs/core';

type Branch = {
    id: string;
    optigate_company_id: number | null;
    code: string;
    name: string;
    is_active: boolean;
    deactivated_at: string | null;
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

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

function BranchActions() {
    const { branch } = usePage<PageProps>().props;

    return (
        <Button variant="outline" asChild>
            <Link href={`/${branch.code}/work-orders`}>
                <ExternalLink aria-hidden />
                Buka Work Order
            </Link>
        </Button>
    );
}

export default function ShowTenant({ branch, usage, errors }: PageProps) {
    const { flash } = usePage<
        InertiaConfig['sharedPageProps'] & {
            flash?: { success?: string | null; error?: string | null };
        }
    >().props;

    return (
        <>
            <Head title={branch.name} />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                {flash?.success && (
                    <p
                        role="status"
                        className="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success"
                    >
                        {flash.success}
                    </p>
                )}

                {flash?.error && (
                    <p
                        role="alert"
                        className="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"
                    >
                        {flash.error}
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
                            Kode (URL)
                        </dt>
                        <dd className="font-mono text-sm">{branch.code}</dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            ID Optigate
                        </dt>
                        <dd className="font-mono text-sm">
                            {branch.optigate_company_id ?? '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Status
                        </dt>
                        <dd className="text-sm font-semibold">
                            {branch.is_active ? (
                                <span className="inline-flex items-center gap-1.5 text-success">
                                    <span
                                        aria-hidden
                                        className="size-1.5 rounded-full bg-success"
                                    />
                                    Aktif
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 text-ink-muted">
                                    <span
                                        aria-hidden
                                        className="size-1.5 rounded-full bg-ink-subtle"
                                    />
                                    Nonaktif
                                </span>
                            )}
                        </dd>
                    </div>
                    {!branch.is_active && branch.deactivated_at && (
                        <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                            <dt className="text-sm text-muted-foreground">
                                Nonaktif sejak
                            </dt>
                            <dd className="text-sm tabular-nums">
                                {formatDate(branch.deactivated_at)}
                            </dd>
                        </div>
                    )}
                    <div className="flex items-center justify-between gap-4 px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Dibuat
                        </dt>
                        <dd className="text-sm tabular-nums">
                            {formatDate(branch.created_at)}
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
                                    index === 0 ? '' : 'border-t'
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
                        Unit bisnis disinkronkan dari Optigate. Cabang yang
                        hilang dari Optigate dinonaktifkan otomatis, bukan
                        dihapus, agar riwayat datanya tetap utuh.
                    </p>
                </section>
            </div>
        </>
    );
}

ShowTenant.layout = {
    breadcrumbs: [
        { title: 'Pengaturan' },
        { title: 'Unit Bisnis', href: '/settings/tenants' },
        { title: 'Detail' },
    ],
    title: 'Detail Unit Bisnis',
    description: 'Identitas, status, dan penggunaan data cabang',
    actions: BranchActions,
};
