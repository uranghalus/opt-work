import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Calendar, ChevronLeft, ChevronRight, Filter } from 'lucide-react';
import { create, show } from '@/routes/work-orders';
import { CategoryBadge } from '@/components/category-badge';
import { StatusBadge, type WoStatus } from '@/components/status-badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { InertiaConfig } from '@inertiajs/core';
import { useRequiredTenant } from '@/lib/tenant';
import { useState, useCallback } from 'react';

type DeadlineState = 'on_track' | 'due_soon' | 'overdue' | 'escalated';

type WorkOrder = {
    id: string;
    nomor_wo: string;
    title: string;
    description: string;
    category: 'normal' | 'accident' | 'owner';
    status: WoStatus;
    requested_schedule_date: string | null;
    scheduled_date: string | null;
    deadline_date: string | null;
    created_at: string;
    requester: { id: string; name: string } | null;
    target_department: { id: string; nama_department: string | null } | null;
    deadline_state: DeadlineState;
    deadline_label: string;
};

type PaginatedData = {
    data: WorkOrder[];
    current_page: number;
    last_page: number;
};

type PageProps = {
    workOrders: PaginatedData;
};

/** Escalation chip rendered on the card when deadline_state warrants it. */
function EscalationChip({ state }: { state: DeadlineState }) {
    const chipByState: Record<DeadlineState, { label: string; className: string } | null> = {
        on_track: null,
        due_soon: {
            label: 'H+3',
            className: 'bg-warning/10 text-warning border-warning/30',
        },
        overdue: {
            label: 'H+5',
            className: 'bg-danger/10 text-danger border-danger/30',
        },
        escalated: {
            label: 'H+6 · Eskalasi',
            className: 'bg-escalation/10 text-escalation border-escalation/30',
        },
    };

    const chip = chipByState[state];
    if (!chip) return null;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-sm border px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
                chip.className,
            )}
        >
            {chip.label}
        </span>
    );
}

/** Primary action of this page, rendered in the shell's heading band. */
function WorkOrderActions() {
    const tenant = useRequiredTenant('work-orders.create');

    return (
        <Button asChild size="lg">
            <Link href={create({ tenant })}>
                <Plus aria-hidden />
                Buat Work Order
            </Link>
        </Button>
    );
}

WorkOrders.layout = {
    breadcrumbs: [{ title: 'Work Order' }],
    title: 'Work Order',
    description:
        'Work Order yang Anda buat dan yang ditujukan ke department Anda',
    actions: WorkOrderActions,
};

function DeadlineRail({ state, label }: { state: DeadlineState; label: string }) {
    const colors: Record<DeadlineState, string> = {
        on_track: 'border-success',
        due_soon: 'border-warning',
        overdue: 'border-danger',
        escalated: 'border-escalation',
    };

    return (
        <div
            className={`w-[3px] rounded-s-full ${colors[state]}`}
            aria-label={label}
            title={label}
        />
    );
}

function StatCard({
    label,
    value,
    icon,
    tone,
}: {
    label: string;
    value: number;
    icon: React.ReactNode;
    tone: string;
}) {
    return (
        <div
            className={cn(
                'rounded-xl border border-border bg-card p-4 flex items-center gap-3',
                tone,
            )}
        >
            <div className="p-1.5 rounded-md">{icon}</div>
            <div className="flex flex-col">
                <p className="text-xs text-ink-muted leading-none">{label}</p>
                <p className="text-lg font-semibold text-ink leading-none">{value}</p>
            </div>
        </div>
    );
}

