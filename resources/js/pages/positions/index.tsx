import { Head } from "@inertiajs/react";
import { router, usePage } from "@inertiajs/react";
import { Briefcase, Building2, GitBranch, LoaderCircle, RefreshCw, Search, SearchX, Users, UserCheck, UserX, ChevronLeft, ChevronRight, ExternalLink, Layers } from "lucide-react";
import { useCallback, useState, useEffect, useRef } from "react";
import { index } from "@/routes/positions";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { RegistryBand } from "@/components/registry-band";
import { Headcount } from "@/components/registry/Headcount";
import { PaginationFooter } from "@/components/registry/PaginationFooter";
import { PositionRow } from "@/components/positions/PositionRow";
import { PositionCardMobile } from "@/components/positions/PositionCardMobile";
import { EmptyState } from "@/components/ui/EmptyState";
import { cn } from "@/lib/utils";
import { useRequiredTenant } from "@/lib/tenant";
import type { InertiaConfig } from "@inertiajs/core";

type Position = {
    id: string;
    nama_position: string;
    department?: { id: string; kode_department: string; nama_department: string | null } | null;
    division?: { id: string; nama_division: string } | null;
    optigate_position_id?: string | null;
    category?: "normal" | "accident" | "owner";
    employees_count: number;
    level?: number | null;
    level_label?: string | null;
    is_vacant?: boolean;
    is_direct?: boolean;
};

