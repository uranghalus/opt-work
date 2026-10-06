import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Bolt,
    CalendarClock,
    CheckCircle,
    ChevronDown,
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
    MonitorCog,
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
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create, index as workOrdersIndex } from '@/routes/work-orders';
import type { InertiaConfig } from '@inertiajs/core';

/*
 * Executive overview home (Stitch reference, DESIGN.md §13 executive
 * scorecard). Authored fixture data: the Work Order module has no dashboard
 * controller yet, so the KPI scorecard, weekly throughput, departmental
 * health, priority queue and audit trail render sample values at production
 * fidelity. Swap for Inertia props when DashboardController ships.
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

function FootCount({
    tone,
    count,
    label,
}: {
    tone: string;
    count: number;
    label: string;
}) {
    return (
        <span className="flex items-center gap-1.5">
            <span
                aria-hidden="true"
                className={cn('size-2 shrink-0 rounded-full', tone)}
            >
            </span>
            <span className="font-semibold text-ink tabular-nums">{count}</span>
            <span className="text-ink-subtle">{label}</span>
        </span>
    );
}

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
                <FootCount tone="bg-success" count={118} label="Selesai" />
                <FootCount tone="bg-warning" count={18} label="Berjalan" />
                <FootCount tone="bg-info" count={6} label="Approval" />
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
                <span className="shrink-0 rounded-full bg-brand-soft px-2 py-0.5 text-[0.6875rem] font-semibold text-brand">
                    6 standby
                </span>
            </div>
        ),
    },
];

const weeklyThroughput = [
    { day: 'Sen', date: '17 Feb', masuk: 18, selesai: 22, sla: 97 },
    { day: 'Sel', date: '18 Feb', masuk: 20, selesai: 24, sla: 98 },
    { day: 'Rab', date: '19 Feb', masuk: 25, selesai: 23, sla: 95 },
    { day: 'Kam', date: '20 Feb', masuk: 16, selesai: 20, sla: 96 },
    { day: 'Jum', date: '21 Feb', masuk: 28, selesai: 30, sla: 99 },
    { day: 'Sab', date: '22 Feb', masuk: 10, selesai: 12, sla: 93 },
    { day: 'Min', date: '23 Feb', masuk: 8, selesai: 11, sla: 96 },
];

const throughputScale = Math.max(
    ...weeklyThroughput.flatMap((d) => [d.masuk, d.selesai]),
);

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
                        className="h-7 cursor-pointer rounded-md bg-brand px-2.5 text-xs font-semibold text-on-brand hover:bg-brand-hover"
                    >
                        Setujui +2 jam
                    </button>
                    <button
                        type="button"
                        className="h-7 cursor-pointer rounded-md border border-border bg-surface px-2.5 text-xs font-semibold text-ink hover:bg-surface-raised"
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

function SectionHeading({
    icon: Icon,
    iconWrap,
    title,
    description,
    trailing,
}: {
    icon: LucideIcon;
    iconWrap: string;
    title: string;
    description: string;
    trailing?: ReactNode;
}) {
    return (
        <header className="flex flex-wrap items-start justify-between gap-3 border-b border-border pb-4">
            <div className="flex min-w-0 items-start gap-2.5">
                <span
                    aria-hidden="true"
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-lg',
                        iconWrap,
                    )}
                >
                    <Icon className="size-4" />
                </span>
                <div className="min-w-0">
                    <h2 className="truncate text-sm font-semibold text-ink">
                        {title}
                    </h2>
                    <p className="mt-0.5 text-xs text-ink-muted">
                        {description}
                    </p>
                </div>
            </div>
            {trailing}
        </header>
    );
}

function Th({
    children,
    align = 'left',
}: {
    children: ReactNode;
    align?: 'left' | 'center' | 'right';
}) {
    return (
        <th
            scope="col"
            className={cn(
                'px-3 py-2.5 text-[0.6875rem] font-semibold tracking-wider whitespace-nowrap text-ink-subtle uppercase',
                align === 'center' && 'text-center',
                align === 'right' && 'text-right',
            )}
        >
            {children}
        </th>
    );
}

function AvatarStack({ people }: { people: string }) {
    const getInitials = useInitials();

    return (
        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-soft text-[0.625rem] font-semibold text-brand ring-1 ring-border">
            {getInitials(people)}
        </span>
    );
}

function DashboardActions() {
    const { permissions, activeTenant } =
        usePage<InertiaConfig['sharedPageProps']>().props;

    if (!permissions['work-order.create'] || !activeTenant) {
        return null;
    }

    return (
        <Button asChild size="lg">
            <Link href={create({ tenant: activeTenant })}>
                <Plus aria-hidden="true" className="size-4" />
                Buat Work Order
            </Link>
        </Button>
    );
}

export default function Dashboard() {
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;
    const tenant = (activeTenant as string | null) ?? '';
    const [range, setRange] = useState<(typeof rangeOptions)[number]>('7 hari');
    const [showNotice, setShowNotice] = useState(true);

    return (
        <>
            <Head title="Beranda" />

            {showNotice && (
                <InfoBanner
                    iconClassName="text-danger"
                    message="6 WO telat dan 2 eskalasi aktif — tinjau sekarang agar SLA tidak memburuk."
                    href={tenant ? workOrdersIndex({ tenant }) : undefined}
                    onDismiss={() => setShowNotice(false)}
                />
            )}

            <div className="flex flex-col gap-6">
                <div className="-mt-2 flex flex-wrap items-center gap-2 self-end">
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
                                    'h-7 cursor-pointer rounded-md px-2.5 text-xs font-medium transition-colors duration-150 motion-reduce:transition-none',
                                    range === option
                                        ? 'bg-surface text-ink shadow-card'
                                        : 'text-ink-muted hover:text-ink',
                                )}
                            >
                                {option}
                            </button>
                        ))}
                    </div>
                    <Button variant="outline" size="sm" className="h-8 gap-1.5">
                        <Upload aria-hidden="true" className="size-3.5" />
                        Ekspor
                    </Button>
                </div>

                <section
                    aria-label="Ringkasan eksekutif"
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                >
                    {kpiCards.map((card) => (
                        <article
                            key={card.id}
                            className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-5 shadow-card transition-[box-shadow,border-color] duration-200 ease-standard hover:border-border-strong hover:shadow-raised motion-reduce:transition-none"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="text-[0.6875rem] font-semibold tracking-wider text-ink-subtle uppercase">
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
                                    'inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-[0.6875rem] font-semibold ring-1 ring-inset',
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
                                    <p className="text-[0.6875rem] text-ink-subtle">
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

                <section
                    aria-labelledby="throughput-heading"
                    className="rounded-2xl border border-border bg-card p-5 shadow-card"
                >
                    <SectionHeading
                        icon={MonitorCog}
                        iconWrap="bg-info-subtle text-info"
                        title="Throughput mingguan & tren SLA"
                        description="Tiket WO masuk dibanding WO selesai, 7 hari terakhir."
                        trailing={
                            <div className="flex items-center gap-3 text-xs text-ink-muted">
                                <span className="flex items-center gap-1.5">
                                    <span
                                        aria-hidden="true"
                                        className="size-2.5 rounded-sm bg-brand"
                                    />
                                    WO selesai
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span
                                        aria-hidden="true"
                                        className="size-2.5 rounded-sm bg-info"
                                    />
                                    WO masuk
                                </span>
                            </div>
                        }
                    />

                    <div className="grid grid-cols-7 gap-3 pt-5 pb-2">
                        {weeklyThroughput.map((day) => (
                            <div
                                key={day.day}
                                className="flex flex-col items-center gap-2"
                            >
                                <span
                                    className={cn(
                                        'text-[0.6875rem] font-semibold tabular-nums',
                                        day.sla >= 95
                                            ? 'text-success'
                                            : 'text-warning',
                                    )}
                                >
                                    {day.sla}%
                                </span>
                                <div className="flex h-24 w-full items-end justify-center gap-1.5 rounded-xl bg-surface-sunken p-1.5 ring-1 ring-border">
                                    <div
                                        className="w-2.5 rounded-t-sm bg-info"
                                        style={{
                                            height: `${(day.masuk / throughputScale) * 100}%`,
                                        }}
                                        title={`WO masuk: ${day.masuk}`}
                                    />
                                    <div
                                        className="w-2.5 rounded-t-sm bg-brand"
                                        style={{
                                            height: `${(day.selesai / throughputScale) * 100}%`,
                                        }}
                                        title={`WO selesai: ${day.selesai}`}
                                    />
                                </div>
                                <div className="text-center">
                                    <p className="text-xs font-medium text-ink">
                                        {day.day}
                                    </p>
                                    <p className="text-[0.625rem] text-ink-subtle">
                                        {day.date}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <section
                    aria-labelledby="department-heading"
                    className="rounded-2xl border border-border bg-card shadow-card"
                >
                    <div className="p-5">
                        <SectionHeading
                            icon={HeartPulse}
                            iconWrap="bg-info-subtle text-info"
                            title="Kesehatan operasional lintas departemen"
                            description="Throughput, rasio penyelesaian, dan kepatuhan SLA per departemen."
                            trailing={
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1.5"
                                >
                                    <ListFilter
                                        aria-hidden="true"
                                        className="size-3.5"
                                    />
                                    Filter
                                </Button>
                            }
                        />
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-y border-border bg-surface-raised">
                                <tr>
                                    <Th>Departemen</Th>
                                    <Th>HOD</Th>
                                    <Th align="center">WO aktif</Th>
                                    <Th>Rasio selesai</Th>
                                    <Th align="center">SLA</Th>
                                    <Th align="right">Status</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {departmentRows.map((row) => (
                                    <tr
                                        key={row.name}
                                        className="transition-colors duration-150 hover:bg-surface-raised motion-reduce:transition-none"
                                    >
                                        <td className="px-3 py-3">
                                            <div className="flex items-center gap-2.5">
                                                <span
                                                    aria-hidden="true"
                                                    className={cn(
                                                        'flex size-8 shrink-0 items-center justify-center rounded-lg text-on-brand',
                                                        row.iconWrap,
                                                    )}
                                                >
                                                    <row.icon className="size-4" />
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium text-ink">
                                                        {row.name}
                                                    </p>
                                                    <p className="truncate text-xs text-ink-subtle">
                                                        {row.scope}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-3 py-3">
                                            <div className="flex items-center gap-2">
                                                <AvatarStack people={row.hod} />
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium text-ink">
                                                        {row.hod}
                                                    </p>
                                                    <p className="truncate text-xs text-ink-subtle">
                                                        {row.hodRole}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-3 py-3 text-center">
                                            <span
                                                className={cn(
                                                    'inline-flex items-center rounded-md px-2 py-1 font-mono text-xs font-semibold',
                                                    row.urgent
                                                        ? 'bg-danger-subtle text-danger ring-danger/25'
                                                        : 'bg-warning-subtle text-warning ring-warning/25',
                                                )}
                                            >
                                                {row.activeWo} WO
                                                {row.urgent && (
                                                    <span className="sr-only">
                                                        , termasuk 1 prioritas
                                                        P1
                                                    </span>
                                                )}
                                            </span>
                                        </td>
                                        <td className="px-3 py-3">
                                            <div className="flex items-baseline justify-between gap-2 text-xs">
                                                <span className="text-ink-muted">
                                                    {row.ratioNote}
                                                </span>
                                                <span className="font-medium text-ink tabular-nums">
                                                    {row.doneRatio}%
                                                </span>
                                            </div>
                                            <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-surface-sunken">
                                                <div
                                                    className={cn(
                                                        'h-full rounded-full bg-linear-to-r',
                                                        row.barClass,
                                                    )}
                                                    style={{
                                                        width: `${row.doneRatio}%`,
                                                    }}
                                                />
                                            </div>
                                        </td>
                                        <td
                                            className={cn(
                                                'px-3 py-3 text-center font-mono text-xs font-semibold tabular-nums',
                                                row.slaClass,
                                            )}
                                        >
                                            {row.sla}
                                        </td>
                                        <td className="px-3 py-3 text-right">
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                                    row.healthy
                                                        ? 'bg-success-subtle text-success ring-success/25'
                                                        : 'bg-warning-subtle text-warning ring-warning/30',
                                                )}
                                            >
                                                {row.healthy ? (
                                                    <CheckCircle
                                                        aria-hidden="true"
                                                        className="size-3"
                                                    />
                                                ) : (
                                                    <TriangleAlert
                                                        aria-hidden="true"
                                                        className="size-3"
                                                    />
                                                )}
                                                {row.healthy
                                                    ? 'Sehat'
                                                    : 'Perlu perhatian'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="grid gap-4 lg:grid-cols-3">
                    <section
                        aria-labelledby="priority-heading"
                        className="rounded-2xl border border-border bg-card p-5 lg:col-span-2"
                    >
                        <SectionHeading
                            icon={Zap}
                            iconWrap="bg-danger-subtle text-danger"
                            title="Pekerjaan prioritas & insiden aktif"
                            description="Tiket P1–P3 yang butuh tindakan sebelum SLA terlampaui."
                            trailing={
                                <Link
                                    href={
                                        tenant
                                            ? workOrdersIndex({ tenant })
                                            : dashboard()
                                    }
                                    className="inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-hover"
                                >
                                    Lihat semua
                                    <ArrowUpRight
                                        aria-hidden="true"
                                        className="size-3.5"
                                    />
                                </Link>
                            }
                        />

                        <div className="mt-4 space-y-3">
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
                                                    <span className="font-mono text-xs font-medium text-ink-muted">
                                                        {ticket.id}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.6875rem] font-semibold',
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
                                                        <MapPin
                                                            aria-hidden="true"
                                                            className="size-3"
                                                        />
                                                        {ticket.location}
                                                    </span>
                                                    <span className="inline-flex items-center gap-1">
                                                        Pelapor:{' '}
                                                        <span className="font-medium text-ink">
                                                            {ticket.requester}
                                                        </span>
                                                    </span>
                                                    <span className="inline-flex items-center gap-1">
                                                        <Wrench
                                                            aria-hidden="true"
                                                            className="size-3"
                                                        />
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
                                                    <Clock
                                                        aria-hidden="true"
                                                        className="size-3"
                                                    />
                                                    {ticket.slaLeft}
                                                </span>
                                                <div className="flex gap-1.5">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-7 gap-1 text-xs"
                                                    >
                                                        <Eye
                                                            aria-hidden="true"
                                                            className="size-3"
                                                        />
                                                        Audit
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        className="h-7 text-xs"
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
                        <SectionHeading
                            icon={History}
                            iconWrap="bg-surface-sunken text-ink-muted"
                            title="Ringkasan eksekusi"
                            description="Jejak audit terbaru hari ini."
                        />

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
                                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.6875rem] font-semibold',
                                                entry.tagClass,
                                            )}
                                        >
                                            <entry.Icon
                                                aria-hidden="true"
                                                className="size-3"
                                            />
                                            {entry.tag}
                                        </span>
                                        <span className="font-mono text-[0.6875rem] text-ink-subtle tabular-nums">
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
                                href={
                                    tenant
                                        ? workOrdersIndex({ tenant })
                                        : dashboard()
                                }
                                className="inline-flex items-center gap-1 font-medium text-brand hover:text-brand-hover"
                            >
                                Buka audit log lengkap
                                <ArrowUpRight
                                    aria-hidden="true"
                                    className="size-3.5"
                                />
                            </Link>
                        </div>
                    </section>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" className="h-8 gap-1.5">
                        <Upload aria-hidden="true" className="size-3.5" />
                        Impor
                    </Button>
                    <label className="relative">
                        <span className="sr-only">Cari Work Order</span>
                        <Search
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-ink-subtle"
                        />
                        <input
                            type="search"
                            placeholder="Cari…"
                            className="h-8 w-40 rounded-md border border-border bg-card pl-8 text-sm text-ink placeholder:text-ink-subtle focus:ring-2 focus:ring-brand/40 focus:outline-none"
                        />
                    </label>
                    <Button variant="outline" size="sm" className="h-8 gap-1.5">
                        Urutkan
                        <ChevronDown
                            aria-hidden="true"
                            className="size-3 text-ink-subtle"
                        />
                    </Button>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
    greeting: true,
    description: 'Kelola antrian dan pantau SLA hari ini.',
    actions: DashboardActions,
};