import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    Briefcase,
    Info,
    Layers,
    UserRoundX,
    Users,
} from 'lucide-react';
import { index } from '@/routes/departments';
import { index as employeesIndex } from '@/routes/employees';
import { RegistryBand } from '@/components/registry-band';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { useRequiredTenant } from '@/lib/tenant';
import type { InertiaConfig } from '@inertiajs/core';

type HodDisplay = {
    name: string;
    email: string | null;
    photo_url: string | null;
};

type Division = {
    id: string;
    kode_division: string | null;
    nama_division: string;
};

type Position = {
    id: string;
    nama_position: string;
    division: { nama_division: string } | null;
};

type Department = {
    id: string;
    kode_department: string;
    nama_department: string | null;
    divisions: Division[];
    positions: Position[];
    hod_display: HodDisplay | null;
    manager: { name: string; email: string | null } | null;
    employees_count: number;
    positions_count: number;
    divisions_count: number;
};

type PageProps = {
    department: Department;
};

/** A section plate with a tracked caption and a count seal. */
function RelationCard({
    title,
    count,
    empty,
    children,
}: {
    title: string;
    count: number;
    empty: string;
    children: React.ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-xl border border-border bg-card shadow-card">
            <header className="flex items-center justify-between gap-3 border-b border-border bg-surface-raised/50 px-5 py-3">
                <h2 className="text-[10px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                    {title}
                </h2>
                <span className="rounded-full border border-border bg-card px-2 py-0.5 font-mono text-[10px] font-medium text-ink-muted tabular-nums">
                    {count}
                </span>
            </header>

            {count === 0 ? (
                <p className="px-5 py-6 text-[13px] leading-relaxed text-ink-muted">
                    {empty}
                </p>
            ) : (
                children
            )}
        </section>
    );
}

/** One linked relation row: ordinal, name, identifier, and a hover arrow. */
function RelationRow({
    ordinal,
    name,
    meta,
    href,
}: {
    ordinal: number;
    name: string;
    meta: string | null;
    href?: string;
}) {
    const content = (
        <>
            <span className="w-6 shrink-0 font-mono text-[10px] font-medium text-ink-muted tabular-nums">
                {String(ordinal).padStart(2, '0')}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-[13px] font-medium text-ink">
                    {name}
                </span>
                {meta && (
                    <span className="mt-0.5 block truncate font-mono text-[10px] tracking-[0.1em] text-ink-muted uppercase">
                        {meta}
                    </span>
                )}
            </span>
            {href && (
                <ArrowUpRight
                    aria-hidden="true"
                    className="size-3.5 shrink-0 text-ink-subtle opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100"
                />
            )}
        </>
    );

    return (
        <li className="border-b border-border last:border-0">
            {href ? (
                <Link
                    href={href}
                    className="group flex items-center gap-3 px-5 py-3 transition-colors hover:bg-surface-raised focus-visible:bg-surface-raised focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-inset focus-visible:outline-none"
                >
                    {content}
                </Link>
            ) : (
                <div className="flex items-center gap-3 px-5 py-3">
                    {content}
                </div>
            )}
        </li>
    );
}

