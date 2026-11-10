import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Bolt,
    CalendarClock,
    CheckCircle,
    ClipboardList,
    Clock,
    Construction,
    Cpu,
    Eye,
    Fan,
    HardHat,
    HeartPulse,
    History,
    ListFilter,
    MapPin,
    Plus,
    Search,
    ShieldCheck,
    Sparkles,
    Timer,
    TrendingUp,
    TriangleAlert,
    Upload,
    Wrench,
    Zap,
    type LucideIcon,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { InfoBanner } from '@/components/info-banner';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { create, index as workOrdersIndex } from '@/routes/work-orders';
import type { InertiaConfig } from '@inertiajs/core';
import { useRequiredTenant } from '@/lib/tenant';

/*
 * Dashboard utama — Executive overview home (Stitch reference, DESIGN.md §13).
 * Authored fixture data: the Work Order module has no dashboard controller yet,
 * so KPI scorecard, departmental health, priority queue and audit trail render
 * sample values. Swap for Inertia props when DashboardController ships.
 */

type KpiCard = {
    id: string;
    label: string;
    hint: string;
    icon: LucideIcon;
    iconWrap: string;
    value: string;
    unit?: string;
    chip: {
        text: string;
        className: string;
        icon?: LucideIcon;
        trend?: boolean;
    };
    progress?: { pct: number; caption: string };
    note?: ReactNode;
    footer: ReactNode;
};

const kpiCards: KpiCard[] = [
    {
        id: 'total-wo',
        label: 'Total Work Order',
        hint: 'Bulan Ini',
        icon: ClipboardList,
        iconWrap: 'bg-brand-strong/15 text-brand-strong',
        value: '142',
        chip: {
            text: '+8% MoM',
            className: 'bg-success-subtle text-success ring-success/25',
            icon: TrendingUp,
            trend: true,
        },
        progress: { pct: 88.8, caption: 'Target 160 WO' },
        footer: (
            <div className="flex flex-wrap items-center gap-3">
                <span className="flex items-center gap-1.5">
                    <span
                        aria-hidden="true"
                        className="size-2 shrink-0 rounded-full bg-success"
                    />
                    <span className="font-semibold text-ink tabular-nums">118</span>
                    <span className="text-ink-subtle">Selesai</span>
                </span>
                <span className="flex items-center gap-1.5">
                    <span
                        aria-hidden="true"
                        className="size-2 shrink-0 rounded-full bg-warning"
                    />
                    <span className="font-semibold text-ink tabular-nums">18</span>
                    <span className="text-ink-subtle">Berjalan</span>
                </span>
                <span className="flex items-center gap-1.5">
                    <span
                        aria-hidden="true"
                        className="size-2 shrink-0 rounded-full bg-info"
                    />
                    <span className="font-semibold text-ink tabular-nums">6</span>
                    <span className="text-ink-subtle">Approval</span>
                </span>
            </div>
        ),
    },
    {
        id: 'sla-rate',
        label: 'Kepatuhan SLA',
        hint: 'Resolusi tepat waktu',
        icon: ShieldCheck,
        iconWrap: 'bg-info-subtle text-info ring-info/25',
        value: '96.2%',
        chip: {
            text: 'Target ≥95%',
            className: 'bg-brand-soft text-brand ring-brand/25',
        },
        note: (
            <p className="text-xs text-ink-muted">
                Di atas targetinternal{' '}
                <span className="font-semibold text-success">+1.2%</span>
            </p>
        ),
        footer: (
            <div className="grid grid-cols-2 gap-2">
                <span className="rounded-md bg-surface-raised px-2 py-1 text-ink-subtle ring-1 ring-border ring-inset">
                    Eskalasi GM{' '}
                    <span className="font-semibold text-success">0</span>
                </span>
                <span className="rounded-md bg-warning-subtle px-2 py-1 text-ink-muted ring-1 ring-warning/25 ring-inset">
                    Eskalasi HOD{' '}
                    <span className="font-semibold text-warning">2</span>
                </span>
            </div>
        ),
    },
    {
        id: 'mttr',
        label: 'Rata-rata Penyelesaian',
        hint: 'Mean time to resolve',
        icon: Timer,
        iconWrap: 'bg-warning-subtle text-warning ring-warning/20',
        value: '4.2',
        unit: 'jam',
        chip: {
            text: '1.8 jam lebih cepat',
            className: 'bg-success-subtle text-success ring-success/25',
            icon: TrendingUp,
            trend: true,
        },
        note: (
            <div className="flex items-center gap-2 text-xs text-ink-muted">
                <span className="rounded-md bg-surface-raised px-2 py-1 ring-1 ring-border ring-inset">
                    Normal{' '}
                    <span className="font-semibold text-ink">1.8 hari</span>
                </span>
            </div>
        ),
        footer: (
            <span className="text-ink-subtle">
                Benchmark internal{' '}
                <span className="font-semibold text-ink">6.0 jam</span>
            </span>
        ),
    },
    {
        id: 'utilization',
        label: 'Utilisasi Tim',
        hint: 'Teknisi aktif hari ini',
        icon: HardHat,
        iconWrap: 'bg-warning-subtle text-warning ring-warning/20',
        value: '84%',
        chip: {
            text: 'Sehat',
            className: 'bg-success-subtle text-success ring-success/25',
        },
        progress: { pct: 84, caption: '32 dari 38 teknisi' },
        footer: (
            <div className="flex items-center justify-between gap-2">
                <span className="truncate text-ink-subtle">
                    MEP · BMS · Sipil · IT
                </span>
                <span className="shrink-0 rounded-full bg-brand-soft px-2 py-0.5 text-xs font-semibold text-brand">
                    6 standby
                </span>
            </div>
        ),
    },
];

