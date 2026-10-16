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
import { useEffect, useRef, useState } from 'react';
import { index, show, sync } from '@/routes/tenants';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-widest transition-all duration-300',
                active 
                    ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 shadow-[0_0_10px_rgba(6,182,212,0.2)]' 
                    : 'bg-slate-800 text-slate-500 border border-slate-700'
            )}
        >
            <span className={cn(
                'size-1.5 rounded-full animate-pulse', 
                active ? 'bg-cyan-400 shadow-[0_0_8px_rgba(34,211,238,1)]' : 'bg-slate-600'
            )} />
            {active ? 'Aktif' : 'Nonaktif'}
        </span>
    );
}

function UnitCard({ branch }: { branch: Branch }) {
    const isActive = branch.is_active;

    return (
        <Link
            href={show(branch.code)}
            className={cn(
                'group relative flex flex-col p-6 transition-all duration-300 rounded-3xl border-2 outline-none overflow-hidden',
                isActive 
                    ? 'bg-slate-900 border-violet-500/30 hover:border-violet-400 hover:shadow-[0_0_30px_rgba(139,92,246,0.2)] hover:-translate-y-1' 
                    : 'bg-slate-900/40 border-slate-800 opacity-60 grayscale hover:grayscale-0 hover:opacity-100 hover:border-slate-700'
            )}
        >
            {/* Neon Accent Glow */}
            {isActive && (
                <div className="absolute -top-12 -right-12 size-24 bg-violet-600/20 blur-3xl group-hover:bg-violet-600/40 transition-all duration-500" />
            )}

            <div className="flex justify-between items-start mb-6 relative z-10">
                <div className={cn(
                    'p-3 rounded-2xl transition-all duration-300',
                    isActive ? 'bg-violet-500/20 text-violet-400 group-hover:scale-110 group-hover:rotate-3' : 'bg-slate-800 text-slate-500'
                )}>
                    <Building2 className="size-6" />
                </div>
                <StatusBadge active={isActive} />
            </div>

            <div className="space-y-2 relative z-10">
                <h3 className={cn(
                    'text-xl font-extrabold leading-tight transition-all duration-300',
                    isActive ? 'text-white group-hover:text-violet-300' : 'text-slate-400'
                )}>
                    {branch.name}
                </h3>
                <div className="flex items-center gap-2">
                    <span className={cn(
                        'font-mono text-xs px-2 py-1 rounded-lg border transition-colors',
                        isActive ? 'bg-violet-500/10 text-violet-300 border-violet-500/20' : 'bg-slate-800 text-slate-500 border-slate-700'
                    )}>
                        {branch.code}
                    </span>
                    {branch.optigate_company_id !== null && (
                        <span className="text-[10px] text-slate-500 font-medium">
                            OPT-ID: {branch.optigate_company_id}
                        </span>
                    )}
                </div>
            </div>

            <div className="mt-8 pt-4 border-t border-slate-800 flex items-center justify-between relative z-10">
                <span className={cn(
                    'text-xs font-bold uppercase tracking-tighter transition-all duration-300',
                    isActive ? 'text-violet-400 group-hover:text-white group-hover:translate-x-1' : 'text-slate-600'
                )}>
                    Enter Unit
                </span>
                <ChevronRight className={cn(
                    'size-5 transition-all duration-300',
                    isActive ? 'text-violet-500 group-hover:text-white group-hover:translate-x-1' : 'text-slate-700'
                )} />
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
        <div className="min-h-screen bg-slate-950 text-slate-100 p-4 sm:p-8">
            <Head title="Unit Bisnis" />

            <div className="max-w-7xl mx-auto space-y-12">
                {flash?.success && (
                    <Alert role="status" className="border-emerald-500/30 bg-emerald-500/10 text-emerald-400 rounded-2xl">
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full max-w-md group">
                        <Search
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-500 group-focus-within:text-violet-400 transition-colors"
                        />
                        <label className="sr-only" htmlFor="tenant-search">
                            Cari nama atau kode unit bisnis
                        </label>
                        <Input
                            id="tenant-search"
                            type="search"
                            placeholder="Cari unit bisnis..."
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            className="h-14 pl-12 rounded-2xl bg-slate-900 border-slate-800 text-white placeholder:text-slate-600 focus-visible:ring-violet-500 focus-visible:border-violet-500 transition-all duration-300 shadow-inner"
                        />
                    </div>

                    <div
                        role="group"
                        aria-label="Filter status"
                        className="inline-flex w-fit items-center gap-2 rounded-2xl bg-slate-900 p-2 border border-slate-800 shadow-xl"
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
                                        'h-10 rounded-xl px-6 py-2 text-xs font-bold transition-all duration-300',
                                        isActive
                                            ? 'bg-violet-600 text-white shadow-[0_0_15px_rgba(139,92,246,0.4)] scale-105'
                                            : 'text-slate-500 hover:text-slate-300 hover:bg-slate-800'
                                    )}
                                >
                                    {option.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div
                    className="relative min-h-[500px]"
                    aria-busy={navigating}
                >
                    {navigating && (
                        <div className="absolute inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-md transition-opacity rounded-3xl">
                            <div className="flex flex-col items-center gap-4">
                                <LoaderCircle className="size-12 animate-spin text-violet-500" />
                                <span className="text-violet-400 font-bold animate-pulse tracking-widest uppercase text-xs">
                                    Synchronizing
                                </span>
                            </div>
                        </div>
                    )}

                    {tenants.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-3xl border-2 border-dashed border-slate-800 bg-slate-900/30 px-6 py-32 text-center">
                            {hasActiveFilters ? (
                                <>
                                    <div className="mb-6 rounded-full bg-slate-900 p-6 border border-slate-800">
                                        <SearchX
                                            aria-hidden
                                            className="size-12 text-slate-600"
                                        />
                                    </div>
                                    <p className="text-2xl font-extrabold text-white">
                                        Hasil tidak ditemukan.
                                    </p>
                                    <p className="max-w-xs text-slate-500 mt-2">
                                        Coba kata kunci lain atau reset filter untuk melihat unit bisnis lainnya.
                                    </p>
                                    <Button
                                        variant="outline"
                                        size="lg"
                                        onClick={clearFilters}
                                        className="mt-8 rounded-full border-slate-700 text-slate-300 hover:bg-slate-800 hover:text-white transition-all duration-300"
                                    >
                                        Reset Filter
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <div className="mb-6 rounded-full bg-violet-500/10 p-6 border border-violet-500/20">
                                        <Building2
                                            aria-hidden
                                            className="size-12 text-violet-500"
                                        />
                                    </div>
                                    <p className="text-2xl font-extrabold text-white">
                                        Unit Bisnis Kosong.
                                    </p>
                                    <p className="max-w-sm text-slate-500 mt-2">
                                        Lakukan sinkronisasi data dari Optigate untuk menampilkan daftar cabang.
                                    </p>
                                    <Button
                                        variant="outline"
                                        size="lg"
                                        onClick={() => router.post(sync(), {}, { preserveScroll: true })}
                                        className="mt-8 rounded-full bg-violet-600 text-white border-transparent hover:bg-violet-500 transition-all duration-300 shadow-lg shadow-violet-600/20"
                                    >
                                        <RefreshCw aria-hidden className="mr-2 size-5" />
                                        Sinkronkan Sekarang
                                    </Button>
                                </>
                            )}
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {tenants.data.map((branch) => (
                                <UnitCard key={branch.id} branch={branch} />
                            ))}
                        </div>
                    )}
                </div>

                {tenants.data.length > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-800 bg-slate-900/50 p-4 backdrop-blur-sm">
                        <p
                            className="text-xs font-medium text-slate-500"
                            aria-live="polite"
                        >
                            Showing <span className="text-slate-200 font-bold">{tenants.from}–{tenants.to}</span> of{' '}
                            <span className="text-slate-200 font-bold">{tenants.total}</span> units
                        </p>
                        <div className="flex items-center gap-2">
                            {tenants.prev_page_url ? (
                                <Button variant="outline" size="sm" asChild className="h-9 rounded-xl border-slate-800 text-slate-400 hover:bg-slate-800 hover:text-white transition-all duration-200">
                                    <Link
                                        href={tenants.prev_page_url}
                                        preserveScroll
                                    >
                                        <ChevronLeft aria-hidden className="mr-1 size-4" />
                                        Prev
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled className="h-9 rounded-xl opacity-30 cursor-not-allowed">
                                    <ChevronLeft aria-hidden className="mr-1 size-4" />
                                    Prev
                                </Button>
                            )}
                            {tenants.next_page_url ? (
                                <Button variant="outline" size="sm" asChild className="h-9 rounded-xl border-slate-800 text-slate-400 hover:bg-slate-800 hover:text-white transition-all duration-200">
                                    <Link
                                        href={tenants.next_page_url}
                                        preserveScroll
                                    >
                                        Next
                                        <ChevronRight aria-hidden className="ml-1 size-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled className="h-9 rounded-xl opacity-30 cursor-not-allowed">
                                    Next
                                    <ChevronRight aria-hidden className="ml-1 size-4" />
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

Tenants.layout = {
    breadcrumbs: [{ title: 'Pengaturan' }, { title: 'Unit Bisnis' }],
    title: 'Unit Bisnis',
    description:
        'Visualisasi unit bisnis perusahaan dengan sinkronisasi data real-time.',
    actions: null,
};
