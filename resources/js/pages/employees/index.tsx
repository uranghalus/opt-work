import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Plus,
    Search,
    SearchX,
    LoaderCircle,
    RefreshCw,
    Eye,
    Edit2,
    Trash2,
    Download,
    Columns,
    ChevronLeft,
    ChevronRight,
    User,
    Mail,
    Phone,
    Calendar,
    Building2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState, useCallback } from 'react';
import { index, create, edit, show, destroy, sync } from '@/routes/employees';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuCheckboxItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { Separator } from '@/components/ui/separator';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { InertiaConfig } from '@inertiajs/core';

type Department = {
    id: string;
    kode_department: string;
    nama_department: string | null;
};

type Position = {
    id: string;
    nama_position: string;
};

type Division = {
    id: string;
    kode_division: string | null;
    nama_division: string;
};

type User = {
    id: string;
    name: string;
    is_active: boolean;
};

type Employee = {
    id: string;
    nik_employee: string | null;
    nama_employee: string;
    email: string | null;
    number: string | null;
    photo_url: string | null;
    created_at: string | null;
    department: Department | null;
    position: Position | null;
    division: Division | null;
    user: User | null;
};

type Paginator = {
    data: Employee[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    per_page: number;
};

type Filters = {
    search: string;
    per_page: number;
    sort: string;
    direction: string;
};

type PageProps = {
    employees: Paginator;
    filters: Filters;
    errors?: Record<string, string>;
};

type FlashProps = InertiaConfig['sharedPageProps'] & {
    flash?: { success?: string | null; error?: string | null };
};

const PER_PAGE_OPTIONS = [10, 25, 50, 100] as const;
const SORT_OPTIONS = [
    { value: 'nama_employee', label: 'Nama' },
    { value: 'nik_employee', label: 'NIK' },
    { value: 'email', label: 'Email' },
    { value: 'number', label: 'Telepon' },
    { value: 'created_at', label: 'Bergabung' },
] as const;

const DEFAULT_VISIBLE_COLUMNS = [
    'nik',
    'name',
    'department',
    'position',
    'division',
    'phone',
    'email',
    'joined',
    'status',
] as const;

type ColumnKey = (typeof DEFAULT_VISIBLE_COLUMNS)[number];

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function Avatar({ src, name, size = 32 }: { src?: string | null; name: string; size?: number }) {
    const initials = getInitials(name);
    const bgColors = [
        'bg-brand-soft text-brand',
        'bg-info-subtle text-info',
        'bg-warning-subtle text-warning',
        'bg-success-subtle text-success',
        'bg-danger-subtle text-danger',
        'bg-escalation-subtle text-escalation',
    ];
    const colorIndex = initials.charCodeAt(0) % bgColors.length;

    if (src) {
        return (
            <img
                src={src}
                alt={name}
                className={cn('rounded-full object-cover', `w-${size / 4} h-${size / 4}`)}
            />
        );
    }

    return (
        <div
            className={cn(
                'rounded-full flex items-center justify-center font-medium text-xs',
                `w-${size / 4} h-${size / 4}`,
                bgColors[colorIndex]
            )}
            aria-label={name}
        >
            {initials}
        </div>
    );
}

function StatusBadge({ active }: { active: boolean }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
                active
                    ? 'border-success/30 bg-success/10 text-success'
                    : 'border-border bg-surface-raised text-ink-muted'
            )}
        >
            <span aria-hidden className={cn('size-1.5 rounded-full', active ? 'bg-success' : 'bg-ink-subtle')} />
            {active ? 'Aktif' : 'Nonaktif'}
        </span>
    );
}

function SkeletonRow() {
    return (
        <TableRow>
            <TableCell className="font-mono text-sm"><Skeleton className="w-24 h-4" /></TableCell>
            <TableCell className="font-semibold"><Skeleton className="w-32 h-5" /></TableCell>
            <TableCell className="font-mono text-sm text-muted-foreground"><Skeleton className="w-20 h-4" /></TableCell>
            <TableCell className="text-muted-foreground"><Skeleton className="w-28 h-4" /></TableCell>
            <TableCell className="text-muted-foreground"><Skeleton className="w-24 h-4" /></TableCell>
            <TableCell className="text-muted-foreground"><Skeleton className="w-32 h-4" /></TableCell>
            <TableCell className="text-muted-foreground"><Skeleton className="w-24 h-4" /></TableCell>
            <TableCell><Skeleton className="w-20 h-5" /></TableCell>
            <TableCell className="w-24"><Skeleton className="w-20 h-8" /></TableCell>
        </TableRow>
    );
}