type DepartmentRow = {
    name: string;
    scope: string;
    icon: LucideIcon;
    iconWrap: string;
    hod: string;
    hodRole: string;
    activeWo: number;
    urgent: boolean;
    doneRatio: number;
    ratioNote: string;
    barClass: string;
    sla: string;
    slaClass: string;
    healthy: boolean;
};

const departmentRows: DepartmentRow[] = [
    {
        name: 'MEP',
        scope: 'Gedung utama & annex',
        icon: Bolt,
        iconWrap: 'bg-linear-to-tr from-info to-brand',
        hod: 'Bambang S.',
        hodRole: 'HOD MEP',
        activeWo: 7,
        urgent: false,
        doneRatio: 87,
        ratioNote: '48 dari 55 WO',
        barClass: 'from-info to-brand',
        sla: '98.1%',
        slaClass: 'text-ink',
        healthy: true,
    },
    {
        name: 'HVAC & BMS',
        scope: 'Central plant & AHU',
        icon: Fan,
        iconWrap: 'bg-linear-to-tr from-brand to-brand-strong',
        hod: 'Ir. Firman H.',
        hodRole: 'HOD Thermal',
        activeWo: 5,
        urgent: false,
        doneRatio: 86,
        ratioNote: '31 dari 36 WO',
        barClass: 'from-brand to-brand-strong',
        sla: '95.4%',
        slaClass: 'text-ink',
        healthy: true,
    },
    {
        name: 'IT & Security',
        scope: 'CCTV, akses, network',
        icon: Cpu,
        iconWrap: 'bg-linear-to-tr from-info to-brand-deep',
        hod: 'Raditya P.',
        hodRole: 'Lead IT',
        activeWo: 3,
        urgent: false,
        doneRatio: 88,
        ratioNote: '22 dari 25 WO',
        barClass: 'from-info to-brand-deep',
        sla: '96.8%',
        slaClass: 'text-ink',
        healthy: true,
    },
    {
        name: 'Sipil & Interior',
        scope: 'Struktural & penyewa',
        icon: Construction,
        iconWrap: 'bg-linear-to-tr from-warning to-owner-urgent',
        hod: 'Agus T.',
        hodRole: 'HOD Civil',
        activeWo: 2,
        urgent: true,
        doneRatio: 67,
        ratioNote: '12 dari 18 WO',
        barClass: 'from-warning to-owner-urgent',
        sla: '91.4%',
        slaClass: 'text-warning',
        healthy: false,
    },
    {
        name: 'Housekeeping',
        scope: 'Area publik & sanitasi',
        icon: Sparkles,
        iconWrap: 'bg-linear-to-tr from-success to-brand-strong',
        hod: 'Siti N.',
        hodRole: 'Supervisor',
        activeWo: 1,
        urgent: false,
        doneRatio: 95,
        ratioNote: '19 dari 20 WO',
        barClass: 'from-success to-brand-strong',
        sla: '99.2%',
        slaClass: 'text-ink',
        healthy: true,
    },
];

