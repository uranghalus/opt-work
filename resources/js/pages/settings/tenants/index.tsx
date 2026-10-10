import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    ChevronLeft,
    ChevronRight,
    LoaderCircle,
    RefreshCw,
    Search,
    SearchX,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { index, show, sync } from '@/routes/tenants';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function StatusBadge({ active }: { active: boolean }) {
    return (
        <span
            className={
                active
                    ? 'inline-flex items-center gap-1.5 rounded-full border border-success/30 bg-success/10 px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-success'
                    : 'inline-flex items-center gap-1.5 rounded-full border border-border bg-surface-raised px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-ink-muted'
            }
        >
            <span
                aria-hidden
                className={`size-1.5 rounded-full ${active ? 'bg-success' : 'bg-ink-subtle'}`}
            />
            {active ? 'Aktif' : 'Nonaktif'}
        </span>
    );
}

function BranchRow({ branch }: { branch: Branch }) {
    return (
        <Link
            href={show(branch.code)}
            className="flex min-h-14 items-center gap-4 border-b border-border px-4 transition-colors last:border-b-0 hover:bg-surface-raised md:min-h-12 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-ink">
                    {branch.name}
                </p>
                <p className="mt-0.5 flex items-center gap-2 text-xs text-ink-muted">
                    <span className="font-mono">{branch.code}</span>
                    {branch.optigate_company_id !== null && (
                        <>
                            <span aria-hidden>·</span>
                            <span className="font-mono">
                                Optigate #{branch.optigate_company_id}
                            </span>
                        </>
                    )}
                </p>
            </div>

            <div className="shrink-0 text-right">
                <StatusBadge active={branch.is_active} />
                {!branch.is_active && branch.deactivated_at && (
                    <p className="mt-0.5 text-xs text-ink-muted">
                        Nonaktif {formatDate(branch.deactivated_at)}
                    </p>
                )}
            </div>

            <ChevronRight
                aria-hidden
                className="size-4 shrink-0 text-ink-subtle"
            />
        </Link>
    );
}

/** Primary action of this page, rendered in the shell's heading band. */
function SyncButton() {
    const [syncing, setSyncing] = useState(false);

    const runSync = () => {
        if (syncing) {
            return;
        }

        setSyncing(true);
        router.post(
            sync(),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSyncing(false),
            },
        );
    };

    return (
        <Button onClick={runSync} disabled={syncing}>
            {syncing ? (
                <LoaderCircle aria-hidden className="animate-spin" />
            ) : (
                <RefreshCw aria-hidden />
            )}
            {syncing ? 'Menyinkronkan…' : 'Sinkronkan'}
        </Button>
    );
}

