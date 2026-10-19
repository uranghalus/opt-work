import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    ExternalLink,
    Hash,
    Info,
} from 'lucide-react';
import { index as tenantIndex } from '@/routes/tenants';
import { index as workOrderIndex } from '@/routes/work-orders';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { InertiaConfig } from '@inertiajs/core';

type Branch = {
    id: string;
    optigate_company_id: string | null;
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

const USAGE_ORDER = ['work order', 'karyawan', 'department', 'divisi', 'posisi'];

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
            <Link href={workOrderIndex({ tenant: branch.code })}>
                <ExternalLink aria-hidden />
                Buka Work Order
            </Link>
        </Button>
    );
}

function DetailRow({
    label,
    children,
    last = false,
}: {
    label: string;
    children: React.ReactNode;
    last?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex items-center justify-between gap-4 px-4 py-3',
                !last && 'border-b border-border',
            )}
        >
            <dt className="text-sm text-ink-muted">{label}</dt>
            <dd className="text-sm font-medium text-ink">{children}</dd>
        </div>
    );
}

export default function ShowTenant({ branch, usage, errors }: PageProps) {
    const { flash } = usePage<
        InertiaConfig['sharedPageProps'] & {
            flash?: { success?: string | null; error?: string | null };
        }
    >().props;

    const usageEntries = USAGE_ORDER.filter((key) => key in usage).map(
        (key) => [key, usage[key]] as const,
    );
    const maxUsage = Math.max(1, ...usageEntries.map(([, count]) => count));

    return (
        <>
            <Head title={branch.name} />

            <div className="mx-auto w-full max-w-3xl space-y-5">
                {flash?.success && (
                    <p
                        role="status"
                        className="rounded-lg border border-success/25 bg-success-subtle px-4 py-3 text-sm text-success"
                    >
                        {flash.success}
                    </p>
                )}

                {flash?.error && (
                    <p
                        role="alert"
                        className="rounded-lg border border-danger/25 bg-danger-subtle px-4 py-3 text-sm text-danger"
                    >
                        {flash.error}
                    </p>
                )}

                {errors?.tenant_id && (
                    <p
                        role="alert"
                        className="rounded-lg border border-danger/25 bg-danger-subtle px-4 py-3 text-sm text-danger"
                    >
                        {errors.tenant_id}
                    </p>
                )}

                {/* Identity card */}
                <div className="rounded-xl border border-border bg-card shadow-card">
                    <div className="flex flex-col gap-4 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-4">
                            <span
                                aria-hidden
                                className={cn(
                                    'flex size-12 items-center justify-center rounded-lg',
                                    branch.is_active
                                        ? 'bg-brand/10 text-brand'
                                        : 'bg-surface-sunken text-ink-subtle',
                                )}
                            >
                                <Building2 className="size-6" />
                            </span>
                            <div>
                                <h2 className="text-lg font-semibold tracking-tight text-ink">
                                    {branch.name}
                                </h2>
                                <p className="mt-0.5 flex items-center gap-1.5 font-mono text-xs text-ink-muted">
                                    <Hash aria-hidden className="size-3" />
                                    {branch.code}
                                </p>
                            </div>
                        </div>
                        <span
                            className={cn(
                                'inline-flex w-fit items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold',
                                branch.is_active
                                    ? 'border-success/25 bg-success-subtle text-success'
                                    : 'border-border bg-surface-sunken text-ink-subtle',
                            )}
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    'size-1.5 rounded-full',
                                    branch.is_active
                                        ? 'bg-success'
                                        : 'bg-ink-subtle',
                                )}
                            />
                            {branch.is_active ? 'Aktif' : 'Nonaktif'}
                        </span>
                    </div>

                    <dl>
                        <DetailRow label="ID Optigate">
                            <span className="font-mono">
                                {branch.optigate_company_id ?? '—'}
                            </span>
                        </DetailRow>
                        <DetailRow label="Kode (URL)">
                            <span className="font-mono">{branch.code}</span>
                        </DetailRow>
                        {!branch.is_active && branch.deactivated_at && (
                            <DetailRow label="Nonaktif sejak">
                                <span className="tabular-nums">
                                    {formatDate(branch.deactivated_at)}
                                </span>
                            </DetailRow>
                        )}
                        <DetailRow label="Dibuat">
                            <span className="tabular-nums">
                                {formatDate(branch.created_at)}
                            </span>
                        </DetailRow>
                        <DetailRow label="Diperbarui" last>
                            <span className="tabular-nums">
                                {formatDate(branch.updated_at)}
                            </span>
                        </DetailRow>
                    </dl>
                </div>

                {/* Usage section */}
                <section className="space-y-2">
                    <h3 className="text-sm font-semibold tracking-tight text-ink">
                        Data cabang
                    </h3>
                    <div className="rounded-xl border border-border bg-card shadow-card">
                        <dl>
                            {usageEntries.map(([label, count], idx) => (
                                <div
                                    key={label}
                                    className={cn(
                                        'px-4 py-3',
                                        idx < usageEntries.length - 1 &&
                                            'border-b border-border',
                                    )}
                                >
                                    <div className="flex items-center justify-between gap-4">
                                        <dt className="text-sm text-ink-muted">
                                            {USAGE_LABELS[label] ?? label}
                                        </dt>
                                        <dd className="text-sm font-semibold tabular-nums text-ink">
                                            {count}
                                        </dd>
                                    </div>
                                    <div
                                        aria-hidden
                                        className="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-sunken"
                                    >
                                        <div
                                            className={cn(
                                                'h-full rounded-full transition-all duration-500 ease-out-expo',
                                                branch.is_active
                                                    ? 'bg-brand/70'
                                                    : 'bg-ink-subtle/50',
                                            )}
                                            style={{
                                                width: `${Math.max(4, (count / maxUsage) * 100)}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </dl>
                    </div>
                    <p className="flex items-start gap-1.5 text-xs text-ink-muted">
                        <Info
                            aria-hidden
                            className="mt-0.5 size-3.5 shrink-0"
                        />
                        Unit bisnis disinkronkan dari Optigate. Cabang yang
                        hilang dari Optigate dinonaktifkan otomatis, bukan
                        dihapus, agar riwayat datanya tetap utuh.
                    </p>
                </section>

                <div>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={tenantIndex()}>
                            <ArrowLeft aria-hidden />
                            Kembali ke Unit Bisnis
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

ShowTenant.layout = {
    breadcrumbs: [{ title: 'Unit Bisnis', href: '/tenants' }, { title: 'Detail' }],
    title: 'Detail Unit Bisnis',
    description: 'Identitas, status, dan penggunaan data cabang',
    actions: BranchActions,
};

BranchActions.displayName = 'BranchActions';
