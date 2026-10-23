import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    Search,
    SearchX,
    LoaderCircle,
    RefreshCw,
    Eye,
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
    Briefcase,
    Hash,
    Users,
    ShieldCheck,
    TriangleAlert,
} from "lucide-react";
import { useEffect, useRef, useState, useCallback } from "react";
import { index, show, sync, ban, unban } from "@/routes/employees";
import { useRequiredTenant } from "@/lib/tenant";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuCheckboxItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import {
    AccountStatusBadge,
    BannedBadge,
    DepartmentValue,
    EmployeeAvatar,
    OrganizationValue,
    TextValue,
} from "@/components/employees/employee-display";
import { cn } from "@/lib/utils";
import type { InertiaConfig } from "@inertiajs/core";

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
    department_id: string | null;
    division_id: string | null;
    position_id: string | null;
    has_account: boolean | null;
    status: string;
};

type FilterOptions = {
    departments: { id: string; nama_department: string }[];
    divisions: { id: string; nama_division: string }[];
    positions: { id: string; nama_position: string }[];
};

type Summary = {
    total: number;
    with_account: number;
    missing_department: number;
};

type PageProps = {
    employees: Paginator;
    summary: Summary;
    filters: Filters;
    filter_options: FilterOptions;
    errors?: Record<string, string>;
};

type FlashProps = InertiaConfig["sharedPageProps"] & {
    flash?: { success?: string | null; error?: string | null };
};

const PER_PAGE_OPTIONS = [10, 25, 50, 100] as const;

const DEFAULT_VISIBLE_COLUMNS = [
    "nik",
    "name",
    "department",
    "position",
    "division",
    "phone",
    "email",
    "joined",
    "akun",
] as const;

type ColumnKey = (typeof DEFAULT_VISIBLE_COLUMNS)[number];