function WorkOrderCard({
    workOrder,
    tenant,
}: {
    workOrder: WorkOrder;
    tenant: string;
}) {
    const isEscalated =
        workOrder.deadline_state === 'overdue' ||
        workOrder.deadline_state === 'escalated';

    return (
        <Link
            href={show({ tenant, workOrder: workOrder.id })}
            className="group block relative rounded-2xl border border-border bg-card shadow-card p-5 transition-[border-color,box-shadow] duration-base hover:shadow-raised hover:border-border-strong focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
        >
            <DeadlineRail state={workOrder.deadline_state} label={workOrder.deadline_label} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                <div className="flex flex-col gap-2 flex-1 min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <CategoryBadge category={workOrder.category} />
                        <StatusBadge status={workOrder.status} />
                        <EscalationChip state={workOrder.deadline_state} />
                    </div>

                    <h3 className="text-base font-semibold text-ink leading-snug truncate">
                        {workOrder.title}
                    </h3>

                    <p className="text-sm text-ink-muted line-clamp-2">
                        {workOrder.description}
                    </p>

                    <div className="flex flex-wrap items-center gap-3 text-sm text-ink-muted">
                        <span className="flex items-center gap-1">
                            <Calendar aria-hidden className="h-3.5 w-3.5" />
                            <span className="font-mono">
                                {workOrder.deadline_date
                                    ? new Date(workOrder.deadline_date).toLocaleDateString('id-ID', {
                                          day: 'numeric',
                                          month: 'short',
                                          year: 'numeric',
                                      })
                                    : '—'}
                            </span>
                        </span>
                        <span className={cn('font-medium', isEscalated ? 'text-danger' : 'text-ink-subtle')}>
                            {workOrder.deadline_label}
                        </span>
                        {workOrder.target_department?.nama_department && (
                            <span className="flex items-center gap-1">
                                <span>•</span>
                                {workOrder.target_department.nama_department}
                            </span>
                        )}
                    </div>
                </div>

                <div className="flex flex-col items-end gap-1 shrink-0 sm:items-end">
                    <span className="font-mono text-xs text-ink-muted tracking-wider">
                        {workOrder.nomor_wo}
                    </span>
                    {workOrder.requester?.name && (
                        <span className="text-xs text-ink-subtle">
                            Dimohon: {workOrder.requester.name}
                        </span>
                    )}
                </div>
            </div>
        </Link>
    );
}

function Pagination({
    currentPage,
    lastPage,
    baseUrl,
}: {
    currentPage: number;
    lastPage: number;
    baseUrl: string;
}) {
    if (lastPage <= 1) return null;

    const pages = Array.from({ length: lastPage }, (_, i) => i + 1);

    const goToPage = (page: number) => {
        // Inertia visit (bukan full reload) — state/progress tetap terjaga.
        router.get(baseUrl, { page: String(page) }, { preserveScroll: true });
    };

    return (
        <nav
            className="flex items-center justify-center gap-1"
            aria-label="Halaman Work Order"
        >
            <Button
                variant="secondary"
                size="sm"
                disabled={currentPage === 1}
                onClick={() => goToPage(currentPage - 1)}
                aria-label="Halaman sebelumnya"
            >
                <ChevronLeft aria-hidden className="h-4 w-4" />
            </Button>

            <div className="flex items-center gap-1" role="group" aria-label="Nomor halaman">
                {pages.map((page) => (
                    <Button
                        key={page}
                        variant={page === currentPage ? 'default' : 'secondary'}
                        size="sm"
                        onClick={() => {
                            if (page !== currentPage) {
                                goToPage(page);
                            }
                        }}
                        aria-label={`Halaman ${page}`}
                        aria-current={page === currentPage ? 'page' : undefined}
                    >
                        {page}
                    </Button>
                ))}
            </div>

            <Button
                variant="secondary"
                size="sm"
                disabled={currentPage === lastPage}
                onClick={() => goToPage(currentPage + 1)}
                aria-label="Halaman selanjutnya"
            >
                <ChevronRight aria-hidden className="h-4 w-4" />
            </Button>
        </nav>
    );
}

type FilterOption = { value: string; label: string };