export default function ShowDepartment({ department }: PageProps) {
    const getInitials = useInitials();
    const tenant = useRequiredTenant('departments.show');
    const name = department.nama_department ?? 'Tanpa nama';

    return (
        <>
            <Head title={department.kode_department} />

            <div className="space-y-5">
                <RegistryBand
                    label={`Ringkasan ${name}`}
                    stats={[
                        {
                            label: 'Karyawan',
                            value: department.employees_count,
                            icon: Users,
                        },
                        {
                            label: 'Divisi',
                            value: department.divisions_count,
                            icon: Layers,
                        },
                        {
                            label: 'Posisi',
                            value: department.positions_count,
                            icon: Briefcase,
                        },
                    ]}
                />

                <div className="grid items-start gap-5 lg:grid-cols-12">
                    <div className="space-y-5 lg:col-span-5">
                        <section className="overflow-hidden rounded-xl border border-border bg-card shadow-card">
                            <header className="flex items-center justify-between gap-3 border-b border-border bg-surface-raised/50 px-5 py-3">
                                <h2 className="text-[10px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                                    Penanggung jawab
                                </h2>
                                <span
                                    className={cn(
                                        'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em]',
                                        department.hod_display
                                            ? 'border-brand/25 bg-brand-soft text-brand'
                                            : 'border-warning/30 bg-warning-subtle text-warning-ink',
                                    )}
                                >
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'size-1.5 rounded-full',
                                            department.hod_display
                                                ? 'bg-brand'
                                                : 'bg-warning',
                                        )}
                                    />
                                    {department.hod_display ? 'HOD' : 'Kosong'}
                                </span>
                            </header>

                            <div className="p-5">
                                {department.hod_display ? (
                                    <div className="flex items-start gap-3.5">
                                        <Avatar className="size-12 shrink-0 rounded-full ring-1 ring-border">
                                            <AvatarImage
                                                src={
                                                    department.hod_display
                                                        .photo_url ?? undefined
                                                }
                                                alt={
                                                    department.hod_display.name
                                                }
                                            />
                                            <AvatarFallback className="rounded-full bg-brand-soft text-sm font-semibold text-brand">
                                                {getInitials(
                                                    department.hod_display.name,
                                                )}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="min-w-0 pt-0.5">
                                            <p className="truncate text-[15px] leading-tight font-semibold text-ink">
                                                {department.hod_display.name}
                                            </p>
                                            {department.hod_display.email && (
                                                <p className="mt-1 truncate text-xs text-ink-muted">
                                                    {
                                                        department.hod_display
                                                            .email
                                                    }
                                                </p>
                                            )}
                                            <p className="mt-2 text-[10px] font-semibold tracking-[0.12em] text-ink-muted uppercase">
                                                Head of Department
                                            </p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-start gap-3.5">
                                        <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-warning-subtle text-warning-ink">
                                            <UserRoundX
                                                aria-hidden="true"
                                                className="size-5"
                                            />
                                        </span>
                                        <div className="min-w-0 pt-0.5">
                                            <p className="text-[15px] leading-tight font-semibold text-ink">
                                                Belum ada HOD
                                            </p>
                                            <p className="mt-1.5 text-xs leading-relaxed text-ink-muted">
                                                Kursi HOD ditetapkan di
                                                Optigate, lalu ikut tersinkron ke
                                                sini.
                                            </p>
                                        </div>
                                    </div>
                                )}

                                <dl className="mt-5 border-t border-border pt-4">
                                    <div className="flex items-start justify-between gap-4">
                                        <dt className="pt-0.5 text-xs text-ink-muted">
                                            Deputy / Manager
                                        </dt>
                                        <dd className="min-w-0 text-right">
                                            <p className="truncate text-[13px] font-medium text-ink">
                                                {department.manager?.name ??
                                                    '—'}
                                            </p>
                                            {department.manager?.email && (
                                                <p className="mt-0.5 truncate text-[11px] text-ink-muted">
                                                    {department.manager.email}
                                                </p>
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <Link
                                href={employeesIndex.url(
                                    { tenant },
                                    {
                                        query: {
                                            department_id: department.id,
                                        },
                                    },
                                )}
                                className="group flex items-center justify-between border-t border-border bg-surface-raised/50 px-5 py-3 text-xs font-semibold text-brand transition-colors hover:bg-surface-raised focus-visible:bg-surface-raised focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-inset focus-visible:outline-none"
                            >
                                Lihat semua karyawan
                                <ArrowUpRight
                                    aria-hidden="true"
                                    className="size-3.5 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                                />
                            </Link>
                        </section>

                        <RelationCard
                            title="Divisi"
                            count={department.divisions.length}
                            empty="Belum ada divisi yang dipetakan ke department ini."
                        >
                            <ul>
                                {department.divisions.map((division, i) => (
                                    <RelationRow
                                        key={division.id}
                                        ordinal={i + 1}
                                        name={division.nama_division}
                                        meta={division.kode_division}
                                        href={employeesIndex.url(
                                            { tenant },
                                            {
                                                query: {
                                                    division_id: division.id,
                                                },
                                            },
                                        )}
                                    />
                                ))}
                            </ul>
                        </RelationCard>
                    </div>

                    <div className="space-y-5 lg:col-span-7">
                        <RelationCard
                            title="Posisi"
                            count={department.positions.length}
                            empty="Belum ada posisi yang terdaftar di department ini."
                        >
                            <ul>
                                {department.positions.map((position, i) => (
                                    <RelationRow
                                        key={position.id}
                                        ordinal={i + 1}
                                        name={position.nama_position}
                                        meta={
                                            position.division
                                                ?.nama_division ?? null
                                        }
                                        href={employeesIndex.url(
                                            { tenant },
                                            {
                                                query: {
                                                    position_id: position.id,
                                                },
                                            },
                                        )}
                                    />
                                ))}
                            </ul>
                        </RelationCard>

                        <p className="flex items-start gap-2 px-1 text-[11px] leading-relaxed text-ink-muted">
                            <Info
                                aria-hidden="true"
                                className="mt-0.5 size-3.5 shrink-0"
                            />
                            Department, HOD, dan struktur di bawahnya
                            disinkronkan dari Optigate. Perubahan dilakukan di
                            portal, bukan di halaman ini.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

/**
 * The heading band is owned by the shell, so the page title is an `<h1>` and
 * the breadcrumb trail is the way back to the list.
 *
 * The layout is a callback because the title and the trail depend on the
 * page's own props. A static layout object cannot do that: Inertia spreads
 * its values straight into the layout, so a function stored under `title`
 * would be rendered as a React child and throw.
 */
ShowDepartment.layout = (
    props: PageProps & InertiaConfig['sharedPageProps'],
) => {
    const tenant = props.activeTenant ?? undefined;
    const department = props.department;
    const name = department.nama_department ?? department.kode_department;

    const summary = [
        department.kode_department,
        `${department.divisions_count} divisi`,
        `${department.employees_count} karyawan`,
    ];

    if (!department.hod_display) {
        summary.push('HOD belum ditunjuk');
    }

    return {
        breadcrumbs: [
            { title: 'Data Master' },
            {
                title: 'Department',
                href: tenant ? index({ tenant }) : undefined,
            },
            { title: name },
        ],
        title: name,
        description: summary.join(' · '),
    };
};