type Pagination = {
    data: Position[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Stats = {
    total: number;
    filled: number;
    synced: number;
    max_employees: number;
};

type PageProps = {
    positions: Pagination;
    stats: Stats;
};

type FlashProps = PageProps &
    InertiaConfig["sharedPageProps"] & {
        flash?: { success?: string | null; error?: string | null };
    };

/** Focus ring for interactive cells — matches app standard */
const linkFocusRing =
    "rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2 focus-visible:ring-offset-surface";

/** Sticky filter bar — search + department + division + has_employees */
function FilterBar({
    tenant,
    departments,
    divisions,
    appliedFilters,
    setAppliedFilters,
    navigating,
}: {
    tenant: string;
    departments: { id: string; kode_department: string; nama_department: string | null }[];
    divisions: { id: string; nama_division: string }[];
    appliedFilters: Filters;
    setAppliedFilters: React.Dispatch<React.SetStateAction<Filters>>;
    navigating: boolean;
}) {
    const setFilter = <K extends keyof Filters>(key: K, value: Filters[K]) =>
        setAppliedFilters((current) => ({
            ...current,
            [key]: value,
            page: 1,
        }));

    // Handle vacancy filter separately since it maps to is_vacant
    const setVacancyFilter = (value: "all" | "occupied" | "vacant" | null) =>
        setAppliedFilters((current) => ({
            ...current,
            vacancy: value === "all" ? null : value,
            page: 1,
        }));

    return (
        <div className="sticky top-16 z-40 flex flex-col gap-3 border-b border-border bg-background/95 px-4 py-3 backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between lg:px-6">
            {/* Search */}
            <div className="relative w-full max-w-xs sm:max-w-md">
                <Search
                    aria-hidden
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-subtle"
                />
                <label className="sr-only" htmlFor="position-search">
                    Cari nama position
                </label>
                <Input
                    id="position-search"
                    type="search"
                    placeholder="Cari position…"
                    value={appliedFilters.search}
                    onChange={(event) => setAppliedFilters((c) => ({ ...c, search: event.target.value, page: 1 }))}
                    className="h-10 pl-9"
                    disabled={navigating}
                />
                {appliedFilters.search && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="absolute top-1/2 right-2 h-10 w-10 -translate-y-1/2 text-ink-muted hover:text-ink"
                        onClick={() => setAppliedFilters((c) => ({ ...c, search: "", page: 1 }))}
                        aria-label="Bersihkan pencarian"
                    >
                        <SearchX className="size-4" />
                    </Button>
                )}
            </div>

            {/* Filters */}
            <div className="flex flex-wrap items-center gap-3">
                <Select
                    value={appliedFilters.department_id ?? "all"}
                    onValueChange={(value) =>
                        setFilter("department_id", value === "all" ? null : value)
                    }
                    disabled={navigating}
                >
                    <SelectTrigger size="sm" aria-label="Filter department" className="h-10 w-[170px]">
                        <SelectValue placeholder="Department" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua department</SelectItem>
                        {departments.map((d) => (
                            <SelectItem key={d.id} value={d.id}>
                                {d.kode_department} — {d.nama_department ?? "Tanpa nama"}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select
                    value={appliedFilters.division_id ?? "all"}
                    onValueChange={(value) =>
                        setFilter("division_id", value === "all" ? null : value)
                    }
                    disabled={navigating}
                >
                    <SelectTrigger size="sm" aria-label="Filter divisi" className="h-10 w-[170px]">
                        <SelectValue placeholder="Divisi" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua divisi</SelectItem>
                        {divisions.map((d) => (
                            <SelectItem key={d.id} value={d.id}>
                                {d.nama_division}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select
                    value={appliedFilters.has_employees ?? "all"}
                    onValueChange={(value) =>
                        setFilter("has_employees", value === "all" ? null : value as "yes" | "no")
                    }
                    disabled={navigating}
                >
                    <SelectTrigger size="sm" aria-label="Filter posisi terisi" className="h-10 w-[150px]">
                        <SelectValue placeholder="Terisi" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua</SelectItem>
                        <SelectItem value="yes">Punya karyawan</SelectItem>
                        <SelectItem value="no">Kosong</SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    value={appliedFilters.vacancy ?? "all"}
                    onValueChange={(value) =>
                        setVacancyFilter(value === "all" ? null : (value as "occupied" | "vacant"))
                    }
                    disabled={navigating}
                >
                    <SelectTrigger size="sm" aria-label="Filter kosong/terisi" className="h-10 w-[150px]">
                        <SelectValue placeholder="Kosong/Terisi" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua</SelectItem>
                        <SelectItem value="occupied">Terisi</SelectItem>
                        <SelectItem value="vacant">Kosong</SelectItem>
                    </SelectContent>
                </Select>

                {[
                    appliedFilters.department_id !== null,
                    appliedFilters.division_id !== null,
                    appliedFilters.has_employees !== null,
                    appliedFilters.vacancy !== null,
                ].filter(Boolean).length > 0 && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            setAppliedFilters({
                                search: "",
                                department_id: null,
                                division_id: null,
                                has_employees: null,
                                vacancy: null,
                                page: 1,
                            })
                        }
                        className="text-ink-muted hover:text-ink"
                    >
                        <SearchX className="size-3" />
                        Reset filter
                    </Button>
                )}
            </div>
        </div>
    );
}

/** Skeleton row for table loading state */
function SkeletonRow() {
    return (
        <TableRow>
            <TableCell className="relative py-3 pl-5">
                <Skeleton className="h-3 w-24" />
                <Skeleton className="mt-1 h-4 w-32" />
            </TableCell>
            <TableCell className="py-3">
                <Skeleton className="h-4 w-12" />
            </TableCell>
            <TableCell className="py-3">
                <Skeleton className="h-8 w-24" />
            </TableCell>
            <TableCell className="py-3">
                <Skeleton className="h-4 w-28" />
            </TableCell>
            <TableCell className="py-3 pr-5">
                <Skeleton className="h-5 w-16" />
                <Skeleton className="mt-1 h-1 w-16" />
            </TableCell>
        </TableRow>
    );
}

Positions.layout = {
    breadcrumbs: [{ title: "Data Master" }, { title: "Position" }],
    title: "Position",
    description: "Jabatan karyawan dalam cabang aktif",
};

type Filters = {
    search: string;
    department_id: string | null;
    division_id: string | null;
    has_employees: "all" | "yes" | "no" | null;
    vacancy: "all" | "occupied" | "vacant" | null;
    page: number;
};

export default function Positions(props: PageProps) {
    const { positions, stats } = props;
    const tenant = useRequiredTenant("positions.index");
    const { flash, auth } = usePage<FlashProps>().props;

    const [searchTerm, setSearchTerm] = useState("");
    const [appliedFilters, setAppliedFilters] = useState<Filters>({
        search: "",
        department_id: null,
        division_id: null,
        has_employees: null,
        vacancy: null,
        page: 1,
    });
    const [navigating, setNavigating] = useState(false);
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

        const params = new URLSearchParams();
        if (appliedFilters.search) params.set("search", appliedFilters.search);
        if (appliedFilters.department_id) params.set("department_id", appliedFilters.department_id);
        if (appliedFilters.division_id) params.set("division_id", appliedFilters.division_id);
        if (appliedFilters.has_employees && appliedFilters.has_employees !== "all") {
            params.set("has_employees", appliedFilters.has_employees === "yes" ? "1" : "0");
        }
        if (appliedFilters.vacancy && appliedFilters.vacancy !== "all") {
            params.set("vacancy", appliedFilters.vacancy);
        }

        router.get(
            index({ tenant }),
            Object.fromEntries(params),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setNavigating(true),
                onFinish: () => setNavigating(false),
            }
        );
    }, [appliedFilters, tenant]);

    const { data, current_page, last_page, total, from, to, prev_page_url, next_page_url } = positions;
    const isEmpty = data.length === 0;

    // Filter options untuk selects — kunci = ULID asli (server memfilter
    // where('department_id', …) pada FK), bukan kode/nama.
    const departmentMap = new Map<string, { id: string; kode_department: string; nama_department: string | null }>();
    const divisionMap = new Map<string, { id: string; nama_division: string }>();

    data.forEach((p) => {
        if (p.department?.id && p.department.kode_department) {
            departmentMap.set(p.department.id, {
                id: p.department.id,
                kode_department: p.department.kode_department,
                nama_department: p.department.nama_department ?? null,
            });
        }
        if (p.division?.id && p.division.nama_division) {
            divisionMap.set(p.division.id, {
                id: p.division.id,
                nama_division: p.division.nama_division,
            });
        }
    });

    const departments = Array.from(departmentMap.values());
    const divisions = Array.from(divisionMap.values());

    return (
        <>
            <Head title="Position" />

            <div className="space-y-5">
                {/* Flash messages */}
                {flash?.success && (
                    <div className="rounded-xl border border-success/30 bg-success-subtle p-4" role="status">
                        <p className="text-success-ink">{flash.success}</p>
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-xl border border-danger/30 bg-danger-subtle p-4" role="alert">
                        <p className="text-danger-ink">{flash.error}</p>
                    </div>
                )}

                {/* Registry Band — 3 metrics on panel stratum */}
                <RegistryBand
                    label="Ringkasan position"
                    stats={[
                        {
                            label: "Total Position",
                            value: stats.total,
                            icon: Briefcase,
                        },
                        {
                            label: "Terisi",
                            value: stats.filled,
                            icon: UserCheck,
                            tone: stats.filled === 0 ? "warning" : "default",
                        },
                        {
                            label: "Optigate Sync",
                            value: stats.synced,
                            icon: RefreshCw,
                        },
                    ]}
                />

                {/* Filter Bar (sticky) */}
                <FilterBar
                    tenant={tenant}
                    departments={departments}
                    divisions={divisions}
                    appliedFilters={appliedFilters}
                    setAppliedFilters={setAppliedFilters}
                    navigating={navigating}
                />

                {/* Content: Empty / Cards (<xl) / Table (≥xl) */}
                {isEmpty ? (
                    <EmptyState
                        icon={Briefcase}
                        title="Belum ada position"
                        description="Posisi akan muncul setelah sinkronisasi dari OptiGate."
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-card">
                        {/* Mobile/Tablet: Card grid (< xl) */}
                        <ul
                            className="grid grid-cols-1 divide-y divide-border lg:grid-cols-2 lg:gap-x-8 lg:gap-y-2 lg:divide-y-0 xl:hidden"
                            role="list"
                            aria-label="Daftar position"
                        >
                            {navigating && data.length > 0 ? (
                                [...Array(3)].map((_, i) => (
                                    <Skeleton key={i} className="h-36 rounded-xl" />
                                ))
                            ) : (
                                data.map((position) => (
                                    <PositionCardMobile
                                        key={position.id}
                                        position={position}
                                        tenant={tenant}
                                        maxEmployees={stats.max_employees}
                                    />
                                ))
                            )}
                        </ul>

                        {/* Desktop: Registry Table (≥ xl) */}
                        <div className="hidden xl:block">
                            <Table className="table-fixed">
                                <colgroup>
                                    <col className="w-[20rem]" />
                                    <col className="w-[6rem]" />
                                    <col className="w-[15rem]" />
                                    <col />
                                    <col className="w-[8rem]" />
                                </colgroup>
                                <TableHeader className="bg-surface-raised">
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead
                                            scope="col"
                                            className="h-9 pl-5 text-[10px] font-semibold tracking-[0.14em] text-ink-muted uppercase border-b border-border-strong"
                                        >
                                            Position
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-9 text-[10px] font-semibold tracking-[0.14em] text-ink-muted uppercase border-b border-border-strong"
                                        >
                                            Level
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-9 text-[10px] font-semibold tracking-[0.14em] text-ink-muted uppercase border-b border-border-strong"
                                        >
                                            Department
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-9 text-[10px] font-semibold tracking-[0.14em] text-ink-muted uppercase border-b border-border-strong"
                                        >
                                            Divisi
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-9 pr-5 text-right text-[10px] font-semibold tracking-[0.14em] text-ink-muted uppercase border-b border-border-strong"
                                        >
                                            Karyawan
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {navigating && data.length > 0 ? (
                                        [...Array(5)].map((_, i) => <SkeletonRow key={i} />)
                                    ) : (
                                        data.map((position) => (
                                            <PositionRow
                                                key={position.id}
                                                position={position}
                                                tenant={tenant}
                                                maxEmployees={stats.max_employees}
                                            />
                                        ))
                                    )}
                                </TableBody>
                            </Table>

                            {last_page > 1 && (
                                <PaginationFooter
                                    current_page={current_page}
                                    last_page={last_page}
                                    from={from}
                                    to={to}
                                    total={total}
                                    prev_page_url={prev_page_url}
                                    next_page_url={next_page_url}
                                    baseUrl={index({ tenant }).url}
                                />
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}