type PriorityTicket = {
    id: string;
    priority: 'P1' | 'P2' | 'P3';
    title: string;
    location: string;
    requester: string;
    technician: string;
    slaLeft: string;
    slaClass: string;
    cardClass: string;
    accentClass: string;
};

const priorityTickets: PriorityTicket[] = [
    {
        id: 'WO-2026-00411',
        priority: 'P1',
        title: 'Kebocoran pipa header chiller unit 02, tekanan turun drastis',
        location: 'MEP · Ruang Chiller B2',
        requester: 'Tenant Management',
        technician: 'Hadi P. & tim MEP',
        slaLeft: '01:24:10',
        slaClass: 'bg-danger-subtle text-danger ring-danger/30',
        cardClass:
            'bg-danger-subtle/40 border-danger/30 hover:bg-danger-subtle/60',
        accentClass: 'border-l-danger',
    },
    {
        id: 'WO-2026-00408',
        priority: 'P2',
        title: 'Keretakan plafon gypsum dekat pintu lift passenger 03',
        location: 'Sipil · Koridor Lift Barat Lt. 14',
        requester: 'Security Patrol',
        technician: 'Joko Anwar',
        slaLeft: '04:12:45',
        slaClass: 'bg-warning-subtle text-warning ring-warning/30',
        cardClass:
            'bg-warning-subtle/40 border-warning/30 hover:bg-warning-subtle/60',
        accentClass: 'border-l-warning',
    },
    {
        id: 'WO-2026-00405',
        priority: 'P3',
        title: 'Magnetic lock pintu data center tidak responsif ke kartu RFID',
        location: 'IT Security · Data Center Lt. 3',
        requester: 'NOC Officer',
        technician: 'Doni Kurniawan',
        slaLeft: '05:40:00',
        slaClass: 'bg-surface-raised text-ink-muted ring-border',
        cardClass: 'bg-surface border-border hover:bg-surface-raised',
        accentClass: 'border-l-info',
    },
];

const priorityStyles: Record<
    PriorityTicket['priority'],
    { label: string; badge: string; icon: LucideIcon }
> = {
    P1: {
        label: 'P1 · KRITIS',
        badge: 'bg-danger text-on-brand',
        icon: Zap,
    },
    P2: {
        label: 'P2 · TINGGI',
        badge: 'bg-warning text-on-brand',
        icon: TriangleAlert,
    },
    P3: { label: 'P3 · NORMAL', badge: 'bg-info text-on-brand', icon: Clock },
};

type AuditEntry = {
    time: string;
    tone: string;
    wrapClass: string;
    tag: string;
    tagClass: string;
    Icon: LucideIcon;
    body: ReactNode;
};

const auditEntries: AuditEntry[] = [
    {
        time: '10:32',
        tone: 'bg-success',
        wrapClass: 'bg-success-subtle/50 border-success/25',
        tag: 'WO ditutup',
        tagClass: 'bg-success text-on-brand',
        Icon: CheckCircle,
        body: (
            <>
                <span className="font-medium text-ink">
                    WO-2026-00398 · Ballast lampu koridor Lt. 8
                </span>
                <span className="block text-ink-subtle">
                    HOD MEP memvalidasi 2 fotoevidence.
                </span>
            </>
        ),
    },
    {
        time: '09:50',
        tone: 'bg-warning',
        wrapClass: 'bg-warning-subtle/50 border-warning/30',
        tag: 'Ajukan perpanjangan',
        tagClass: 'bg-warning text-on-brand',
        Icon: Clock,
        body: (
            <>
                <span className="font-medium text-ink">
                    WO-2026-00402 · Overhaul motor pompa transfer B3
                </span>
                <span className="block text-ink-subtle">
                    Menunggu approval DGM/GM · +2 jam (sparepart vendor).
                </span>
                <span className="mt-1.5 flex gap-1.5">
                    <button
                        type="button"
                        className="h-9 cursor-pointer rounded-md bg-brand px-2.5 text-xs font-semibold text-on-brand hover:bg-brand-hover"
                    >
                        Setujui +2 jam
                    </button>
                    <button
                        type="button"
                        className="h-9 cursor-pointer rounded-md border border-border bg-surface px-2.5 text-xs font-semibold text-ink hover:bg-surface-raised"
                    >
                        Tolak
                    </button>
                </span>
            </>
        ),
    },
    {
        time: '08:00',
        tone: 'bg-info',
        wrapClass: 'bg-surface-raised border-border',
        tag: 'Otomatis',
        tagClass: 'bg-info text-on-brand',
        Icon: CalendarClock,
        body: (
            <span className="text-ink-muted">
                12 preventive maintenance terjadwal untuk shift pagi.
            </span>
        ),
    },
];