const COLUMN_DEFINITIONS: Record<ColumnKey, { label: string; icon?: React.ReactNode }> = {
    nik: { label: 'NIK', icon: <User className="size-3" /> },
    name: { label: 'Nama', icon: <User className="size-3" /> },
    department: { label: 'Department', icon: <Building2 className="size-3" /> },
    position: { label: 'Position', icon: <User className="size-3" /> },
    division: { label: 'Division', icon: <Building2 className="size-3" /> },
    phone: { label: 'Telepon', icon: <Phone className="size-3" /> },
    email: { label: 'Email', icon: <Mail className="size-3" /> },
    joined: { label: 'Bergabung', icon: <Calendar className="size-3" /> },
    status: { label: 'Status', icon: <User className="size-3" /> },
};

function EmployeeActions({
    activeTenant,
    onSync,
}: { activeTenant: string; onSync: () => void }) {
    const [syncing, setSyncing] = useState(false);

    const runSync = useCallback(() => {
        if (syncing) return;
        setSyncing(true);
        router.post(
            sync({ tenant: activeTenant }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSyncing(false),
            }
        );
    }, [activeTenant, syncing]);

    return (
        <div className="flex items-center gap-2">
            <Button onClick={onSync} disabled={syncing} variant="outline" size="sm">
                {syncing ? (
                    <>
                        <LoaderCircle aria-hidden className="animate-spin mr-1.5" />
                        Menyinkronkan…
                    </>
                ) : (
                    <>
                        <RefreshCw aria-hidden className="mr-1.5" />
                        Sinkronkan
                    </>
                )}
            </Button>
            <Button asChild size="lg">
                <Link href={create({ tenant: activeTenant })}>
                    <Plus aria-hidden className="mr-1.5" />
                    Tambah Karyawan
                </Link>
            </Button>
        </div>
    );
}

EmployeeActions.displayName = 'EmployeeActions';