function formatDate(value: string | null): string {
    if (!value) return "—";
    return new Date(value).toLocaleDateString("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
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
    return (
        <EmployeeAvatar
            src={src}
            name={name}
            size={size >= 40 ? "size-10" : "size-8"}
        />
    );
}

function SkeletonRow({
    visibleColumns,
}: {
    visibleColumns: ColumnKey[];
}) {
    return (
        <TableRow>
            {visibleColumns.map((column) => {
                const width =
                    column === "name"
                        ? "h-5 w-32"
                        : column === "akun"
                          ? "h-5 w-20"
                          : "h-4 w-24";

                return (
                    <TableCell key={column} className="text-muted-foreground">
                        <Skeleton className={width} />
                    </TableCell>
                );
            })}
            <TableCell className="w-28">
                <Skeleton className="h-8 w-16" />
            </TableCell>
        </TableRow>
    );
}

const COLUMN_DEFINITIONS: Record<
    ColumnKey,
    { label: string; icon?: React.ReactNode }
> = {
    nik: { label: "NIK", icon: <Hash className="size-3" /> },
    name: { label: "Nama", icon: <User className="size-3" /> },
    department: { label: "Departemen", icon: <Building2 className="size-3" /> },
    position: { label: "Posisi", icon: <Briefcase className="size-3" /> },
    division: { label: "Divisi", icon: <Building2 className="size-3" /> },
    phone: { label: "Telepon", icon: <Phone className="size-3" /> },
    email: { label: "Email", icon: <Mail className="size-3" /> },
    joined: { label: "Data dibuat", icon: <Calendar className="size-3" /> },
    akun: { label: "Akun", icon: <UserCheck className="size-3" /> },
};

function SummaryMetric({
    icon: Icon,
    label,
    value,
    tone,
}: {
    icon: typeof Users;
    label: string;
    value: number;
    tone: "brand" | "success" | "warning";
}) {
    const toneClass = {
        brand: "border-brand/25 bg-brand-soft text-brand",
        success: "border-success/25 bg-success-subtle text-success",
        warning: "border-warning/25 bg-warning-subtle text-warning-ink",
    }[tone];

    return (
        <div className="rounded-xl border border-border bg-card p-4 shadow-card">
            <div className="flex items-center gap-3">
                <span
                    className={`grid size-9 place-items-center rounded-lg border ${toneClass}`}
                >
                    <Icon aria-hidden className="size-4" />
                </span>
                <div>
                    <p className="text-[11px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                        {label}
                    </p>
                    <p className="text-2xl leading-none font-semibold tabular-nums text-ink">
                        {value}
                    </p>
                </div>
            </div>
        </div>
    );
}

function EmployeeSummary({ summary }: { summary: Summary }) {
    return (
        <div className="grid gap-3 sm:grid-cols-3">
            <SummaryMetric
                icon={Users}
                label="Total karyawan"
                value={summary.total}
                tone="brand"
            />
            <SummaryMetric
                icon={UserCheck}
                label="Dengan akun"
                value={summary.with_account}
                tone="success"
            />
            <SummaryMetric
                icon={TriangleAlert}
                label="Tanpa departemen"
                value={summary.missing_department}
                tone="warning"
            />
        </div>
    );
}

function EmployeeCard({
    employee,
    tenant,
    onBanToggle,
    canBan,
}: {
    employee: Employee;
    tenant: string;
    onBanToggle: (employee: Employee) => void;
    canBan: boolean;
}) {
    return (
        <article className="rounded-xl border border-border bg-card p-4 shadow-card">
            <div className="flex items-start gap-3">
                <Avatar
                    src={employee.photo_url}
                    name={employee.nama_employee}
                    size={40}
                />

                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <Link
                            href={show({ tenant, employee: employee.id })}
                            className="truncate font-semibold text-ink hover:text-brand hover:underline"
                        >
                            {employee.nama_employee}
                        </Link>
                        {employee.is_banned && <BannedBadge />}
                    </div>

                    <p className="mt-0.5 font-mono text-xs text-ink-muted">
                        {employee.nik_employee ?? "NIK belum ada"}
                    </p>

                    <OrganizationValue
                        department={employee.department}
                        position={employee.position}
                        division={employee.division}
                        className="mt-1"
                    />
                </div>
            </div>

            <dl className="mt-4 space-y-3">
                <div>
                    <dt className="text-[11px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                        Departemen
                    </dt>
                    <dd className="mt-1">
                        <DepartmentValue department={employee.department} />
                    </dd>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt className="text-[11px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                            Email
                        </dt>
                        <dd className="mt-1 text-sm text-ink-muted">
                            {employee.email ?? "Belum ada data"}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-[11px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                            Telepon
                        </dt>
                        <dd className="mt-1 text-sm text-ink-muted">
                            {employee.number ?? "Belum ada data"}
                        </dd>
                    </div>
                </div>
            </dl>

            <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-4">
                <AccountStatusBadge hasAccount={employee.has_account} />

                <div className="ml-auto flex items-center gap-2">
                    <Button
                        asChild
                        variant="outline"
                        className="h-10"
                        aria-label={`Lihat ${employee.nama_employee}`}
                    >
                        <Link href={show({ tenant, employee: employee.id })}>
                            <Eye aria-hidden className="size-4" />
                            Lihat
                        </Link>
                    </Button>

                    {canBan && (
                        <Button
                            variant={
                                employee.is_banned ? "outline" : "ghost"
                            }
                            onClick={() => onBanToggle(employee)}
                            className="h-10"
                        >
                            {employee.is_banned ? (
                                <>
                                    <Undo2 aria-hidden className="size-4" />
                                    Batal blokir
                                </>
                            ) : (
                                <>
                                    <Ban aria-hidden className="size-4" />
                                    Blokir
                                </>
                            )}
                        </Button>
                    )}
                </div>
            </div>
        </article>
    );
}

/**
 * Post to the sync endpoint, which re-runs the Optigate employee import for
 * the current cabang and flashes the summary back.
 */
function useEmployeeSync() {
    const tenant = useRequiredTenant("employees.index");
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
        </div>
    );
}

EmployeeActions.displayName = "EmployeeActions";

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
                <Button variant="outline" size="sm" className="h-10 gap-1.5">
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

ColumnVisibilityDropdown.displayName = "ColumnVisibilityDropdown";

function EmployeeRow({
    employee,
    tenant,
    visibleColumns,
    onBanToggle,
    canBan,
}: {
    employee: Employee;
    tenant: string;
    visibleColumns: ColumnKey[];
    onBanToggle: (employee: Employee) => void;
    canBan: boolean;
}) {
    return (
        <TableRow key={employee.id}>
            {visibleColumns.includes("nik") && (
                <TableCell className="font-mono text-sm">
                    {employee.nik_employee ?? "—"}
                </TableCell>
            )}
            {visibleColumns.includes("name") && (
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
                                "hover:underline",
                                employee.is_banned && "text-ink-muted",
                            )}
                        >
                            {employee.nama_employee}
                        </Link>
                        {employee.is_banned && <BannedBadge />}
                    </div>
                </TableCell>
            )}
            {visibleColumns.includes("department") && (
                <TableCell>
                    <DepartmentValue department={employee.department} />
                </TableCell>
            )}
            {visibleColumns.includes("position") && (
                <TableCell>
                    <TextValue
                        value={employee.position?.nama_position}
                        fallbackLabel="Posisi belum ada"
                    />
                </TableCell>
            )}
            {visibleColumns.includes("division") && (
                <TableCell>
                    <TextValue
                        value={employee.division?.nama_division}
                        fallbackLabel="Divisi belum ada"
                    />
                </TableCell>
            )}
            {visibleColumns.includes("phone") && (
                <TableCell>
                    <TextValue
                        value={employee.number}
                        fallbackLabel="Telepon belum ada"
                    />
                </TableCell>
            )}
            {visibleColumns.includes("email") && (
                <TableCell>
                    <TextValue
                        value={employee.email}
                        fallbackLabel="Email belum ada"
                    />
                </TableCell>
            )}
            {visibleColumns.includes("joined") && (
                <TableCell className="text-muted-foreground">
                    {formatDate(employee.created_at)}
                </TableCell>
            )}
            {visibleColumns.includes("akun") && (
                <TableCell>
                    <AccountStatusBadge
                        hasAccount={employee.has_account}
                    />
                </TableCell>
            )}
            <TableCell className="w-28">
                <div className="flex items-center justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        asChild
                        className="h-10 w-10"
                        aria-label={`Lihat ${employee.nama_employee}`}
                    >
                        <Link href={show({ tenant, employee: employee.id })}>
                            <Eye className="size-4" />
                        </Link>
                    </Button>
                    {canBan && (
                        <Button
                            variant="ghost"
                            size="icon"
                            className={cn(
                                "h-10 w-10",
                                employee.is_banned
                                    ? "text-success hover:bg-success/10"
                                    : "text-destructive hover:bg-destructive/10",
                            )}
                            onClick={() => onBanToggle(employee)}
                            aria-label={
                                employee.is_banned
                                    ? `Batalkan blokir ${employee.nama_employee}`
                                    : `Blokir ${employee.nama_employee}`
                            }
                        >
                            {employee.is_banned ? (
                                <Undo2 className="size-4" />
                            ) : (
                                <Ban className="size-4" />
                            )}
                        </Button>
                    )}
                </div>
            </TableCell>
        </TableRow>
    );
}

