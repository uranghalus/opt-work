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
    Columns,
    ChevronLeft,
    ChevronRight,
    User,
    UserCheck,
    UserX,
    Ban,
    Undo2,
    Mail,
    Phone,
    Calendar,
    Building2,
} from 'lucide-react';
import { useEffect, useRef, useState, useCallback } from 'react';
import {
    index,
    create,
    edit,
    show,
    destroy,
    sync,
    ban,
    unban,
} from '@/routes/employees';
import { useRequiredTenant } from '@/lib/tenant';
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
    email: string | null;
};

type Employee = {
    id: string;
    nik_employee: string | null;
    nama_employee: string;
    email: string | null;
    number: string | null;
    photo_url: string | null;
    created_at: string | null;
    is_banned: boolean;
    banned_at: string | null;
    has_account: boolean;
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

const DEFAULT_VISIBLE_COLUMNS = [
    'nik',
    'name',
    'department',
    'position',
    'division',
    'phone',
    'email',
    'joined',
    'akun',
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

function Avatar({
    src,
    name,
    size = 32,
}: {
    src?: string | null;
    name: string;
    size?: number;
}) {
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
                className={cn(
                    'rounded-full object-cover',
                    `w-${size / 4} h-${size / 4}`,
                )}
            />
        );
    }

    return (
        <div
            className={cn(
                'flex items-center justify-center rounded-full text-xs font-medium',
                `w-${size / 4} h-${size / 4}`,
                bgColors[colorIndex],
            )}
            aria-label={name}
        >
            {initials}
        </div>
    );
}

function AccountBadge({ hasAccount }: { hasAccount: boolean }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap',
                hasAccount
                    ? 'border-success/30 bg-success/10 text-success'
                    : 'border-border bg-surface-raised text-ink-muted',
            )}
        >
            {hasAccount ? (
                <UserCheck aria-hidden className="size-3" />
            ) : (
                <UserX aria-hidden className="size-3" />
            )}
            {hasAccount ? 'Punya akun' : 'Tanpa akun'}
        </span>
    );
}

function BannedBadge() {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-danger/30 bg-danger/10 px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap text-danger">
            <Ban aria-hidden className="size-3" />
            Diblokir
        </span>
    );
}

function SkeletonRow() {
    return (
        <TableRow>
            <TableCell className="font-mono text-sm">
                <Skeleton className="h-4 w-24" />
            </TableCell>
            <TableCell className="font-semibold">
                <Skeleton className="h-5 w-32" />
            </TableCell>
            <TableCell className="font-mono text-sm text-muted-foreground">
                <Skeleton className="h-4 w-20" />
            </TableCell>
            <TableCell className="text-muted-foreground">
                <Skeleton className="h-4 w-28" />
            </TableCell>
            <TableCell className="text-muted-foreground">
                <Skeleton className="h-4 w-24" />
            </TableCell>
            <TableCell className="text-muted-foreground">
                <Skeleton className="h-4 w-32" />
            </TableCell>
            <TableCell className="text-muted-foreground">
                <Skeleton className="h-4 w-24" />
            </TableCell>
            <TableCell>
                <Skeleton className="h-5 w-20" />
            </TableCell>
            <TableCell className="w-24">
                <Skeleton className="h-8 w-20" />
            </TableCell>
        </TableRow>
    );
}

const COLUMN_DEFINITIONS: Record<
    ColumnKey,
    { label: string; icon?: React.ReactNode }
> = {
    nik: { label: 'NIK', icon: <User className="size-3" /> },
    name: { label: 'Nama', icon: <User className="size-3" /> },
    department: { label: 'Department', icon: <Building2 className="size-3" /> },
    position: { label: 'Position', icon: <User className="size-3" /> },
    division: { label: 'Division', icon: <Building2 className="size-3" /> },
    phone: { label: 'Telepon', icon: <Phone className="size-3" /> },
    email: { label: 'Email', icon: <Mail className="size-3" /> },
    joined: { label: 'Bergabung', icon: <Calendar className="size-3" /> },
    akun: { label: 'Akun', icon: <UserCheck className="size-3" /> },
};

/**
 * Post to the sync endpoint, which re-runs the Optigate employee import for
 * the current cabang and flashes the summary back.
 */
function useEmployeeSync() {
    const tenant = useRequiredTenant('employees.index');
    const [syncing, setSyncing] = useState(false);

    const runSync = useCallback(() => {
        if (syncing) return;
        setSyncing(true);
        router.post(
            sync({ tenant }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSyncing(false),
            },
        );
    }, [tenant, syncing]);

    return { syncing, runSync };
}

