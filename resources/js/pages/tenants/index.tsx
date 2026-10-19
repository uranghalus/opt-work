import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    LoaderCircle,
    RefreshCw,
    Search,
    SearchX,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { index, show, sync } from '@/routes/tenants';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { InertiaConfig } from '@inertiajs/core';

type Branch = {
    id: string;
    optigate_company_id: number | null;
    code: string;
    name: string;
    is_active: boolean;
    deactivated_at: string | null;
};

type Paginator = {
    data: Branch[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filters = {
    search: string;
    status: string;
};

type PageProps = {
    tenants: Paginator;
    filters: Filters;
    errors?: Record<string, string>;
};

type FlashProps = InertiaConfig['sharedPageProps'] & {
    flash?: { success?: string | null; error?: string | null };
};

const STATUS_OPTIONS = [
    { value: 'all', label: 'Semua' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
] as const;

function StatusBadge({ active }: { active: boolean }) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider',
                active
                    ? 'border-success/25 bg-success-subtle text-success'
                    : 'border-border bg-surface-sunken text-ink-subtle',
            )}
        >
            <span
                aria-hidden
                className={cn(
                    'size-1.5 rounded-full',
                    active ? 'bg-success' : 'bg-ink-subtle',
                )}
            />
            {active ? 'Aktif' : 'Nonaktif'}
        </Badge>
    );
}

function SyncButton() {
    const [syncing, setSyncing] = useState(false);
    const { tenants } = usePage<PageProps>().props;

    const runSync = useCallback(() => {
        if (syncing) return;
        setSyncing(true);
        router.post(
            sync(),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSyncing(false),
            },
        );
    }, [syncing]);

    return (
        <Button onClick={runSync} disabled={syncing || tenants.total === 0}>
            {syncing ? (
                <LoaderCircle aria-hidden className="animate-spin" />
            ) : (
                <RefreshCw aria-hidden />
            )}
            Sinkronkan Optigate
        </Button>
    );
}