const rangeOptions = ['Hari ini', '7 hari', '30 hari'] as const;

export default function Dashboard() {
    const tenant = useRequiredTenant('dashboard');
    const [range, setRange] = useState<(typeof rangeOptions)[number]>('7 hari');
    const [showNotice, setShowNotice] = useState(true);

    return (
        <>
            <Head title="Beranda" />

            {showNotice && (
                <InfoBanner
                    iconClassName="text-danger"
                    message="6 WO telat dan 2 eskalasi aktif — tinjau sekarang agar SLA tidak memburuk."
                    href={workOrdersIndex({ tenant })}
                    onDismiss={() => setShowNotice(false)}
                />
            )}

            <div className="flex flex-col gap-6">
                {/* Dashboard Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border pb-4">
                    <div>
                        <h1 className="text-xl sm:text-2xl font-bold tracking-normal text-ink leading-tight">
                            Ruang Kerja Operasional
                        </h1>
                        <p className="text-sm text-ink-muted font-medium leading-relaxed">
                            Pantau kinerja tim, kelola antrian, dan pastikan SLA terpenuhi
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <div
                            role="group"
                            aria-label="Rentang laporan"
                            className="flex items-center gap-0.5 rounded-lg bg-surface-sunken p-0.5"
                        >
                            {rangeOptions.map((option) => (
                                <button
                                    key={option}
                                    type="button"
                                    aria-pressed={range === option}
                                    onClick={() => setRange(option)}
                                    className={cn(
                                        'h-9 cursor-pointer rounded-md px-3 text-xs font-medium transition-colors duration-200',
                                        range === option
                                            ? 'bg-surface text-ink shadow-card'
                                            : 'text-ink-muted hover:bg-surface hover:text-ink',
                                    )}
                                >
                                    {option}
                                </button>
                            ))}
                        </div>
                        <Button variant="outline" size="sm" className="h-9 gap-1 px-4 font-medium border-1" aria-label="Ekspor data dashboard">
                            <Upload aria-hidden="true" className="size-3.5" />
                            Ekspor
                        </Button>
                    </div>
                </div>

                {/* KPI Row */}
                <section aria-label="Ringkasan eksekutif" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {kpiCards.map((card) => (
                        <article
                            key={card.id}
                            className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-5 shadow-card transition-[box-shadow,border-color] duration-200 ease-standard hover:border-border-strong hover:shadow-raised motion-reduce:transition-none"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="text-xs font-semibold tracking-wider text-ink-subtle uppercase">
                                        {card.label}
                                    </p>
                                    <p className="mt-0.5 truncate text-xs text-ink-subtle">
                                        {card.hint}
                                    </p>
                                </div>
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'flex size-9 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset',
                                        card.iconWrap,
                                    )}
                                >
                                    <card.icon className="size-4" />
                                </span>
                            </div>

                            <div className="flex items-baseline gap-1.5">
                                <span className="text-2xl font-semibold tracking-tight text-ink tabular-nums">
                                    {card.value}
                                </span>
                                {card.unit && (
                                    <span className="text-sm text-ink-muted">
                                        {card.unit}
                                    </span>
                                )}
                            </div>

                            <span
                                className={cn(
                                    'inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset',
                                    card.chip.className,
                                )}
                            >
                                {card.chip.icon && (
                                    <card.chip.icon
                                        aria-hidden="true"
                                        className="size-3"
                                    />
                                )}
                                {card.chip.text}
                            </span>

                            {card.note}

                            {card.progress && (
                                <div className="space-y-1.5">
                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-surface-sunken">
                                        <div
                                            className="h-full rounded-full bg-brand"
                                            style={{
                                                width: `${card.progress.pct}%`,
                                            }}
                                        />
                                    </div>
                                    <p className="text-xs text-ink-subtle">
                                        {card.progress.caption}
                                    </p>
                                </div>
                            )}

                            <div className="mt-auto border-t border-border pt-3 text-xs">
                                {card.footer}
                            </div>
                        </article>
                    ))}
                </section>

                {/* Department Health */}
                <section
                    aria-labelledby="department-heading"
                    className="rounded-2xl border border-border bg-card shadow-card"
                >
                    <div className="p-5 border-b border-border/30">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="text-lg sm:text-xl font-bold tracking-normal text-ink">
                                    Kesehatan Departemen
                                </h2>
                                <p className="text-sm text-ink-muted mt-1 font-medium">
                                    Status SLA dan kinerja per departemen.
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-9 gap-1 px-4 font-medium border-1"
                                aria-label="Filter data kesehatan departemen"
                            >
                                <ListFilter aria-hidden="true" className="size-3.5" />
                                Filter
                            </Button>
                        </div>
                    </div>

                    <div className="p-5 space-y-4">
                        {departmentRows.map((row) => (
                            <div
                                key={row.name}
                                className="flex items-center justify-between p-4 rounded-xl border border-border/30 bg-card shadow-sm transition-colors duration-200 hover:border-border/50 motion-reduce:transition-none focus:outline-none focus:ring-2 focus:ring-brand/30"
                                tabIndex={0}
                            >
                                <div className="flex items-center gap-3">
                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm border border-border/50">
                                        <row.icon className="size-4 text-brand" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-ink tracking-normal">
                                            {row.name}
                                        </h3>
                                        <p className="text-xs text-ink-muted font-medium">
                                            {row.hod}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-6">
                                    <div className="text-right">
                                        <p className="font-semibold text-ink tabular-nums">
                                            {row.activeWo}
                                        </p>
                                        <p className="text-xs text-ink-muted uppercase tracking-wide font-medium">WO Aktif</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-semibold text-ink tabular-nums">
                                            {row.doneRatio}%
                                        </p>
                                        <p className="text-xs text-ink-muted uppercase tracking-wide font-medium">Selesai</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-semibold tabular-nums {row.slaClass}">
                                            {row.sla}
                                        </p>
                                        <p className="text-xs text-ink-muted uppercase tracking-wide font-medium">SLA</p>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <span
                                            className={cn(
                                                'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset transition-colors duration-200',
                                                row.healthy
                                                    ? 'bg-success-subtle text-success border-success/50 hover:bg-success hover:text-on-brand hover:ring-success/40'
                                                    : 'bg-warning-subtle text-warning border-warning/50 hover:bg-warning hover:text-on-brand hover:ring-warning/40',
                                            )}
                                        >
                                            {row.healthy ? (
                                                <CheckCircle aria-hidden="true" className="size-4" />
                                            ) : (
                                                <TriangleAlert aria-hidden="true" className="size-4" />
                                            )}
                                            {row.healthy ? 'Sehat' : 'Perlu perhatian'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                    <div className="mt-6 p-5 pt-4 border-t border-border/30">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-ink-muted uppercase tracking-wide">Rata-rata SLA</p>
                                <p className="text-xl sm:text-2xl font-bold text-success mt-1">92%</p>
                            </div>
                            <div className="w-32 h-1.5 bg-surface-sunken rounded-full overflow-hidden">
                                <div className="h-full bg-brand rounded-full transition-all duration-500" style={{ width: '92%' }} />
                            </div>
                        </div>
                    </div>
                </section>

                {/* Priority Queue & Audit Trail */}
                <div className="grid gap-6 lg:grid-cols-3">
                    <section
                        aria-labelledby="priority-heading"
                        className="rounded-2xl border border-border bg-card p-5 lg:col-span-2 shadow-card"
                    >
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h2 className="text-lg sm:text-xl font-bold tracking-normal text-ink">
                                    Antrian Prioritas
                                </h2>
                                <p className="text-sm text-ink-muted mt-1 font-medium">
                                    Work Order yang memerlukan tindakan segera atau berisiko melanggar SLA.
                                </p>
                            </div>
                            <Link
                                href={workOrdersIndex({ tenant })}
                                className="inline-flex items-center gap-1 text-sm font-medium text-brand hover:text-brand-hover"
                                aria-label="Lihat semua work order prioritas"
                            >
                                Lihat semua
                                <ArrowUpRight aria-hidden="true" className="size-3.5" />
                            </Link>
                        </div>

                        <div className="space-y-3">
                            {priorityTickets.map((ticket) => {
                                const style = priorityStyles[ticket.priority];

                                return (
                                    <article
                                        key={ticket.id}
                                        className={cn(
                                            'rounded-lg border border-l-4 p-3.5 shadow-card transition-colors duration-150 motion-reduce:transition-none',
                                            ticket.cardClass,
                                            ticket.accentClass,
                                        )}
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-mono text-xs font-medium text-ink-muted tabular-nums">{ticket.id}</span>
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                                            style.badge,
                                                        )}
                                                    >
                                                        <style.icon
                                                            aria-hidden="true"
                                                            className="size-3"
                                                        />
                                                        {style.label}
                                                    </span>
                                                </div>
                                                <h3 className="mt-1.5 text-sm font-semibold text-balance text-ink">
                                                    {ticket.title}
                                                </h3>
                                                <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-muted">
                                                    <span className="inline-flex items-center gap-1">
                                                        <MapPin aria-hidden="true" className="size-3" />
                                                        {ticket.location}
                                                    </span>
                                                    <span className="inline-flex items-center gap-1">
                                                        Pelapor:{' '}
                                                        <span className="font-medium text-ink">
                                                            {ticket.requester}
                                                        </span>
                                                    </span>
                                                    <span className="inline-flex items-center gap-1">
                                                        <Wrench aria-hidden="true" className="size-3" />
                                                        <span className="font-medium text-ink">
                                                            {ticket.technician}
                                                        </span>
                                                    </span>
                                                </p>
                                            </div>

                                            <div className="flex shrink-0 flex-col items-end gap-2">
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center gap-1 rounded-md px-2 py-1 font-mono text-xs font-semibold tabular-nums ring-1 ring-inset',
                                                        ticket.slaClass,
                                                    )}
                                                >
                                                    <Clock aria-hidden="true" className="size-3" />
                                                    {ticket.slaLeft}
                                                </span>
                                                <div className="flex gap-1.5">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-9 gap-1 text-xs"
                                                        aria-label={`Audit ${ticket.title}`}
                                                    >
                                                        <Eye aria-hidden="true" className="size-3" />
                                                        Audit
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        className="h-9 text-xs"
                                                        aria-label={`Tangani ${ticket.title}`}
                                                    >
                                                        Tangani
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    </section>

                    <section
                        aria-labelledby="audit-heading"
                        className="flex flex-col rounded-2xl border border-border bg-card p-5 shadow-card"
                    >
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h2 className="text-lg sm:text-xl font-bold tracking-normal text-ink">
                                    Aktivitas Terbaru
                                </h2>
                                <p className="text-sm text-ink-muted mt-1 font-medium">
                                    Perubahan penting dalam 24 jam terakhir.
                                </p>
                            </div>
                        </div>

                        <ol className="mt-4 space-y-3">
                            {auditEntries.map((entry) => (
                                <li
                                    key={entry.time}
                                    className={cn(
                                        'rounded-lg border p-3',
                                        entry.wrapClass,
                                    )}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span
                                            className={cn(
                                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                                entry.tagClass,
                                            )}
                                        >
                                            <entry.Icon
                                                aria-hidden="true"
                                                className="size-3"
                                            />
                                            {entry.tag}
                                        </span>
                                        <span className="font-mono text-xs text-ink-subtle tabular-nums">
                                            {entry.time}
                                        </span>
                                    </div>
                                    <p className="mt-1.5 text-xs">
                                        {entry.body}
                                    </p>
                                </li>
                            ))}
                        </ol>

                        <div className="mt-auto pt-4 text-xs text-ink-subtle">
                            <Link
                                href={workOrdersIndex({ tenant })}
                                className="inline-flex items-center gap-1 font-medium text-brand hover:text-brand-hover"
                            >
                                Buka audit log lengkap
                                <ArrowUpRight aria-hidden="true" className="size-3.5" />
                            </Link>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Beranda', href: home() }],
    greeting: true,
    description: 'Kelola antrian dan pantau SLA hari ini.',
    actions: undefined,
};