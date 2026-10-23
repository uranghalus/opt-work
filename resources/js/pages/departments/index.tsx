import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    ChevronLeft,
    ChevronRight,
    LoaderCircle,
    RefreshCw,
    UserRoundX,
    Users,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import { show, sync } from '@/routes/departments';
import { index as employeesIndex } from '@/routes/employees';
import { RegistryBand } from '@/components/registry-band';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { useRequiredTenant } from '@/lib/tenant';
import type { InertiaConfig } from '@inertiajs/core';

type HodDisplay = {
    name: string;
    email: string | null;
    photo_url: string | null;
};

type Department = {
    id: string;
    kode_department: string;
    nama_department: string | null;
    hod_display: HodDisplay | null;
    employees_count: number;
    positions_count: number;
    divisions_count: number;
    divisions: { id: string; nama_division: string }[];
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

type Stats = {
    departments: number;
    employees: number;
    without_hod: number;
    max_employees: number;
};

type PageProps = {
    departments: Pagination & { data: Department[] };
    stats: Stats;
    last_synced_at: string | null;
    last_synced_label: string | null;
};

type FlashProps = PageProps &
    InertiaConfig['sharedPageProps'] & {
        flash?: { success?: string | null; error?: string | null };
    };

/**
 * Focus ring for the interactive cells.
 *
 * Every link in the registry gets the same 2px ring + 2px offset as the rest
 * of the app, so keyboard users can follow a row without guessing where focus
 * went.
 */
const linkFocusRing =
    'rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2 focus-visible:ring-offset-card';

/** HOD cell: linked akun avatar + name, or an explicit data-quality chip. */
function HodCell({ hod }: { hod: HodDisplay | null }) {
    const getInitials = useInitials();

    if (!hod) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full border border-warning/30 bg-warning-subtle px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap text-warning-ink">
                <span
                    aria-hidden="true"
                    className="size-1.5 rounded-full bg-warning"
                />
                Tanpa HOD
            </span>
        );
    }

    return (
        <div className="flex min-w-0 items-center gap-2.5">
            <Avatar className="size-8 shrink-0 rounded-full ring-1 ring-border">
                <AvatarImage src={hod.photo_url ?? undefined} alt={hod.name} />
                <AvatarFallback className="rounded-full bg-brand-soft text-[10px] font-semibold text-brand">
                    {getInitials(hod.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid min-w-0 leading-tight">
                <span className="truncate text-[13px] font-medium text-ink">
                    {hod.name}
                </span>
                {hod.email && (
                    <span className="mt-0.5 truncate text-[11px] text-ink-muted">
                        {hod.email}
                    </span>
                )}
            </div>
        </div>
    );
}

/** Headcount: the number plus a meter scaled to the largest department. */
function Headcount({
    count,
    max,
    href,
    label,
    caption,
    className,
}: {
    count: number;
    max: number;
    href?: string;
    label: string;
    caption?: string;
    className?: string;
}) {
    const width =
        max > 0 ? Math.min(100, Math.max(4, (count / max) * 100)) : 0;

    const number = (
        <span className="text-[1.0625rem] leading-none font-semibold tabular-nums text-ink">
            {count}
        </span>
    );

    return (
        <div className={cn('flex flex-col items-end gap-1.5', className)}>
            {href ? (
                <Link
                    href={href}
                    aria-label={label}
                    className={cn(
                        linkFocusRing,
                        'underline-offset-2 transition-colors hover:text-brand hover:underline',
                    )}
                >
                    {number}
                </Link>
            ) : (
                number
            )}
            <span
                aria-hidden="true"
                className="h-1 w-16 overflow-hidden rounded-full bg-surface-sunken"
            >
                <span
                    className="block h-full rounded-full bg-brand"
                    style={{ width: `${width}%` }}
                />
            </span>
            {caption && (
                <span className="text-[9px] font-semibold tracking-[0.12em] text-ink-muted uppercase">
                    {caption}
                </span>
            )}
        </div>
    );
}

/** One department as a card, for widths where the registry cannot breathe. */
function DepartmentCard({
    department,
    stats,
    tenant,
}: {
    department: Department;
    stats: Stats;
    tenant: string;
}) {
    const name = department.nama_department ?? 'Tanpa nama';

    return (
        <li className="bg-card">
            <Link
                href={show({ tenant, department: department.id })}
                className="group block px-4 py-4 transition-colors hover:bg-surface-raised focus-visible:bg-surface-raised focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-inset focus-visible:outline-none"
            >
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <p className="truncate font-mono text-[10px] font-medium tracking-[0.14em] text-ink-muted uppercase">
                            {department.kode_department}
                        </p>
                        <p className="mt-0.5 truncate text-[15px] font-semibold tracking-[-0.01em] text-ink">
                            {name}
                        </p>
                    </div>
                    <Headcount
                        count={department.employees_count}
                        max={stats.max_employees}
                        label={`${department.employees_count} karyawan di ${name}`}
                        caption="karyawan"
                        className="shrink-0"
                    />
                </div>

                <div className="mt-3 flex items-center justify-between gap-3">
                    <HodCell hod={department.hod_display} />
                    <span className="shrink-0 text-[11px] text-ink-muted tabular-nums">
                        {department.divisions_count} divisi
                    </span>
                </div>
            </Link>
        </li>
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
                {pagination.total} department
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

/**
 * Primary action of the page, rendered in the shell's heading band.
 *
 * The timestamp lives beside the button because they are one feature: the
 * button changes the value the caption reports.
 */
function DepartmentActions() {
    const tenant = useRequiredTenant('departments.index');
    const { last_synced_at, last_synced_label } =
        usePage<PageProps>().props;
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

    return (
        <div className="flex items-center gap-3">
            {last_synced_label && (
                <p className="hidden text-right text-[10px] leading-tight text-ink-muted sm:block">
                    <span className="block font-semibold tracking-[0.1em] text-ink-muted uppercase">
                        Tersinkron
                    </span>
                    <time
                        dateTime={last_synced_at ?? undefined}
                        className="mt-0.5 block font-medium tabular-nums"
                    >
                        {last_synced_label}
                    </time>
                </p>
            )}

            <Button
                onClick={runSync}
                disabled={syncing}
                size="lg"
                className="h-10 gap-2 px-3 shadow-card sm:px-4"
            >
                {syncing ? (
                    <>
                        <LoaderCircle aria-hidden="true" className="animate-spin" />
                        <span className="hidden sm:inline">Menyinkronkan…</span>
                    </>
                ) : (
                    <>
                        <RefreshCw aria-hidden="true" />
                        <span className="hidden sm:inline">Sinkronkan</span>
                    </>
                )}
            </Button>
        </div>
    );
}

DepartmentActions.displayName = 'DepartmentActions';

Departments.layout = {
    breadcrumbs: [{ title: 'Data Master' }, { title: 'Department' }],
    title: 'Department',
    description: 'Struktur organisasi dan HOD cabang aktif.',
    actions: DepartmentActions,
};

export default function Departments(props: PageProps) {
    const { departments, stats } = props;
    const tenant = useRequiredTenant('departments.index');
    const { flash } = usePage<FlashProps>().props as FlashProps;

    const { data, ...pagination } = departments;
    const isEmpty = data.length === 0;

    return (
        <>
            <Head title="Department" />

            <div className="space-y-5">
                {flash?.success && (
                    <Alert role="status">
                        <AlertDescription>
                            {String(flash.success)}
                        </AlertDescription>
                    </Alert>
                )}

                {flash?.error && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                <RegistryBand
                    label="Ringkasan organisasi"
                    stats={[
                        {
                            label: 'Department',
                            value: stats.departments,
                            icon: Building2,
                        },
                        {
                            label: 'Karyawan',
                            value: stats.employees,
                            icon: Users,
                        },
                        {
                            label: 'Tanpa HOD',
                            value: stats.without_hod,
                            icon: UserRoundX,
                            tone:
                                stats.without_hod > 0 ? 'warning' : 'default',
                        },
                    ]}
                />

                {isEmpty ? (
                    <div className="rounded-xl border border-dashed border-border-strong/60 bg-card px-6 py-16 text-center shadow-card">
                        <span
                            aria-hidden="true"
                            className="mx-auto flex size-12 items-center justify-center rounded-xl bg-brand/10 text-brand"
                        >
                            <Building2 className="size-5" />
                        </span>
                        <p className="mt-4 text-base font-semibold tracking-[-0.01em] text-ink">
                            Belum ada department
                        </p>
                        <p className="mx-auto mt-1.5 max-w-sm text-sm leading-relaxed text-ink-muted">
                            Department ditarik dari Optigate. Jalankan
                            sinkronisasi untuk memuat struktur organisasi
                            cabang ini.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-border bg-card shadow-card">
                        {/* Below the registry breakpoint: cards keep every field
                            visible instead of squeezing the table. */}
                        <ul className="grid grid-cols-1 divide-y divide-border lg:grid-cols-2 lg:gap-x-8 lg:gap-y-2 lg:divide-y-0 xl:hidden">
                            {data.map((department) => (
                                <DepartmentCard
                                    key={department.id}
                                    department={department}
                                    stats={stats}
                                    tenant={tenant}
                                />
                            ))}
                        </ul>

                        <div className="hidden xl:block">
                            <Table className="table-fixed">
                                <colgroup>
                                    <col className="w-[20rem]" />
                                    <col className="w-[15rem]" />
                                    <col />
                                    <col className="w-[8rem]" />
                                </colgroup>
                                <TableHeader>
                                    <TableRow className="border-b border-border-strong bg-surface-raised hover:bg-surface-raised">
                                        <TableHead
                                            scope="col"
                                            className="h-9 pl-5 text-[10px] font-semibold tracking-[0.14em] text-ink-muted uppercase"
                                        >
                                            Department
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-9 text-[10px] font-semibold tracking-[0.14em] text-ink-muted uppercase"
                                        >
                                            HOD
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-9 text-[10px] font-semibold tracking-[0.14em] text-ink-muted uppercase"
                                        >
                                            Divisi
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-9 pr-5 text-right text-[10px] font-semibold tracking-[0.14em] text-ink-muted uppercase"
                                        >
                                            Karyawan
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {data.map((department) => {
                                        const name =
                                            department.nama_department ??
                                            'Tanpa nama';
                                        const divisionNames =
                                            department.divisions.map(
                                                (division) =>
                                                    division.nama_division,
                                            );

                                        return (
                                            <TableRow
                                                key={department.id}
                                                className="group/row border-border last:border-0"
                                            >
                                                <TableCell className="relative py-3 pl-5">
                                                    <span
                                                        aria-hidden="true"
                                                        className="absolute inset-y-0 left-0 w-0.5 bg-brand opacity-0 transition-opacity duration-150 group-hover/row:opacity-100 group-focus-within/row:opacity-100"
                                                    />
                                                    <p className="truncate font-mono text-[10px] font-medium tracking-[0.14em] text-ink-muted uppercase">
                                                        {department.kode_department}
                                                        <span
                                                            aria-hidden="true"
                                                            className="mx-2 text-border-strong"
                                                        >
                                                            ·
                                                        </span>
                                                        {
                                                            department.positions_count
                                                        }{' '}
                                                        posisi
                                                    </p>
                                                    <p className="mt-1 truncate text-[15px] leading-tight font-semibold tracking-[-0.01em]">
                                                        <Link
                                                            href={show({
                                                                tenant,
                                                                department:
                                                                    department.id,
                                                            })}
                                                            className={cn(
                                                                linkFocusRing,
                                                                'text-ink transition-colors group-hover/row:text-brand',
                                                            )}
                                                        >
                                                            {name}
                                                        </Link>
                                                    </p>
                                                </TableCell>

                                                <TableCell className="py-3">
                                                    <HodCell
                                                        hod={
                                                            department.hod_display
                                                        }
                                                    />
                                                </TableCell>

                                                <TableCell className="py-3">
                                                    {divisionNames.length >
                                                    0 ? (
                                                        <p
                                                            className="truncate text-[12.5px] leading-relaxed text-ink-muted"
                                                            title={divisionNames.join(
                                                                ' · ',
                                                            )}
                                                        >
                                                            {divisionNames.join(
                                                                ' · ',
                                                            )}
                                                        </p>
                                                    ) : (
                                                        <span className="text-[12.5px] text-ink-muted">
                                                            —
                                                        </span>
                                                    )}
                                                </TableCell>

                                                <TableCell className="py-3 pr-5">
                                                    <Headcount
                                                        count={
                                                            department.employees_count
                                                        }
                                                        max={
                                                            stats.max_employees
                                                        }
                                                        href={employeesIndex.url(
                                                            { tenant },
                                                            {
                                                                query: {
                                                                    department_id:
                                                                        department.id,
                                                                },
                                                            },
                                                        )}
                                                        label={`Lihat ${department.employees_count} karyawan di ${name}`}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>

                        {pagination.last_page > 1 && (
                            <PaginationFooter pagination={pagination} />
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