export default function Tenants({ tenants, filters, errors }: PageProps) {
    const { flash } = usePage<FlashProps>().props;

    const [searchTerm, setSearchTerm] = useState(filters.search);
    const [applied, setApplied] = useState<Filters>(filters);
    const [navigating, setNavigating] = useState(false);
    const skipFirstQuery = useRef(true);

    // Debounce the search box before touching the URL.
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

    // Navigating back or paginating server-side replaces the page props;
    // re-align the toolbar with what the list is actually showing.
    useEffect(() => {
        setSearchTerm(filters.search);
        setApplied(filters);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tenants.current_page]);

    const clearFilters = () => {
        setSearchTerm('');
        setApplied({ search: '', status: 'all' });
    };

    const hasActiveFilters = applied.search !== '' || applied.status !== 'all';

    /** Rows of the current page, grouped by the initial letter of the name. */
    const groups = useMemo(() => {
        const byLetter = new Map<string, Branch[]>();

        for (const branch of tenants.data) {
            const letter = (branch.name?.[0] ?? '#').toUpperCase();
            byLetter.set(letter, [...(byLetter.get(letter) ?? []), branch]);
        }

        return [...byLetter.entries()];
    }, [tenants.data]);

    return (
        <>
            <Head title="Unit Bisnis" />

            <div className="space-y-6">
                {flash?.success && (
                    <Alert role="status">
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {flash?.error && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                {errors?.tenant_id && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{errors.tenant_id}</AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full max-w-xs">
                        <Search
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-subtle"
                        />
                        <label className="sr-only" htmlFor="tenant-search">
                            Cari nama atau kode unit bisnis
                        </label>
                        <Input
                            id="tenant-search"
                            type="search"
                            placeholder="Cari nama atau kode…"
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            className="h-11 pl-9"
                        />
                    </div>

                    <div
                        role="group"
                        aria-label="Filter status"
                        className="inline-flex w-fit items-center gap-0.5 rounded-md bg-surface-sunken p-1"
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
                                    className={`min-h-11 rounded-sm px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand md:min-h-9 ${
                                        isActive
                                            ? 'border border-border-strong bg-card text-ink'
                                            : 'text-ink-muted hover:text-ink'
                                    }`}
                                >
                                    {option.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div
                    className="overflow-hidden rounded-lg border bg-card transition-opacity duration-150 aria-busy:pointer-events-none aria-busy:opacity-60"
                    aria-busy={navigating}
                >
                    {tenants.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 px-6 py-16 text-center">
                            {hasActiveFilters ? (
                                <>
                                    <SearchX
                                        aria-hidden
                                        className="size-8 text-ink-subtle"
                                    />
                                    <p className="text-sm font-semibold text-ink">
                                        Tidak ada unit bisnis yang cocok.
                                    </p>
                                    <p className="text-sm text-ink-muted">
                                        Coba kata kunci lain atau bersihkan
                                        filter.
                                    </p>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={clearFilters}
                                        className="mt-2"
                                    >
                                        Bersihkan filter
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <Building2
                                        aria-hidden
                                        className="size-8 text-ink-subtle"
                                    />
                                    <p className="text-sm font-semibold text-ink">
                                        Belum ada unit bisnis.
                                    </p>
                                    <p className="max-w-sm text-sm text-ink-muted">
                                        Sinkronkan dari Optigate untuk mengisi
                                        daftar cabang secara otomatis.
                                    </p>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => router.post(sync(), {}, { preserveScroll: true })}
                                        className="mt-2"
                                    >
                                        <RefreshCw aria-hidden />
                                        Sinkronkan sekarang
                                    </Button>
                                </>
                            )}
                        </div>
                    ) : (
                        groups.map(([letter, branches]) => (
                            <section key={letter} aria-label={letter}>
                                <h2 className="border-b border-border px-4 pt-4 pb-2 text-xs font-semibold tracking-[0.08em] text-ink-muted uppercase">
                                    {letter}
                                </h2>
                                {branches.map((branch) => (
                                    <BranchRow key={branch.id} branch={branch} />
                                ))}
                            </section>
                        ))
                    )}

                    {tenants.data.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
                            <p
                                className="text-xs text-ink-muted"
                                aria-live="polite"
                            >
                                Menampilkan {tenants.from}–{tenants.to} dari{' '}
                                {tenants.total} unit bisnis
                            </p>
                            <div className="flex items-center gap-2">
                                {tenants.prev_page_url ? (
                                    <Button variant="outline" size="sm" asChild>`n                                            className="h-11 md:h-8"
                                        <Link
                                            href={tenants.prev_page_url}
                                            preserveScroll
                                        >
                                            <ChevronLeft aria-hidden />
                                            Sebelumnya
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button variant="outline" size="sm" disabled>`n                                            className="h-11 md:h-8"
                                        <ChevronLeft aria-hidden />
                                        Sebelumnya
                                    </Button>
                                )}
                                {tenants.next_page_url ? (
                                    <Button variant="outline" size="sm" asChild>`n                                            className="h-11 md:h-8"
                                        <Link
                                            href={tenants.next_page_url}
                                            preserveScroll
                                        >
                                            Selanjutnya
                                            <ChevronRight aria-hidden />
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button variant="outline" size="sm" disabled>`n                                            className="h-11 md:h-8"
                                        Selanjutnya
                                        <ChevronRight aria-hidden />
                                    </Button>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

Tenants.layout = {
    breadcrumbs: [{ title: 'Pengaturan' }, { title: 'Unit Bisnis' }],
    title: 'Unit Bisnis',
    description:
        'Cabang yang tersinkron dari Optigate. Sinkronisasi otomatis setiap pukul 01:00.',
    actions: SyncButton,
};