function UnitCard({ branch }: { branch: Branch }) {
    const isActive = branch.is_active;

    return (
        <Link
            href={show(branch.code)}
            className={cn(
                'group relative flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-card outline-none transition-all duration-200 ease-standard',
                'hover:-translate-y-0.5 hover:border-brand/40 hover:shadow-raised focus-visible:ring-[3px] focus-visible:ring-ring/40',
                !isActive && 'opacity-70 hover:opacity-100',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-3">
                    <span
                        aria-hidden
                        className={cn(
                            'flex size-11 shrink-0 items-center justify-center rounded-lg transition-colors',
                            isActive
                                ? 'bg-brand/10 text-brand group-hover:bg-brand/15'
                                : 'bg-surface-sunken text-ink-subtle',
                        )}
                    >
                        <Building2 className="size-5" />
                    </span>
                    <span className="min-w-0">
                        <span className="block truncate text-sm font-semibold tracking-tight text-ink">
                            {branch.name}
                        </span>
                        <span className="mt-0.5 block font-mono text-xs text-ink-muted">
                            {branch.code}
                        </span>
                    </span>
                </div>
                <StatusBadge active={isActive} />
            </div>

            <div className="flex items-center justify-between border-t border-border pt-3">
                <span className="text-[11px] text-ink-subtle">
                    {branch.optigate_company_id
                        ? `Optigate #${branch.optigate_company_id}`
                        : 'Tanpa ID Optigate'}
                </span>
                <span
                    className={cn(
                        'inline-flex items-center gap-1 text-xs font-semibold transition-all duration-200',
                        isActive
                            ? 'text-brand group-hover:translate-x-0.5'
                            : 'text-ink-subtle',
                    )}
                >
                    {isActive ? 'Kelola unit' : 'Lihat detail'}
                    <ArrowRight
                        aria-hidden
                        className="size-3.5 transition-transform duration-200 group-hover:translate-x-0.5"
                    />
                </span>
            </div>
        </Link>
    );
}

export default function Tenants({ tenants, filters, errors }: PageProps) {
    const { flash } = usePage<FlashProps>().props;

    const [searchTerm, setSearchTerm] = useState(filters.search);
    const [applied, setApplied] = useState<Filters>(filters);
    const [navigating, setNavigating] = useState(false);
    const skipFirstQuery = useRef(true);

    useEffect(() => {
        const timer = setTimeout(() => {
            setApplied((current) =>
                current.search === searchTerm
                    ? current
                    : { ...current, search: searchTerm },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [searchTerm]);

    useEffect(() => {
        if (skipFirstQuery.current) {
            skipFirstQuery.current = false;
            return;
        }

        router.get(
            index(),
            { search: applied.search, status: applied.status },
            {
                preserveState: true,
                replace: true,
                onStart: () => setNavigating(true),
                onFinish: () => setNavigating(false),
            },
        );
    }, [applied]);

    useEffect(() => {
        setSearchTerm(filters.search);
        setApplied(filters);
    }, [tenants.current_page]);

    const clearFilters = () => {
        setSearchTerm('');
        setApplied({ search: '', status: 'all' });
    };

    const hasActiveFilters = applied.search !== '' || applied.status !== 'all';

    return (
        <>
            <Head title="Unit Bisnis" />

            <div className="space-y-5">
                {flash?.success && (
                    <Alert
                        role="status"
                        className="rounded-lg border-success/25 bg-success-subtle text-success"
                    >
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {flash?.error && (
                    <Alert
                        role="alert"
                        className="rounded-lg border-danger/25 bg-danger-subtle text-danger"
                    >
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                {errors?.tenant_id && (
                    <Alert
                        role="alert"
                        className="rounded-lg border-danger/25 bg-danger-subtle text-danger"
                    >
                        <AlertDescription>{errors.tenant_id}</AlertDescription>
                    </Alert>
                )}

                {/* Filter bar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="group relative w-full max-w-sm">
                        <Search
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-subtle transition-colors group-focus-within:text-brand"
                        />
                        <label className="sr-only" htmlFor="tenant-search">
                            Cari nama atau kode unit bisnis
                        </label>
                        <Input
                            id="tenant-search"
                            type="search"
                            placeholder="Cari nama atau kode..."
                            value={searchTerm}
                            onChange={(event) =>
                                setSearchTerm(event.target.value)
                            }
                            className="h-10 rounded-lg bg-card pl-9"
                        />
                    </div>

                    <div
                        role="group"
                        aria-label="Filter status"
                        className="inline-flex w-fit items-center gap-1 rounded-lg border border-border bg-card p-1"
                    >
                        {STATUS_OPTIONS.map((option) => {
                            const isActive = applied.status === option.value;

                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    aria-pressed={isActive}
                                    onClick={() =>
                                        setApplied((current) => ({
                                            ...current,
                                            status: option.value,
                                        }))
                                    }
                                    className={cn(
                                        'h-8 rounded-md px-3.5 text-xs font-semibold transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40',
                                        isActive
                                            ? 'bg-brand text-white shadow-card'
                                            : 'text-ink-muted hover:bg-surface-sunken hover:text-ink',
                                    )}
                                >
                                    {option.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Result grid */}
                <div className="relative" aria-busy={navigating}>
                    {navigating && (
                        <div className="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-card/70 backdrop-blur-[2px]">
                            <LoaderCircle
                                aria-hidden
                                className="size-6 animate-spin text-brand"
                            />
                        </div>
                    )}

                    {tenants.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card px-6 py-20 text-center">
                            {hasActiveFilters ? (
                                <>
                                    <div className="mb-4 flex size-12 items-center justify-center rounded-full bg-surface-sunken">
                                        <SearchX
                                            aria-hidden
                                            className="size-5 text-ink-subtle"
                                        />
                                    </div>
                                    <p className="text-base font-semibold tracking-tight text-ink">
                                        Hasil tidak ditemukan
                                    </p>
                                    <p className="mt-1 max-w-xs text-sm text-ink-muted">
                                        Coba kata kunci lain atau reset filter
                                        untuk melihat semua unit bisnis.
                                    </p>
                                    <Button
                                        variant="outline"
                                        onClick={clearFilters}
                                        className="mt-5"
                                    >
                                        Reset Filter
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <div className="mb-4 flex size-12 items-center justify-center rounded-full bg-brand/10">
                                        <Building2
                                            aria-hidden
                                            className="size-5 text-brand"
                                        />
                                    </div>
                                    <p className="text-base font-semibold tracking-tight text-ink">
                                        Belum ada unit bisnis
                                    </p>
                                    <p className="mt-1 max-w-sm text-sm text-ink-muted">
                                        Unit bisnis disinkronkan dari Optigate.
                                        Jalankan sinkronisasi untuk menarik data
                                        perusahaan pertama kali.
                                    </p>
                                </>
                            )}
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {tenants.data.map((branch) => (
                                <UnitCard key={branch.id} branch={branch} />
                            ))}
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {tenants.data.length > 0 && (
                    <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                        <p className="text-xs text-ink-muted">
                            {tenants.from != null && tenants.to != null
                                ? `Menampilkan ${tenants.from}–${tenants.to} dari ${tenants.total} unit bisnis`
                                : `${tenants.total} unit bisnis`}
                        </p>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!tenants.prev_page_url}
                                onClick={() =>
                                    router.get(
                                        tenants.prev_page_url ?? index(),
                                        undefined,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <ArrowLeft aria-hidden />
                                Sebelumnya
                            </Button>
                            <span className="px-1 text-xs font-medium tabular-nums text-ink-muted">
                                {tenants.current_page} / {tenants.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!tenants.next_page_url}
                                onClick={() =>
                                    router.get(
                                        tenants.next_page_url ?? index(),
                                        undefined,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Selanjutnya
                                <ArrowRight aria-hidden />
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

Tenants.layout = {
    breadcrumbs: [{ title: 'Unit Bisnis' }],
    title: 'Unit Bisnis',
    description:
        'Semua cabang perusahaan, tersinkron dari Optigate — pilih unit untuk melihat detail dan penggunaan datanya.',
    actions: SyncButton,
};

SyncButton.displayName = 'SyncButton';