function ColumnVisibilityDropdown({ visibleColumns, setVisibleColumns }: { visibleColumns: ColumnKey[]; setVisibleColumns: React.Dispatch<React.SetStateAction<ColumnKey[]>> }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="gap-1.5">
                    <Columns className="size-4" />
                    Kolom
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>Tampilkan kolom</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {DEFAULT_VISIBLE_COLUMNS.map((key) => (
                    <DropdownMenuCheckboxItem
                        key={key}
                        checked={visibleColumns.includes(key)}
                        onCheckedChange={(checked) =>
                            setVisibleColumns((prev) =>
                                checked ? [...prev, key] : prev.filter((k) => k !== key)
                            )
                        }
                        className="capitalize"
                    >
                        {COLUMN_DEFINITIONS[key].label}
                    </DropdownMenuCheckboxItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

ColumnVisibilityDropdown.displayName = 'ColumnVisibilityDropdown';

function EmployeeRow({
    employee,
    tenant,
    visibleColumns,
    onDelete,
}: {
    employee: Employee;
    tenant: string;
    visibleColumns: ColumnKey[];
    onDelete: (id: string) => void;
}) {
    const hasUser = employee.user !== null;
    const isActive = hasUser ? employee.user.is_active : true;

    return (
        <TableRow key={employee.id}>
            {visibleColumns.includes('nik') && (
                <TableCell className="font-mono text-sm sticky left-0 bg-card/95 z-10 border-r border-border">
                    {employee.nik_employee ?? '—'}
                </TableCell>
            )}
            {visibleColumns.includes('name') && (
                <TableCell className="font-semibold sticky left-[80px] bg-card/95 z-10 border-r border-border">
                    <div className="flex items-center gap-3">
                        <Avatar src={employee.photo_url} name={employee.nama_employee} size={32} />
                        <Link
                            href={show({ tenant, employee: employee.id })}
                            className="hover:underline"
                        >
                            {employee.nama_employee}
                        </Link>
                    </div>
                </TableCell>
            )}
            {visibleColumns.includes('department') && (
                <TableCell className="font-mono text-sm text-muted-foreground sticky left-[200px] bg-card/95 z-10 border-r border-border">
                    {employee.department?.kode_department ?? '—'}
                </TableCell>
            )}
            {visibleColumns.includes('position') && (
                <TableCell className="text-muted-foreground">
                    {employee.position?.nama_position ?? '—'}
                </TableCell>
            )}
            {visibleColumns.includes('division') && (
                <TableCell className="text-muted-foreground">
                    {employee.division?.nama_division ?? '—'}
                </TableCell>
            )}
            {visibleColumns.includes('phone') && (
                <TableCell className="text-muted-foreground">
                    {employee.number ?? '—'}
                </TableCell>
            )}
            {visibleColumns.includes('email') && (
                <TableCell className="text-muted-foreground">
                    {employee.email ?? '—'}
                </TableCell>
            )}
            {visibleColumns.includes('joined') && (
                <TableCell className="text-muted-foreground">
                    {formatDate(employee.created_at)}
                </TableCell>
            )}
            {visibleColumns.includes('status') && (
                <TableCell>
                    <StatusBadge active={isActive} />
                </TableCell>
            )}
            <TableCell className="w-24 sticky right-0 bg-card/95 z-10 border-l border-border">
                <div className="flex items-center justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        asChild
                        aria-label={`Lihat ${employee.nama_employee}`}
                    >
                        <Link href={show({ tenant, employee: employee.id })}>
                            <Eye className="size-4" />
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        asChild
                        aria-label={`Edit ${employee.nama_employee}`}
                    >
                        <Link href={edit({ tenant, employee: employee.id })}>
                            <Edit2 className="size-4" />
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => onDelete(employee.id)}
                        aria-label={`Hapus ${employee.nama_employee}`}
                        className="text-destructive hover:bg-destructive/10"
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            </TableCell>
        </TableRow>
    );
}

EmployeeRow.displayName = 'EmployeeRow';

function DeleteConfirmDialog({
    open,
    onOpenChange,
    onConfirm,
    employeeName,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
    employeeName: string;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hapus Karyawan</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus <strong>{employeeName}</strong>?
                        Tindakan ini tidak dapat dibatalkan.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Batal
                    </Button>
                    <Button variant="destructive" onClick={() => { onConfirm(); onOpenChange(false); }}>
                        Hapus
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

DeleteConfirmDialog.displayName = 'DeleteConfirmDialog';

export default function Employees({ employees, filters, errors }: PageProps) {
    const { flash } = usePage<FlashProps>().props;
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;
    const tenant = activeTenant!;

    const [searchTerm, setSearchTerm] = useState(filters.search);
    const [appliedFilters, setAppliedFilters] = useState<Filters>(filters);
    const [navigating, setNavigating] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [employeeToDelete, setEmployeeToDelete] = useState<{ id: string; name: string } | null>(null);
    const [visibleColumns, setVisibleColumns] = useState<ColumnKey[]>(DEFAULT_VISIBLE_COLUMNS);
    const skipFirstQuery = useRef(true);

    // Debounce search
    useEffect(() => {
        const timer = setTimeout(() => {
            setAppliedFilters((current) =>
                current.search === searchTerm
                    ? current
                    : { ...current, search: searchTerm, page: 1 }
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [searchTerm]);

    // Sync URL with filters
    useEffect(() => {
        if (skipFirstQuery.current) {
            skipFirstQuery.current = false;
            return;
        }

        router.get(
            index({ tenant }),
            {
                search: appliedFilters.search,
                per_page: appliedFilters.per_page,
                sort: appliedFilters.sort,
                direction: appliedFilters.direction,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setNavigating(true),
                onFinish: () => setNavigating(false),
            }
        );
    }, [appliedFilters, tenant]);

    // Re-align toolbar when paginating server-side
    useEffect(() => {
        setSearchTerm(filters.search);
        setAppliedFilters(filters);
    }, [employees.current_page]);

    const clearFilters = () => {
        setSearchTerm('');
        setAppliedFilters({
            search: '',
            per_page: 10,
            sort: 'nama_employee',
            direction: 'asc',
        });
    };

    const hasActiveFilters = appliedFilters.search !== '';

    const handleDelete = (id: string) => {
        const employee = employees.data.find((e) => e.id === id);
        if (employee) {
            setEmployeeToDelete({ id, name: employee.nama_employee });
            setDeleteDialogOpen(true);
        }
    };

    const confirmDelete = () => {
        if (!employeeToDelete) return;
        router.delete(
            destroy({ tenant, employee: employeeToDelete.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEmployeeToDelete(null);
                },
            }
        );
    };

    const handleSort = (column: string) => {
        setAppliedFilters((current) => ({
            ...current,
            sort: column,
            direction: current.sort === column && current.direction === 'asc' ? 'desc' : 'asc',
            page: 1,
        }));
    };

    const sortIcon = (column: string) => {
        if (appliedFilters.sort !== column) return <ChevronUpIcon className="size-4 text-ink-subtle" />;
        return appliedFilters.direction === 'asc' ? (
            <ChevronUpIcon className="size-4 text-brand" />
        ) : (
            <ChevronDownIcon className="size-4 text-brand" />
        );
    };

    const { data: employeesData, ...pagination } = employees;

    return (
        <>
            <Head title="Karyawan" />

            <DeleteConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
                onConfirm={confirmDelete}
                employeeName={employeeToDelete?.name ?? ''}
            />

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

                {errors?.employee && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{errors.employee}</AlertDescription>
                    </Alert>
                )}

                {/* Sticky Action Bar */}
                <div className="sticky top-16 z-40 flex flex-col gap-4 bg-background/95 backdrop-blur-sm border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between lg:px-6">
                    <div className="relative w-full max-w-xs sm:max-w-md">
                        <Search
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-subtle"
                        />
                        <label className="sr-only" htmlFor="employee-search">
                            Cari NIK, nama, email, telepon, department, position
                        </label>
                        <Input
                            id="employee-search"
                            type="search"
                            placeholder="Cari karyawan…"
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            className="h-10 pl-9"
                        />
                        {searchTerm && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-ink-subtle hover:text-ink"
                                onClick={clearFilters}
                                aria-label="Bersihkan pencarian"
                            >
                                <SearchX className="size-4" />
                            </Button>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <ColumnVisibilityDropdown
                            visibleColumns={visibleColumns}
                            setVisibleColumns={setVisibleColumns}
                        />

                        <Select value={appliedFilters.per_page.toString()} onValueChange={(value) => setAppliedFilters((c) => ({ ...c, per_page: parseInt(value), page: 1 }))}>
                            <SelectTrigger size="sm" className="w-[110px]">
                                <SelectValue placeholder="Per halaman" />
                            </SelectTrigger>
                            <SelectContent>
                                {PER_PAGE_OPTIONS.map((opt) => (
                                    <SelectItem key={opt} value={opt.toString()}>
                                        {opt} per halaman
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <EmployeeActions activeTenant={tenant} onSync={() => {}} />
                    </div>
                </div>

                {/* Table */}
                <div
                    className="overflow-hidden rounded-lg border bg-card transition-opacity duration-150 aria-busy:pointer-events-none aria-busy:opacity-60"
                    aria-busy={navigating}
                >
                    <div className="overflow-x-auto">
                        <Table className="w-full">
                            <TableHeader className="sticky top-[60px] z-20 bg-card/95 backdrop-blur-sm border-b border-border-strong">
                                <TableRow className="hover:bg-transparent">
                                    {visibleColumns.includes('nik') && (
                                        <TableHead className="w-32 sticky left-0 z-30 bg-card/95">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="w-full justify-start p-0 h-auto text-xs font-semibold"
                                                onClick={() => handleSort('nik_employee')}
                                            >
                                                <div className="flex items-center gap-1.5">
                                                    <User className="size-3" />
                                                    NIK
                                                    {sortIcon('nik_employee')}
                                                </div>
                                            </Button>
                                        </TableHead>
                                    )}
                                    {visibleColumns.includes('name') && (
                                        <TableHead className="min-w-[200px] sticky left-[80px] z-30 bg-card/95">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="w-full justify-start p-0 h-auto text-xs font-semibold"
                                                onClick={() => handleSort('nama_employee')}
                                            >
                                                <div className="flex items-center gap-1.5">
                                                    <User className="size-3" />
                                                    Nama
                                                    {sortIcon('nama_employee')}
                                                </div>
                                            </Button>
                                        </TableHead>
                                    )}
                                    {visibleColumns.includes('department') && (
                                        <TableHead className="w-40 sticky left-[200px] z-30 bg-card/95">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="w-full justify-start p-0 h-auto text-xs font-semibold"
                                            >
                                                <div className="flex items-center gap-1.5">
                                                    <Building2 className="size-3" />
                                                    Department
                                                </div>
                                            </Button>
                                        </TableHead>
                                    )}
                                    {visibleColumns.includes('position') && (
                                        <TableHead className="min-w-[160px]">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="w-full justify-start p-0 h-auto text-xs font-semibold"
                                            >
                                                Position
                                            </Button>
                                        </TableHead>
                                    )}
                                    {visibleColumns.includes('division') && (
                                        <TableHead className="min-w-[160px]">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="w-full justify-start p-0 h-auto text-xs font-semibold"
                                            >
                                                Division
                                            </Button>
                                        </TableHead>
                                    )}
                                    {visibleColumns.includes('phone') && (
                                        <TableHead className="min-w-[140px]">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="w-full justify-start p-0 h-auto text-xs font-semibold"
                                            >
                                                Telepon
                                            </Button>
                                        </TableHead>
                                    )}
                                    {visibleColumns.includes('email') && (
                                        <TableHead className="min-w-[200px]">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="w-full justify-start p-0 h-auto text-xs font-semibold"
                                            >
                                                Email
                                            </Button>
                                        </TableHead>
                                    )}
                                    {visibleColumns.includes('joined') && (
                                        <TableHead className="w-40">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="w-full justify-start p-0 h-auto text-xs font-semibold"
                                                onClick={() => handleSort('created_at')}
                                            >
                                                <div className="flex items-center gap-1.5">
                                                    <Calendar className="size-3" />
                                                    Bergabung
                                                    {sortIcon('created_at')}
                                                </div>
                                            </Button>
                                        </TableHead>
                                    )}
                                    {visibleColumns.includes('status') && (
                                        <TableHead className="w-32">
                                            <span className="text-xs font-semibold text-ink-muted">Status</span>
                                        </TableHead>
                                    )}
                                    <TableHead className="w-24 sticky right-0 z-30 bg-card/95 border-l border-border">
                                        <span className="sr-only">Aksi</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {navigating && employeesData.length > 0 ? (
                                    [...Array(5)].map((_, i) => <SkeletonRow key={i} />)
                                ) : employeesData.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={visibleColumns.length + 1}
                                            className="h-32 text-center text-ink-muted"
                                        >
                                            {hasActiveFilters ? (
                                                <>
                                                    <SearchX className="size-8 mx-auto text-ink-subtle" />
                                                    <p className="mt-2 text-sm font-semibold text-ink">
                                                        Tidak ada karyawan yang cocok.
                                                    </p>
                                                    <p className="text-sm text-ink-muted">
                                                        Coba kata kunci lain atau{' '}
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="ml-1 p-0 h-auto"
                                                            onClick={clearFilters}
                                                        >
                                                            bersihkan pencarian
                                                        </Button>
                                                    </p>
                                                </>
                                            ) : (
                                                <>
                                                    <Building2 className="size-8 mx-auto text-ink-subtle" />
                                                    <p className="mt-2 text-sm font-semibold text-ink">
                                                        Belum ada karyawan.
                                                    </p>
                                                    <p className="max-w-sm text-sm text-ink-muted">
                                                        Tambah karyawan pertama atau sinkronkan dari Optigate.
                                                    </p>
                                                    <div className="mt-3 flex gap-2 justify-center">
                                                        <Button asChild variant="outline" size="sm">
                                                            <Link href={create({ tenant })}>
                                                                <Plus className="size-3.5 mr-1.5" />
                                                                Tambah Karyawan
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => router.post(sync({ tenant }), {}, { preserveScroll: true })}
                                                        >
                                                            <RefreshCw className="size-3.5 mr-1.5" />
                                                            Sinkronkan
                                                        </Button>
                                                    </div>
                                                </>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    employeesData.map((employee) => (
                                        <EmployeeRow
                                            key={employee.id}
                                            employee={employee}
                                            tenant={tenant}
                                            visibleColumns={visibleColumns}
                                            onDelete={handleDelete}
                                        />
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Pagination */}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
                        <p className="text-xs text-ink-muted" aria-live="polite">
                            Menampilkan {pagination.from}–{pagination.to} dari {pagination.total} karyawan
                        </p>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!pagination.prev_page_url}
                                onClick={() => pagination.prev_page_url && router.get(pagination.prev_page_url, {}, { preserveScroll: true, preserveState: true })}
                            >
                                <ChevronLeft aria-hidden />
                                Sebelumnya
                            </Button>
                            <span className="text-sm text-ink-muted px-2">
                                Halaman {pagination.current_page} dari {pagination.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!pagination.next_page_url}
                                onClick={() => pagination.next_page_url && router.get(pagination.next_page_url, {}, { preserveScroll: true, preserveState: true })}
                            >
                                Selanjutnya
                                <ChevronRight aria-hidden />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

function ChevronUpIcon({ className, ...props }: React.SVGProps<SVGSVGElement>) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            {...props}
            className={cn('size-4', className)}
        >
            <path d="m18 15-6-6-6 6" />
        </svg>
    );
}

function ChevronDownIcon({ className, ...props }: React.SVGProps<SVGSVGElement>) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            {...props}
            className={cn('size-4', className)}
        >
            <path d="m6 9 6 6 6-6" />
        </svg>
    );
}

Employees.layout = {
    breadcrumbs: [{ title: 'Data Master' }, { title: 'Karyawan' }],
    title: 'Karyawan',
    description: 'Data karyawan dalam cabang aktif',
    actions: ({ activeTenant }: { activeTenant?: string }) => (
        <EmployeeActions
            activeTenant={activeTenant!}
            onSync={() => {}}
        />
    ),
};