EmployeeRow.displayName = "EmployeeRow";

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

BanConfirmDialog.displayName = "BanConfirmDialog";

export default function Employees({
    employees,
    summary,
    filters,
    filter_options,
    errors,
}: PageProps) {
    const { flash, auth } = usePage<FlashProps>().props;
    const tenant = useRequiredTenant("employees.index");
    const canBan = auth?.isSuperAdmin ?? false;

    const [searchTerm, setSearchTerm] = useState(filters.search);
    const [appliedFilters, setAppliedFilters] = useState<Filters>(filters);
    const [navigating, setNavigating] = useState(false);
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

    // Org/account/status filters — same debounce-free path as per_page: a
    // Select change updates the state and the URL-sync effect navigates.
    const setFilter = <K extends keyof Filters>(key: K, value: Filters[K]) =>
        setAppliedFilters((current) => ({
            ...current,
            [key]: value,
            page: 1,
        }));

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
                department_id: appliedFilters.department_id ?? "",
                division_id: appliedFilters.division_id ?? "",
                position_id: appliedFilters.position_id ?? "",
                has_account:
                    appliedFilters.has_account === null
                        ? ""
                        : appliedFilters.has_account
                          ? "1"
                          : "0",
                status: appliedFilters.status,
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

    const clearSearch = () => {
        setSearchTerm("");
        setAppliedFilters(current =>
            current.search === ""
                ? current
                : { ...current, search: "", page: 1 },
        );
    };

    const activeFilterCount = [
        appliedFilters.department_id !== null,
        appliedFilters.division_id !== null,
        appliedFilters.position_id !== null,
        appliedFilters.has_account !== null,
        appliedFilters.status !== "all",
    ].filter(Boolean).length;

    const clearFilters = () => {
        setSearchTerm("");
        setAppliedFilters({
            search: "",
            per_page: 10,
            sort: "nama_employee",
            direction: "asc",
            department_id: null,
            division_id: null,
            position_id: null,
            has_account: null,
            status: "all",
        });
    };

    const hasActiveFilters =
        appliedFilters.search !== "" ||
        appliedFilters.department_id !== null ||
        appliedFilters.division_id !== null ||
        appliedFilters.position_id !== null ||
        appliedFilters.has_account !== null ||
        appliedFilters.status !== "all";

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
                current.sort === column && current.direction === "asc"
                    ? "desc"
                    : "asc",
            page: 1,
        }));
    };

    const ariaSortFor = (column: string) => {
        if (appliedFilters.sort !== column) return "none";
        return appliedFilters.direction === "asc"
            ? "ascending"
            : "descending";
    };

    const sortIcon = (column: string) => {
        if (appliedFilters.sort !== column)
            return <ChevronUpIcon className="size-4 text-ink-subtle" />;
        return appliedFilters.direction === "asc" ? (
            <ChevronUpIcon className="size-4 text-brand" />
        ) : (
            <ChevronDownIcon className="size-4 text-brand" />
        );
    };

    const { data: employeesData, ...pagination } = employees;

    return (
        <>
            <Head title="Karyawan" />

            <BanConfirmDialog
                open={banDialogOpen}
                onOpenChange={setBanDialogOpen}
                onConfirm={confirmBan}
                employeeName={employeeToBan?.nama_employee ?? ""}
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

                <EmployeeSummary summary={summary} />

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
                            className="h-11 pl-9"
                        />
                        {searchTerm && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="absolute top-1/2 right-2 h-11 w-11 -translate-y-1/2 text-ink-muted hover:text-ink"
                                onClick={clearSearch}
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
                            <SelectTrigger
                                size="sm"
                                aria-label="Jumlah per halaman"
                                className="h-10 w-[110px]"
                            >
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

                {/* Filter bar — org + account/status filters; option lists
                    are server-driven per tenant (filter_options prop). */}
                <div className="flex flex-wrap items-center gap-3 px-4 lg:px-6">
                    <Select
                        value={appliedFilters.department_id ?? "all"}
                        onValueChange={(value) =>
                            setFilter(
                                "department_id",
                                value === "all" ? null : value,
                            )
                        }
                    >
                        <SelectTrigger
                            size="sm"
                            aria-label="Filter departemen"
                            className="h-10 w-[170px]"
                        >
                            <SelectValue placeholder="Departemen" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Semua departemen
                            </SelectItem>
                            {filter_options.departments.map((option) => (
                                <SelectItem key={option.id} value={option.id}>
                                    {option.nama_department}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={appliedFilters.division_id ?? "all"}
                        onValueChange={(value) =>
                            setFilter(
                                "division_id",
                                value === "all" ? null : value,
                            )
                        }
                    >
                        <SelectTrigger
                            size="sm"
                            aria-label="Filter divisi"
                            className="h-10 w-[170px]"
                        >
                            <SelectValue placeholder="Divisi" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua divisi</SelectItem>
                            {filter_options.divisions.map((option) => (
                                <SelectItem key={option.id} value={option.id}>
                                    {option.nama_division}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={appliedFilters.position_id ?? "all"}
                        onValueChange={(value) =>
                            setFilter(
                                "position_id",
                                value === "all" ? null : value,
                            )
                        }
                    >
                        <SelectTrigger
                            size="sm"
                            aria-label="Filter posisi"
                            className="h-10 w-[150px]"
                        >
                            <SelectValue placeholder="Posisi" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua posisi</SelectItem>
                            {filter_options.positions.map((option) => (
                                <SelectItem key={option.id} value={option.id}>
                                    {option.nama_position}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            appliedFilters.has_account === null
                                ? "all"
                                : appliedFilters.has_account
                                  ? "1"
                                  : "0"
                        }
                        onValueChange={(value) =>
                            setFilter(
                                "has_account",
                                value === "all" ? null : value === "1",
                            )
                        }
                    >
                        <SelectTrigger
                            size="sm"
                            aria-label="Filter status akun"
                            className="h-10 w-[160px]"
                        >
                            <SelectValue placeholder="Akun" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua akun</SelectItem>
                            <SelectItem value="1">Punya akun</SelectItem>
                            <SelectItem value="0">Belum ada akun</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={appliedFilters.status}
                        onValueChange={(value) => setFilter("status", value)}
                    >
                        <SelectTrigger
                            size="sm"
                            aria-label="Filter status karyawan"
                            className="h-10 w-[140px]"
                        >
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua status</SelectItem>
                            <SelectItem value="active">Aktif</SelectItem>
                            <SelectItem value="banned">Diblokir</SelectItem>
                        </SelectContent>
                    </Select>

                    {activeFilterCount > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={clearFilters}
                            className="text-ink-muted hover:text-ink"
                        >
                            <SearchX className="size-3" />
                            Reset filter ({activeFilterCount})
                        </Button>
                    )}
                </div>

                {/* Mobile card list — a wide table is unusable below lg. */}
                <div className="space-y-3 px-4 lg:hidden lg:px-6">
                    {navigating && employeesData.length > 0 ? (
                        [...Array(3)].map((_, index) => (
                            <Skeleton
                                key={index}
                                className="h-44 rounded-xl"
                            />
                        ))
                    ) : employeesData.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border bg-card p-6 text-center">
                            <p className="text-sm font-semibold text-ink">
                                {hasActiveFilters
                                    ? "Tidak ada karyawan yang cocok."
                                    : "Belum ada karyawan."}
                            </p>
                            <p className="mt-1 text-sm text-ink-muted">
                                {hasActiveFilters
                                    ? "Coba kata kunci lain atau reset filter."
                                    : "Sinkronkan data dari Optigate untuk menampilkan daftar karyawan."}
                            </p>
                            <div className="mt-4 flex justify-center">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={
                                        hasActiveFilters
                                            ? clearFilters
                                            : runSync
                                    }
                                    className="h-10"
                                >
                                    {hasActiveFilters
                                        ? "Reset filter"
                                        : syncing
                                          ? "Menyinkronkan…"
                                          : "Sinkronkan"}
                                </Button>
                            </div>
                        </div>
                    ) : (
                        employeesData.map((employee) => (
                            <EmployeeCard
                                key={employee.id}
                                employee={employee}
                                tenant={tenant}
                                onBanToggle={handleBanToggle}
                                canBan={canBan}
                            />
                        ))
                    )}
                </div>

                {/* Desktop table */}
                <div
                    className="hidden overflow-hidden rounded-lg border bg-card shadow-card transition-opacity duration-150 lg:block aria-busy:pointer-events-none aria-busy:opacity-60"
                    aria-busy={navigating}
                >
                    <Table>
                        <TableHeader className="bg-surface-sunken">
                            <TableRow className="hover:bg-transparent">
                                {visibleColumns.includes("nik") && (
                                    <TableHead
                                        className="w-32"
                                        aria-sort={ariaSortFor("nik_employee")}
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-auto w-full justify-start p-0 text-xs font-semibold"
                                            onClick={() =>
                                                handleSort("nik_employee")
                                            }
                                        >
                                            <div className="flex items-center gap-1.5">
                                                <Hash className="size-3" />
                                                NIK
                                                {sortIcon("nik_employee")}
                                            </div>
                                        </Button>
                                    </TableHead>
                                )}
                                {visibleColumns.includes("name") && (
                                    <TableHead
                                        className="min-w-[200px]"
                                        aria-sort={ariaSortFor("nama_employee")}
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-auto w-full justify-start p-0 text-xs font-semibold"
                                            onClick={() =>
                                                handleSort("nama_employee")
                                            }
                                        >
                                            <div className="flex items-center gap-1.5">
                                                <User className="size-3" />
                                                Nama
                                                {sortIcon("nama_employee")}
                                            </div>
                                        </Button>
                                    </TableHead>
                                )}
                                {visibleColumns.includes("department") && (
                                    <TableHead className="w-44">
                                        <span className="flex items-center gap-1.5 text-xs font-semibold text-ink-muted">
                                            <Building2 className="size-3" />
                                            Departemen
                                        </span>
                                    </TableHead>
                                )}
                                {visibleColumns.includes("position") && (
                                    <TableHead
                                        className="min-w-[160px]"
                                        aria-sort="none"
                                    >
                                        <span className="text-xs font-semibold text-ink-muted">
                                            Posisi
                                        </span>
                                    </TableHead>
                                )}
                                {visibleColumns.includes("division") && (
                                    <TableHead
                                        className="min-w-[160px]"
                                        aria-sort="none"
                                    >
                                        <span className="text-xs font-semibold text-ink-muted">
                                            Divisi
                                        </span>
                                    </TableHead>
                                )}
                                {visibleColumns.includes("phone") && (
                                    <TableHead
                                        className="min-w-[140px]"
                                        aria-sort="none"
                                    >
                                        <span className="text-xs font-semibold text-ink-muted">
                                            Telepon
                                        </span>
                                    </TableHead>
                                )}
                                {visibleColumns.includes("email") && (
                                    <TableHead
                                        className="min-w-[200px]"
                                        aria-sort="none"
                                    >
                                        <span className="text-xs font-semibold text-ink-muted">
                                            Email
                                        </span>
                                    </TableHead>
                                )}
                                {visibleColumns.includes("joined") && (
                                    <TableHead
                                        className="w-40"
                                        aria-sort={ariaSortFor("created_at")}
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-auto w-full justify-start p-0 text-xs font-semibold"
                                            onClick={() =>
                                                handleSort("created_at")
                                            }
                                        >
                                            <div className="flex items-center gap-1.5">
                                                <Calendar className="size-3" />
                                                Data dibuat
                                                {sortIcon("created_at")}
                                            </div>
                                        </Button>
                                    </TableHead>
                                )}
                                {visibleColumns.includes("akun") && (
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
                                    <SkeletonRow
                                        key={i}
                                        visibleColumns={visibleColumns}
                                    />
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
                                                        atau{" "}
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
                                                        Sinkronkan data dari
                                                        Optigate untuk
                                                        menampilkan daftar
                                                        karyawan.
                                                    </p>
                                                    <div className="mt-4 flex flex-wrap justify-center gap-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={syncing}
                                                            onClick={runSync}
                                                        >
                                                            <RefreshCw
                                                                aria-hidden
                                                                className={cn(
                                                                    "mr-1.5",
                                                                    syncing &&
                                                                        "animate-spin",
                                                                )}
                                                            />
                                                            {syncing
                                                                ? "Menyinkronkan…"
                                                                : "Sinkronkan"}
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
                            Menampilkan {pagination.from}–{pagination.to} dari{" "}
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
                                Halaman {pagination.current_page} dari{" "}
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
            className={cn("size-4", className)}
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
            className={cn("size-4", className)}
        >
            <path d="m6 9 6 6 6-6" />
        </svg>
    );
}

Employees.layout = {
    breadcrumbs: [{ title: "Data Master" }, { title: "Karyawan" }],
    title: "Karyawan",
    description: "Data karyawan dalam cabang aktif",
    actions: EmployeeActions,
};
