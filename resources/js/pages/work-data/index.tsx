import { Head, Link, router, usePage } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import {
    Building2,
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    Database,
    FileCheck2,
} from 'lucide-react';
import { show as showWorkData } from '@/routes/work-data';
import { index as workDataIndexRoute } from '@/routes/work-data';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { useRequiredTenant } from '@/lib/tenant';

type WorkDataRow = {
    id: string;
    no_kerja: string;
    status_pekerjaan: string;
    tgl_mulai_aktual: string | null;
    tgl_selesai_aktual: string | null;
    jam_pengerjaan: string | null;
    finalized_at: string | null;
    department: { nama_department: string | null } | null;
};

type DepartmentOption = { id: string; nama_department: string | null };

type StatusOption = { value: string; label: string };

type Filters = {
    department_id: string | null;
    status: string | null;
    from: string | null;
    to: string | null;
};

type Pagination = {
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type PageProps = InertiaConfig['sharedPageProps'] & {
    workData: Pagination & { data: WorkDataRow[] };
    filter_options: {
        departments: DepartmentOption[];
        statuses: StatusOption[];
    };
    filters: Filters;
};

const formatDate = (value: string | null): string =>
    value
        ? new Date(value).toLocaleDateString('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          })
        : '—';

/**
 * Terapkan filter dengan router.get — state filter tinggal di URL sehingga
 * hasil bisa dibagikan dan tombol kembali bekerja seperti yang diharapkan.
 */
function applyFilters(tenant: string, next: Filters): void {
    const query: Record<string, string> = {};

    if (next.department_id) query.department_id = next.department_id;
    if (next.status) query.status = next.status;
    if (next.from) query.from = next.from;
    if (next.to) query.to = next.to;

    router.get(
        workDataIndexRoute.url({ tenant }, { query }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function FilterBar({
    filters,
    filterOptions,
    tenant,
}: {
    filters: Filters;
    filterOptions: PageProps['filter_options'];
    tenant: string;
}) {
    const set = (patch: Partial<Filters>): void =>
        applyFilters(tenant, { ...filters, ...patch });

    const isFiltered =
        filters.department_id !== null ||
        filters.status !== null ||
        filters.from !== null ||
        filters.to !== null;

    return (
        <div className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-4 shadow-card">
            <div className="space-y-1.5">
                <Label htmlFor="wd-filter-department">Department</Label>
                <Select
                    value={filters.department_id ?? 'all'}
                    onValueChange={(value) =>
                        set({
                            department_id:
                                value === 'all' ? null : value,
                        })
                    }
                >
                    <SelectTrigger id="wd-filter-department" className="w-48 bg-card">
                        <SelectValue placeholder="Semua department" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua department</SelectItem>
                        {filterOptions.departments.map((department) => (
                            <SelectItem key={department.id} value={department.id}>
                                {department.nama_department ?? '—'}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-1.5">
                <Label htmlFor="wd-filter-status">Status</Label>
                <Select
                    value={filters.status ?? 'all'}
                    onValueChange={(value) =>
                        set({ status: value === 'all' ? null : value })
                    }
                >
                    <SelectTrigger id="wd-filter-status" className="w-40 bg-card">
                        <SelectValue placeholder="Semua status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua status</SelectItem>
                        {filterOptions.statuses.map((status) => (
                            <SelectItem key={status.value} value={status.value}>
                                {status.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-1.5">
                <Label htmlFor="wd-filter-from">Selesai dari</Label>
                <Input
                    id="wd-filter-from"
                    type="date"
                    className="w-40 bg-card"
                    value={filters.from ?? ''}
                    onChange={(event) => set({ from: event.target.value })}
                />
            </div>

            <div className="space-y-1.5">
                <Label htmlFor="wd-filter-to">s/d</Label>
                <Input
                    id="wd-filter-to"
                    type="date"
                    className="w-40 bg-card"
                    value={filters.to ?? ''}
                    onChange={(event) => set({ to: event.target.value })}
                />
            </div>

            {isFiltered && (
                <Button
                    variant="outline"
                    size="sm"
                    className="h-9 bg-card"
                    onClick={() =>
                        applyFilters(tenant, {
                            department_id: null,
                            status: null,
                            from: null,
                            to: null,
                        })
                    }
                >
                    Reset
                </Button>
            )}
        </div>
    );
}

function PaginationFooter({ pagination }: { pagination: Pagination }) {
    const goTo = (url: string | null) => {
        if (!url) return;

        router.get(url, {}, { preserveScroll: true, preserveState: true });
    };

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-surface-raised/60 px-4 py-2.5">
            <p className="text-[11px] text-ink-muted" aria-live="polite">
                {pagination.from ?? 0}–{pagination.to ?? 0} dari{' '}
                {pagination.total} work data
            </p>
            <div className="flex items-center gap-1.5">
                <Button
                    variant="outline"
                    size="sm"
                    className="h-8 bg-card px-2.5 sm:px-3"
                    disabled={!pagination.prev_page_url}
                    onClick={() => goTo(pagination.prev_page_url)}
                    aria-label="Halaman sebelumnya"
                >
                    <ChevronLeft aria-hidden="true" />
                    <span className="hidden sm:inline">Sebelumnya</span>
                </Button>
                <span className="px-1.5 font-mono text-[11px] text-ink-muted tabular-nums">
                    {pagination.current_page} / {pagination.last_page}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-8 bg-card px-2.5 sm:px-3"
                    disabled={!pagination.next_page_url}
                    onClick={() => goTo(pagination.next_page_url)}
                    aria-label="Halaman berikutnya"
                >
                    <span className="hidden sm:inline">Selanjutnya</span>
                    <ChevronRight aria-hidden="true" />
                </Button>
            </div>
        </div>
    );
}

WorkDataIndex.layout = {
    breadcrumbs: [{ title: 'Work Data' }],
    title: 'Work Data',
    description: 'Histori pekerjaan per Work Order yang telah selesai.',
};

export default function WorkDataIndex(props: PageProps) {
    const { workData, filter_options, filters } = props;
    const tenant = useRequiredTenant('work-data.index');
    const { data, ...pagination } = workData;

    return (
        <>
            <Head title="Work Data" />

            <div className="space-y-5">
                <FilterBar
                    filters={filters}
                    filterOptions={filter_options}
                    tenant={tenant}
                />

                {data.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border-strong/60 bg-card px-6 py-16 text-center shadow-card">
                        <span
                            aria-hidden="true"
                            className="mx-auto flex size-12 items-center justify-center rounded-xl bg-brand/10 text-brand"
                        >
                            <Database className="size-5" />
                        </span>
                        <p className="mt-4 text-base font-semibold tracking-[-0.01em] text-ink">
                            Belum ada work data
                        </p>
                        <p className="mx-auto mt-1.5 max-w-sm text-sm leading-relaxed text-ink-muted">
                            Record muncul otomatis setiap kali sebuah Work
                            Order selesai diverifikasi HOD. Filter di atas
                            bisa disesuaikan untuk mencari histori lama.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-border bg-card shadow-card">
                        <ul className="divide-y divide-border">
                            {data.map((row) => {
                                const hasAnalysis =
                                    row.finalized_at !== null;

                                return (
                                    <li key={row.id}>
                                        <Link
                                            href={showWorkData({
                                                tenant,
                                                workData: row.id,
                                            })}
                                            className="group flex items-start justify-between gap-4 px-4 py-3.5 transition-colors hover:bg-surface-raised focus-visible:bg-surface-raised focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-inset focus-visible:outline-none"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate font-mono text-[10px] font-medium tracking-[0.14em] text-ink-muted uppercase">
                                                    {row.no_kerja}
                                                </p>
                                                <p className="mt-0.5 flex items-center gap-2 truncate text-[14.5px] font-semibold tracking-[-0.01em] text-ink group-hover:text-brand">
                                                    <Building2
                                                        aria-hidden="true"
                                                        className="size-3.5 shrink-0 text-ink-muted"
                                                    />
                                                    {row.department
                                                        ?.nama_department ??
                                                        '—'}
                                                </p>
                                                <p className="mt-1 flex items-center gap-1.5 text-[11.5px] text-ink-muted tabular-nums">
                                                    <CalendarClock
                                                        aria-hidden="true"
                                                        className="size-3.5"
                                                    />
                                                    {formatDate(
                                                        row.tgl_selesai_aktual,
                                                    )}
                                                    {' · '}
                                                    {row.jam_pengerjaan ??
                                                        '—'}{' '}
                                                    jam
                                                </p>
                                            </div>

                                            <span
                                                className={cn(
                                                    'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap',
                                                    hasAnalysis
                                                        ? 'border-success/30 bg-success-subtle text-success-ink'
                                                        : 'border-warning/30 bg-warning-subtle text-warning-ink',
                                                )}
                                            >
                                                <FileCheck2
                                                    aria-hidden="true"
                                                    className="size-3"
                                                />
                                                {hasAnalysis
                                                    ? 'Tersimpulan'
                                                    : 'Menunggu analitik'}
                                            </span>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>

                        {pagination.last_page > 1 && (
                            <PaginationFooter pagination={pagination} />
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