/**
 * Primary actions of this page, rendered in the shell's heading band. The
 * shell instantiates layout actions with no props, so this component owns its
 * own sync state and call.
 */
function EmployeeActions() {
    const tenant = useRequiredTenant('employees.index');
    const { syncing, runSync } = useEmployeeSync();

    return (
        <div className="flex items-center gap-2">
            <Button
                onClick={runSync}
                disabled={syncing}
                variant="outline"
                size="sm"
            >
                {syncing ? (
                    <>
                        <LoaderCircle
                            aria-hidden
                            className="mr-1.5 animate-spin"
                        />
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
                <Link href={create({ tenant })}>
                    <Plus aria-hidden className="mr-1.5" />
                    Tambah Karyawan
                </Link>
            </Button>
        </div>
    );
}

EmployeeActions.displayName = 'EmployeeActions';

function ColumnVisibilityDropdown({
    visibleColumns,
    setVisibleColumns,
}: {
    visibleColumns: ColumnKey[];
    setVisibleColumns: React.Dispatch<React.SetStateAction<ColumnKey[]>>;
}) {
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
                                checked
                                    ? [...prev, key]
                                    : prev.filter((k) => k !== key),
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
    onBanToggle,
    canBan,
}: {
    employee: Employee;
    tenant: string;
    visibleColumns: ColumnKey[];
    onDelete: (id: string) => void;
    onBanToggle: (employee: Employee) => void;
    canBan: boolean;
}) {
    return (
        <TableRow key={employee.id}>
            {visibleColumns.includes('nik') && (
                <TableCell className="font-mono text-sm">
                    {employee.nik_employee ?? '—'}
                </TableCell>
            )}
            {visibleColumns.includes('name') && (
                <TableCell className="font-semibold">
                    <div className="flex items-center gap-3">
                        <Avatar
                            src={employee.photo_url}
                            name={employee.nama_employee}
                            size={32}
                        />
                        <Link
                            href={show({ tenant, employee: employee.id })}
                            className={cn(
                                'hover:underline',
                                employee.is_banned && 'text-ink-muted',
                            )}
                        >
                            {employee.nama_employee}
                        </Link>
                        {employee.is_banned && <BannedBadge />}
                    </div>
                </TableCell>
            )}
            {visibleColumns.includes('department') && (
                <TableCell className="font-mono text-sm text-muted-foreground">
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
            {visibleColumns.includes('akun') && (
                <TableCell>
                    <AccountBadge hasAccount={employee.has_account} />
                </TableCell>
            )}
            <TableCell className="w-28">
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
                    {canBan && (
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => onBanToggle(employee)}
                            aria-label={
                                employee.is_banned
                                    ? `Batalkan blokir ${employee.nama_employee}`
                                    : `Blokir ${employee.nama_employee}`
                            }
                            className={
                                employee.is_banned
                                    ? 'text-success hover:bg-success/10'
                                    : 'text-destructive hover:bg-destructive/10'
                            }
                        >
                            {employee.is_banned ? (
                                <Undo2 className="size-4" />
                            ) : (
                                <Ban className="size-4" />
                            )}
                        </Button>
                    )}
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
                        Apakah Anda yakin ingin menghapus{' '}
                        <strong>{employeeName}</strong>? Tindakan ini tidak
                        dapat dibatalkan.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={() => {
                            onConfirm();
                            onOpenChange(false);
                        }}
                    >
                        Hapus
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

DeleteConfirmDialog.displayName = 'DeleteConfirmDialog';

function BanConfirmDialog({
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
                    <DialogTitle>Blokir karyawan?</DialogTitle>
                    <DialogDescription>
                        <strong>{employeeName}</strong> akan langsung diblokir
                        dari penggunaan operasional pada request berikutnya.
                        Blokir menempel pada karyawan dan tidak ikut terhapus
                        saat sinkronisasi portal berjalan. Riwayat datanya tetap
                        tersimpan, dan blokir dapat dibatalkan kapan saja.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={() => {
                            onConfirm();
                            onOpenChange(false);
                        }}
                    >
                        Blokir
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

BanConfirmDialog.displayName = 'BanConfirmDialog';

export default function Employees({ employees, filters, errors }: PageProps) {
    const { flash, auth } = usePage<FlashProps>().props;
    const tenant = useRequiredTenant('employees.index');
    const canBan = auth?.isSuperAdmin ?? false;

    const [searchTerm, setSearchTerm] = useState(filters.search);
    const [appliedFilters, setAppliedFilters] = useState<Filters>(filters);
    const [navigating, setNavigating] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [employeeToDelete, setEmployeeToDelete] = useState<{
        id: string;
        name: string;
    } | null>(null);
    const [banDialogOpen, setBanDialogOpen] = useState(false);
    const [employeeToBan, setEmployeeToBan] = useState<Employee | null>(null);
    const [visibleColumns, setVisibleColumns] = useState<ColumnKey[]>([
        ...DEFAULT_VISIBLE_COLUMNS,
    ]);
    const { syncing, runSync } = useEmployeeSync();
    const skipFirstQuery = useRef(true);

    // Debounce search
    useEffect(() => {
        const timer = setTimeout(() => {
            setAppliedFilters((current) =>
                current.search === searchTerm
                    ? current
                    : { ...current, search: searchTerm, page: 1 },
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
            },
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
        router.delete(destroy({ tenant, employee: employeeToDelete.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setEmployeeToDelete(null);
            },
        });
    };

    const handleBanToggle = (employee: Employee) => {
        if (!employee.is_banned) {
            // Ban is destructive with lasting consequences: confirm first.
            setEmployeeToBan(employee);
            setBanDialogOpen(true);
            return;
        }

        router.post(
            unban({ tenant, employee: employee.id }).url,
            {},
            { preserveScroll: true },
        );
    };

    const confirmBan = () => {
        if (!employeeToBan) return;
        router.post(
            ban({ tenant, employee: employeeToBan.id }).url,
            {},
            { preserveScroll: true },
        );
    };

    const handleSort = (column: string) => {
        setAppliedFilters((current) => ({
            ...current,
            sort: column,
            direction:
                current.sort === column && current.direction === 'asc'
                    ? 'desc'
                    : 'asc',
            page: 1,
        }));
    };

    const sortIcon = (column: string) => {
        if (appliedFilters.sort !== column)
            return <ChevronUpIcon className="size-4 text-ink-subtle" />;
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

            <BanConfirmDialog
                open={banDialogOpen}
                onOpenChange={setBanDialogOpen}
                onConfirm={confirmBan}
                employeeName={employeeToBan?.nama_employee ?? ''}
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
                <div className="sticky top-16 z-40 flex flex-col gap-4 border-b border-border bg-background/95 px-4 py-3 backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between lg:px-6">
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
                            onChange={(event) =>
                                setSearchTerm(event.target.value)
                            }
                            className="h-10 pl-9"
                        />
                        {searchTerm && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="absolute top-1/2 right-2 -translate-y-1/2 text-ink-subtle hover:text-ink"
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

                        <Select
                            value={appliedFilters.per_page.toString()}
                            onValueChange={(value) =>
                                setAppliedFilters((c) => ({
                                    ...c,
                                    per_page: parseInt(value),
                                    page: 1,
                                }))
                            }
                        >
                            <SelectTrigger size="sm" className="w-[110px]">
                                <SelectValue placeholder="Per halaman" />
                            </SelectTrigger>
                            <SelectContent>
                                {PER_PAGE_OPTIONS.map((opt) => (
                                    <SelectItem
                                        key={opt}
                                        value={opt.toString()}
                                    >
                                        {opt} per halaman
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {/* Table */}
                <div
                    className="overflow-hidden rounded-lg border bg-card transition-opacity duration-150 aria-busy:pointer-events-none aria-busy:opacity-60"
                    aria-busy={navigating}
                >
                    <Table>
                        <TableHeader className="bg-surface-sunken">
                            <TableRow className="hover:bg-transparent">
                                {visibleColumns.includes('nik') && (
                                    <TableHead className="w-32">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-auto w-full justify-start p-0 text-xs font-semibold"
                                            onClick={() =>
                                                handleSort('nik_employee')
                                            }
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
                                    <TableHead className="min-w-[200px]">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-auto w-full justify-start p-0 text-xs font-semibold"
                                            onClick={() =>
                                                handleSort('nama_employee')
                                            }
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
                                    <TableHead className="w-40">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-auto w-full justify-start p-0 text-xs font-semibold"
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
                                            className="h-auto w-full justify-start p-0 text-xs font-semibold"
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
                                            className="h-auto w-full justify-start p-0 text-xs font-semibold"
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
                                            className="h-auto w-full justify-start p-0 text-xs font-semibold"
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
                                            className="h-auto w-full justify-start p-0 text-xs font-semibold"
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
                                            className="h-auto w-full justify-start p-0 text-xs font-semibold"
                                            onClick={() =>
                                                handleSort('created_at')
                                            }
                                        >
                                            <div className="flex items-center gap-1.5">
                                                <Calendar className="size-3" />
                                                Bergabung
                                                {sortIcon('created_at')}
                                            </div>
                                        </Button>
                                    </TableHead>
                                )}
                                {visibleColumns.includes('akun') && (
                                    <TableHead className="w-32">
                                        <span className="text-xs font-semibold text-ink-muted">
                                            Akun
                                        </span>
                                    </TableHead>
                                )}
                                <TableHead className="w-28">
                                    <span className="sr-only">Aksi</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {navigating && employeesData.length > 0 ? (
                                [...Array(5)].map((_, i) => (
                                    <SkeletonRow key={i} />
                                ))
                            ) : employeesData.length === 0 ? (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={visibleColumns.length + 1}
                                        className="px-6 py-16 text-center text-ink-muted"
                                    >
                                        <div className="flex flex-col items-center">
                                            {hasActiveFilters ? (
                                                <>
                                                    <SearchX
                                                        aria-hidden
                                                        className="size-8 text-ink-subtle"
                                                    />
                                                    <p className="mt-3 text-sm font-semibold text-ink">
                                                        Tidak ada karyawan yang
                                                        cocok.
                                                    </p>
                                                    <p className="mt-1 max-w-sm text-sm text-ink-muted">
                                                        Coba kata kunci lain
                                                        atau{' '}
                                                        <button
                                                            type="button"
                                                            onClick={
                                                                clearFilters
                                                            }
                                                            className="font-medium text-brand underline underline-offset-2 hover:no-underline"
                                                        >
                                                            bersihkan pencarian
                                                        </button>
                                                    </p>
                                                </>
                                            ) : (
                                                <>
                                                    <UserX
                                                        aria-hidden
                                                        className="size-8 text-ink-subtle"
                                                    />
                                                    <p className="mt-3 text-sm font-semibold text-ink">
                                                        Belum ada karyawan.
                                                    </p>
                                                    <p className="mt-1 max-w-sm text-sm text-ink-muted">
                                                        Tambah karyawan pertama
                                                        atau sinkronkan dari
                                                        Optigate.
                                                    </p>
                                                    <div className="mt-4 flex flex-wrap justify-center gap-2">
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <Link
                                                                href={create({
                                                                    tenant,
                                                                })}
                                                            >
                                                                <Plus
                                                                    aria-hidden
                                                                    className="mr-1.5"
                                                                />
                                                                Tambah Karyawan
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={syncing}
                                                            onClick={runSync}
                                                        >
                                                            <RefreshCw
                                                                aria-hidden
                                                                className={cn(
                                                                    'mr-1.5',
                                                                    syncing &&
                                                                        'animate-spin',
                                                                )}
                                                            />
                                                            {syncing
                                                                ? 'Menyinkronkan…'
                                                                : 'Sinkronkan'}
                                                        </Button>
                                                    </div>
                                                </>
                                            )}
                                        </div>
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
                                        onBanToggle={handleBanToggle}
                                        canBan={canBan}
                                    />
                                ))
                            )}
                        </TableBody>
                    </Table>

                    {/* Pagination */}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
                        <p
                            className="text-xs text-ink-muted"
                            aria-live="polite"
                        >
                            Menampilkan {pagination.from}–{pagination.to} dari{' '}
                            {pagination.total} karyawan
                        </p>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!pagination.prev_page_url}
                                onClick={() =>
                                    pagination.prev_page_url &&
                                    router.get(
                                        pagination.prev_page_url,
                                        {},
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                        },
                                    )
                                }
                            >
                                <ChevronLeft aria-hidden />
                                Sebelumnya
                            </Button>
                            <span className="px-2 text-sm text-ink-muted">
                                Halaman {pagination.current_page} dari{' '}
                                {pagination.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!pagination.next_page_url}
                                onClick={() =>
                                    pagination.next_page_url &&
                                    router.get(
                                        pagination.next_page_url,
                                        {},
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                        },
                                    )
                                }
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

function ChevronDownIcon({
    className,
    ...props
}: React.SVGProps<SVGSVGElement>) {
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
    actions: EmployeeActions,
};