const STATUS_OPTIONS: FilterOption[] = [
    { value: '', label: 'Semua status' },
    { value: 'draft', label: 'Draf' },
    { value: 'pending_dept_approval', label: 'Menunggu Persetujuan Dept' },
    { value: 'rejected_by_dept', label: 'Ditolak Dept' },
    { value: 'waiting_hod', label: 'Menunggu HOD' },
    { value: 'hod_approved', label: 'Disetujui HOD' },
    { value: 'scheduled', label: 'Terjadwal' },
    { value: 'assigned', label: 'Ditugaskan' },
    { value: 'in_progress', label: 'Dikerjakan' },
    { value: 'pending_verify', label: 'Menunggu Verifikasi' },
    { value: 'revision', label: 'Revisi' },
    { value: 'closed', label: 'Selesai' },
    { value: 'cancelled', label: 'Dibatalkan' },
    // 'overdue'/'escalated' adalah nilai deadline_state (lihat EscalationChip), bukan status WO.
    // Tidak dimasukkan ke dalam opsi filter status.
];

const CATEGORY_OPTIONS: FilterOption[] = [
    { value: '', label: 'Semua kategori' },
    { value: 'normal', label: 'Normal' },
    { value: 'accident', label: 'Urgent · Kecelakaan' },
    { value: 'owner', label: 'Urgent · Owner' },
];

const PRIORITY_OPTIONS: FilterOption[] = [
    { value: '', label: 'Semua prioritas' },
    { value: 'low', label: 'Rendah' },
    { value: 'medium', label: 'Sedang' },
    { value: 'high', label: 'Tinggi' },
];

const SORT_OPTIONS: FilterOption[] = [
    { value: 'created_desc', label: 'Terbaru' },
    { value: 'created_asc', label: 'Terlama' },
    { value: 'deadline_asc', label: 'Deadline terdekat' },
];

export default function WorkOrders({ workOrders }: PageProps) {
    const tenant = useRequiredTenant('work-orders.index');
    const baseUrl = `/${tenant}/work-orders`;
    const [openFilter, setOpenFilter] = useState(false);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [priorityFilter, setPriorityFilter] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('');
    const [sortFilter, setSortFilter] = useState('created_desc');

    const data = workOrders.data;

    // NOTE (MVP): statistik dihitung hanya dari data halaman ini (workOrders.data),
    // bukan total keseluruhan semua halaman. Payload server saat ini hanya menyertakan
    // halaman yang diminta; statistik global butuh endpoint ringkas terpisah (pasca-MVP).
    const stats = {
        total: data.length,
        completed: data.filter((w) => w.status === 'closed').length,
        inProgress: data.filter((w) => w.status === 'in_progress').length,
        pending: data.filter(
            (w) =>
                // Pending = belum selesai/dibatalkan/revisi DAN belum dikerjakan (in_progress
                // sendiri punya StatCard, jadi tidak boleh ikut terhitung di sini).
                w.status !== 'closed' &&
                w.status !== 'cancelled' &&
                w.status !== 'revision' &&
                w.status !== 'in_progress',
        ).length,
    };

    const handleFilterChange = useCallback(
        (key: string, value: string) => {
            switch (key) {
                case 'search':
                    setSearch(value);
                    break;
                case 'status':
                    setStatusFilter(value);
                    break;
                case 'category':
                    setCategoryFilter(value);
                    break;
                case 'priority':
                    setPriorityFilter(value);
                    break;
                case 'department':
                    setDepartmentFilter(value);
                    break;
                case 'sort':
                    setSortFilter(value);
                    break;
            }
        },
        [],
    );

    const activeFilters =
        (search ? 1 : 0) +
        (statusFilter ? 1 : 0) +
        (categoryFilter ? 1 : 0) +
        (priorityFilter ? 1 : 0) +
        (departmentFilter ? 1 : 0);

    return (
        <>
            <Head title="Work Order" />

            <div className="space-y-6">
                {/* Stats cards row */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Total"
                        value={stats.total}
                        icon={<Calendar className="size-4 text-ink" />}
                        tone="bg-primary/10 text-primary border-primary/30"
                    />
                    <StatCard
                        label="Selesai"
                        value={stats.completed}
                        icon={<Calendar className="size-4 text-success" />}
                        tone="bg-success/10 text-success border-success/30"
                    />
                    <StatCard
                        label="Dikerjakan"
                        value={stats.inProgress}
                        icon={<Calendar className="size-4 text-info" />}
                        tone="bg-info/10 text-info border-info/30"
                    />
                    <StatCard
                        label="Pending"
                        value={stats.pending}
                        icon={<Calendar className="size-4 text-warning" />}
                        tone="bg-warning/10 text-warning border-warning/30"
                    />
                </div>

                {workOrders.data.length === 0 ? (
                    <div className="rounded-2xl border border-border bg-card p-12 text-center">
                        <p className="text-ink-muted mb-6">
                            Belum ada Work Order.
                        </p>
                        <WorkOrderActions />
                    </div>
                ) : (
                    <div className="space-y-3">
                        {workOrders.data.map((workOrder) => (
                            <WorkOrderCard
                                key={workOrder.id}
                                workOrder={workOrder}
                                tenant={tenant}
                            />
                        ))}
                    </div>
                )}

                <Pagination
                    currentPage={workOrders.current_page}
                    lastPage={workOrders.last_page}
                    baseUrl={baseUrl}
                />

                {/* Filter modal */}
                <Dialog open={openFilter} onOpenChange={setOpenFilter}>
                    <DialogTrigger asChild>
                        <Button variant="outline" size="sm" onClick={() => setOpenFilter(true)}>
                            <Filter aria-hidden className="size-4" />
                            {activeFilters > 0 ? (
                                <span className="font-medium">
                                    Filter ({activeFilters})
                                </span>
                            ) : (
                                <span className="font-medium">Filter</span>
                            )}
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-md p-6">
                        <DialogHeader>
                            <DialogTitle>Filter Work Order</DialogTitle>
                            <DialogDescription>
                                Atur tampilan daftar Work Order.
                            </DialogDescription>
                        </DialogHeader>
                        <form className="space-y-5">
                            <div className="space-y-2">
                                <Label htmlFor="filter-search">Cari</Label>
                                <Input
                                    id="filter-search"
                                    placeholder="Nomor WO, judul, atau deskripsi..."
                                    value={search}
                                    onChange={(e) =>
                                        handleFilterChange('search', e.target.value)
                                    }
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="filter-status">Status</Label>
                                <Select
                                    value={statusFilter}
                                    onValueChange={(value) =>
                                        handleFilterChange('status', value ?? '')
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Semua status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {STATUS_OPTIONS.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="filter-category">Kategori</Label>
                                <Select
                                    value={categoryFilter}
                                    onValueChange={(value) =>
                                        handleFilterChange('category', value ?? '')
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Semua kategori" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {CATEGORY_OPTIONS.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="filter-priority">Prioritas</Label>
                                <Select
                                    value={priorityFilter}
                                    onValueChange={(value) =>
                                        handleFilterChange('priority', value ?? '')
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Semua prioritas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PRIORITY_OPTIONS.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="filter-department">Department</Label>
                                <Select
                                    value={departmentFilter}
                                    onValueChange={(value) =>
                                        handleFilterChange('department', value ?? '')
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Semua department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {data
                                            .map(
                                                (w) =>
                                                    w.target_department?.nama_department,
                                            )
                                            .filter(
                                                (v, i, arr) =>
                                                    v !== null &&
                                                    arr.indexOf(v) === i,
                                            )
                                            .map((v) => (
                                                <SelectItem
                                                    key={v}
                                                    value={v ?? ''}
                                                >
                                                    {v}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="filter-sort">Urutkan</Label>
                                <Select
                                    value={sortFilter}
                                    onValueChange={(value) =>
                                        handleFilterChange('sort', value ?? '')
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Terbaru" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {SORT_OPTIONS.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setSearch('');
                                        setStatusFilter('');
                                        setCategoryFilter('');
                                        setPriorityFilter('');
                                        setDepartmentFilter('');
                                        setSortFilter('created_desc');
                                        setOpenFilter(false);
                                    }}
                                >
                                    Reset
                                </Button>
                                <Button onClick={() => setOpenFilter(false)}>
                                    Terapkan
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
