import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronDown,
    Ellipsis,
    Folder,
    ListFilter,
    Plus,
    RotateCcw,
    Search,
    ShieldAlert,
    TriangleAlert,
    Upload,
    X,
    Zap,
    type LucideIcon,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { CategoryBadge, type WoCategory } from '@/components/category-badge';
import { DeadlineRail, type DeadlineState } from '@/components/deadline-rail';
import { StatusBadge, type WoStatus } from '@/components/status-badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

/*
 * Demo fixture data. The Work Order module (routes + controller) does not
 * exist yet, so this page renders the PRD's status pipeline with authored
 * sample data at production fidelity. Swap for Inertia props when the
 * DashboardController ships.
 */

/* Queue cards, styled as the mockup's folder cards */
type QueueCard = {
    status: WoStatus;
    title: string;
    hint: string;
    count: number;
    emphasis?: boolean;
};

const queueCards: QueueCard[] = [
    {
        status: 'waiting_hod',
        title: 'Menunggu Keputusan HOD',
        hint: 'perlu eksekusi atau jadwal',
        count: 7,
        emphasis: true,
    },
    {
        status: 'assigned',
        title: 'Ditugaskan',
        hint: 'sudah punya penanggung jawab',
        count: 12,
    },
    {
        status: 'scheduled',
        title: 'Terjadwal',
        hint: 'masuk rencana kerja',
        count: 5,
    },
    {
        status: 'pending_verify',
        title: 'Menunggu Verifikasi',
        hint: 'hasil menunggu persetujuan',
        count: 3,
    },
];

type DemoWo = {
    id: string;
    jenisShort: string;
    jenis: string;
    category: WoCategory;
    status: WoStatus;
    requester: string;
    dept: string;
    created: string;
    deadline: string;
    deadlineState: DeadlineState;
    deadlineLabel: string;
    assignee: string;
};

const demoWorkOrders: DemoWo[] = [
    {
        id: 'WO-2026-00418',
        jenisShort: 'Perbaikan',
        jenis: 'Perbaikan panel listrik lantai 2',
        category: 'accident',
        status: 'in_progress',
        requester: 'Gunawan W.',
        dept: 'GA',
        created: '20-09-26',
        deadline: '23-09-26',
        deadlineState: 'on_track',
        deadlineLabel: 'On track',
        assignee: 'Sari R.',
    },
    {
        id: 'WO-2026-00417',
        jenisShort: 'Pemeliharaan',
        jenis: 'Ganti pompa air chiller',
        category: 'normal',
        status: 'overdue',
        requester: 'Maya S.',
        dept: 'Produksi',
        created: '12-09-26',
        deadline: '19-09-26',
        deadlineState: 'escalated',
        deadlineLabel: 'Telat H+3 · Team Leader',
        assignee: 'Budi S.',
    },
    {
        id: 'WO-2026-00416',
        jenisShort: 'Pemeliharaan',
        jenis: 'Servis AC ruang server',
        category: 'normal',
        status: 'scheduled',
        requester: 'Andi P.',
        dept: 'IT',
        created: '22-09-26',
        deadline: '26-09-26',
        deadlineState: 'due_soon',
        deadlineLabel: 'Deadline besok',
        assignee: 'Tim Teknik',
    },
    {
        id: 'WO-2026-00415',
        jenisShort: 'Instalasi',
        jenis: 'Perbaikan lift gedung B',
        category: 'owner',
        status: 'waiting_hod',
        requester: 'Ibu Direksi',
        dept: 'GA',
        created: '23-09-26',
        deadline: '—',
        deadlineState: 'on_track',
        deadlineLabel: 'Menunggu keputusan',
        assignee: '—',
    },
    {
        id: 'WO-2026-00414',
        jenisShort: 'Perawatan',
        jenis: 'Cat ulang garasi parkir',
        category: 'normal',
        status: 'pending_verify',
        requester: 'Rina T.',
        dept: 'GA',
        created: '15-09-26',
        deadline: '22-09-26',
        deadlineState: 'on_track',
        deadlineLabel: 'On track',
        assignee: 'Sari R.',
    },
    {
        id: 'WO-2026-00413',
        jenisShort: 'Perbaikan',
        jenis: 'Perbaikan mesin injeksi #4',
        category: 'normal',
        status: 'revision',
        requester: 'Produksi',
        dept: 'Produksi',
        created: '10-09-26',
        deadline: '25-09-26',
        deadlineState: 'overdue',
        deadlineLabel: 'Telat',
        assignee: 'Joko W.',
    },
];

/* Compliance proportions: on track / telat / eskalasi (DESIGN.md signals) */
const slaSegments = [
    { label: 'On track', pct: 80, align: 'text-left' },
    { label: 'Telat', pct: 15, align: 'text-center' },
    { label: 'Eskalasi', pct: 5, align: 'text-right' },
];

/* Attention tiles with signal-colored icons (mockup: Expired/Incomplete/Invalid) */
const attentionTiles: {
    count: number;
    label: string;
    icon: LucideIcon;
    tone: string;
}[] = [
    { count: 6, label: 'Telat', icon: TriangleAlert, tone: 'text-danger' },
    { count: 2, label: 'Eskalasi', icon: ShieldAlert, tone: 'text-escalation' },
    { count: 1, label: 'Revisi', icon: RotateCcw, tone: 'text-warning' },
];

const pulseTiles = [
    { trend: '↑ 15%', value: '12 WO', label: 'Deadline ≤ 1 hari' },
    { trend: '↑ 8%', value: '6 WO', label: 'Telat, perlu perpanjangan' },
    { trend: '↑ 3%', value: '5 WO', label: 'Masuk jadwal minggu ini' },
];

const deadlineTone: Record<DeadlineState, string> = {
    on_track: 'text-ink-muted',
    due_soon: 'text-warning',
    overdue: 'text-danger',
    escalated: 'text-escalation',
};

function PersonCell({ name }: { name: string }) {
    const getInitials = useInitials();

    if (name === '—') {
        return <span className="text-ink-subtle">—</span>;
    }

    return (
        <span className="flex items-center gap-2">
            <Avatar className="size-6 rounded-full">
                <AvatarFallback className="rounded-full bg-secondary text-[0.625rem] font-semibold text-foreground">
                    {getInitials(name)}
                </AvatarFallback>
            </Avatar>
            <span className="whitespace-nowrap">{name}</span>
        </span>
    );
}

function SortableTh({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <th
            scope="col"
            className={cn(
                'px-2.5 py-2.5 font-semibold whitespace-nowrap',
                className,
            )}
        >
            <span className="inline-flex items-center gap-1">
                {children}
                <ChevronDown
                    aria-hidden="true"
                    className="size-3 text-ink-subtle"
                />
            </span>
        </th>
    );
}

export default function Dashboard() {
    const [statusFilter, setStatusFilter] = useState<'semua' | WoStatus>(
        'semua',
    );
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [showSlaWarning, setShowSlaWarning] = useState(true);

    const visibleWorkOrders =
        statusFilter === 'semua'
            ? demoWorkOrders
            : demoWorkOrders.filter((wo) => wo.status === statusFilter);

    const allVisibleSelected =
        visibleWorkOrders.length > 0 &&
        visibleWorkOrders.every((wo) => selected.has(wo.id));

    const toggleAll = () => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (allVisibleSelected) {
                visibleWorkOrders.forEach((wo) => next.delete(wo.id));
            } else {
                visibleWorkOrders.forEach((wo) => next.add(wo.id));
            }
            return next;
        });
    };

    const toggleOne = (id: string) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    return (
        <>
            <Head title="Beranda" />

            {/* Page title row */}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-[1.75rem] leading-tight font-semibold tracking-tight">
                    Beranda
                </h1>
                <Button className="h-9 gap-1.5 rounded-md px-3">
                    <Plus aria-hidden="true" className="size-4" />
                    Buat Work Order
                </Button>
            </div>

            {/* Folder-style status queue cards */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {queueCards.map((card) => (
                    <button
                        key={card.status}
                        type="button"
                        onClick={() =>
                            setStatusFilter(
                                statusFilter === card.status
                                    ? 'semua'
                                    : card.status,
                            )
                        }
                        aria-pressed={statusFilter === card.status}
                        className={cn(
                            'group flex flex-col items-start gap-4 rounded-md border bg-card p-4 text-left transition-shadow duration-150 hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none',
                            statusFilter === card.status
                                ? 'border-border-strong ring-1 ring-ring/40'
                                : 'border-border',
                        )}
                    >
                        <div className="flex w-full items-start justify-between">
                            <Folder
                                aria-hidden="true"
                                className="size-7 fill-ink-subtle/25 text-ink-subtle"
                            />
                            <ArrowUpRight
                                aria-hidden="true"
                                className="size-4 text-ink-subtle transition-colors group-hover:text-foreground"
                            />
                        </div>
                        <div className="w-full">
                            <p className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                                {card.title}
                                {card.emphasis && (
                                    <span className="rounded-sm bg-info/10 px-1.5 py-0.5 text-xs font-semibold text-info">
                                        perlu aksi
                                    </span>
                                )}
                            </p>
                            <p className="mt-0.5 text-xs text-ink-muted">
                                {card.count} WO · {card.hint}
                            </p>
                        </div>
                    </button>
                ))}
            </div>

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                {/* Work Order queue */}
                <section
                    aria-labelledby="wo-queue-heading"
                    className="rounded-md border border-border bg-card xl:col-span-2"
                >
                    <header className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
                        <h2
                            id="wo-queue-heading"
                            className="text-base font-semibold"
                        >
                            Antrean Work Order
                        </h2>
                        <Link
                            href={dashboard()}
                            className="inline-flex size-8 items-center justify-center rounded-md text-ink-muted hover:bg-accent hover:text-foreground"
                            aria-label="Buka daftar Work Order lengkap"
                        >
                            <ArrowUpRight className="size-4" />
                        </Link>
                    </header>

                    {/* Toolbar */}
                    <div className="flex flex-wrap items-center gap-2 border-b border-border px-4 py-3">
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Button size="sm" className="h-8 gap-1.5 rounded-md">
                                <Upload aria-hidden="true" className="size-3.5" />
                                Impor
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-8 rounded-md border-border bg-card"
                            >
                                Perbarui
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                aria-pressed={statusFilter === 'semua'}
                                onClick={() => setStatusFilter('semua')}
                                className={cn(
                                    'h-8 rounded-md border-border bg-card',
                                    statusFilter === 'semua' &&
                                        'border-border-strong ring-1 ring-ring/40',
                                )}
                            >
                                Semua
                            </Button>
                        </div>
                        <div className="ml-auto flex flex-wrap items-center gap-2">
                            <label className="relative">
                                <span className="sr-only">Cari Work Order</span>
                                <Search
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-ink-subtle"
                                />
                                <Input
                                    type="search"
                                    placeholder="Cari…"
                                    className="h-8 w-40 rounded-md border-border bg-card pl-8 text-sm"
                                />
                            </label>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-8 gap-1.5 rounded-md border-border bg-card"
                            >
                                <ListFilter
                                    aria-hidden="true"
                                    className="size-3.5"
                                />
                                Urutkan
                                <ChevronDown
                                    aria-hidden="true"
                                    className="size-3 text-ink-subtle"
                                />
                            </Button>
                        </div>
                    </div>

                    {/* Table (desktop) */}
                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-border text-left text-xs font-semibold text-ink-muted">
                                    <th scope="col" className="w-10 px-3 py-2.5">
                                        <Checkbox
                                            checked={
                                                allVisibleSelected
                                                    ? true
                                                    : selected.size > 0
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={toggleAll}
                                            aria-label="Pilih semua Work Order"
                                        />
                                    </th>
                                    <SortableTh>Jenis</SortableTh>
                                    <SortableTh>No. WO</SortableTh>
                                    <SortableTh className="hidden 2xl:table-cell">
                                        Peminta
                                    </SortableTh>
                                    <SortableTh className="hidden 2xl:table-cell">
                                        Dibuat
                                    </SortableTh>
                                    <SortableTh>Deadline</SortableTh>
                                    <SortableTh className="hidden md:table-cell">
                                        PIC
                                    </SortableTh>
                                    <SortableTh>Status</SortableTh>
                                    <th scope="col" className="px-3 py-2.5">
                                        <span className="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {visibleWorkOrders.map((wo) => (
                                    <tr
                                        key={wo.id}
                                        className={cn(
                                            'border-b border-border last:border-b-0 hover:bg-surface-raised',
                                            selected.has(wo.id) &&
                                                'bg-surface-raised',
                                        )}
                                    >
                                        <td className="px-3 py-3 align-middle">
                                            <Checkbox
                                                checked={selected.has(wo.id)}
                                                onCheckedChange={() =>
                                                    toggleOne(wo.id)
                                                }
                                                aria-label={`Pilih ${wo.id}`}
                                            />
                                        </td>
                                        <td className="px-2.5 py-3 align-middle">
                                            <div className="flex items-stretch gap-2.5">
                                                <DeadlineRail
                                                    state={wo.deadlineState}
                                                    label={wo.deadlineLabel}
                                                    className="min-h-9"
                                                />
                                                <div className="min-w-0">
                                                    <p
                                                        className="max-w-[150px] truncate font-medium"
                                                        title={wo.jenis}
                                                    >                                                        {wo.jenisShort}
                                                    </p>
                                                    <CategoryBadge
                                                        category={wo.category}
                                                        className="mt-1"
                                                    />
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-2.5 py-3 align-middle font-mono text-xs whitespace-nowrap text-ink-muted">
                                            {wo.id}
                                        </td>
                                        <td className="hidden px-3 py-3 align-middle 2xl:table-cell">
                                            <PersonCell name={wo.requester} />
                                        </td>
                                        <td className="hidden px-3 py-3 align-middle font-mono text-xs whitespace-nowrap text-ink-muted 2xl:table-cell">
                                            {wo.created}
                                        </td>
                                        <td className="px-2.5 py-3 align-middle whitespace-nowrap">
                                            <span className="font-mono text-xs text-ink-muted">
                                                {wo.deadline}
                                            </span>
                                            <span
                                                className={cn(
                                                    'mt-0.5 block max-w-[140px] text-xs font-medium',
                                                    deadlineTone[
                                                        wo.deadlineState
                                                    ],
                                                )}
                                            >
                                                {wo.deadlineLabel}
                                            </span>
                                        </td>
                                        <td className="hidden px-3 py-3 align-middle md:table-cell">
                                            <PersonCell name={wo.assignee} />
                                        </td>
                                        <td className="px-2.5 py-3 align-middle">
                                            <StatusBadge
                                                status={wo.status}
                                                compact
                                                className="whitespace-nowrap"
                                            />
                                        </td>
                                        <td className="px-3 py-3 text-right align-middle whitespace-nowrap">
                                            <Link
                                                href={dashboard()}
                                                className="inline-flex size-8 items-center justify-center rounded-md text-ink-muted hover:bg-accent hover:text-foreground"
                                                aria-label={`Buka ${wo.id}`}
                                            >
                                                <Ellipsis className="size-4" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Card list (mobile) */}
                    <div className="divide-y divide-border md:hidden">
                        {visibleWorkOrders.map((wo) => (
                            <Link
                                key={wo.id}
                                href={dashboard()}
                                className="relative flex gap-3 p-4 hover:bg-surface-raised"
                            >
                                <DeadlineRail
                                    state={wo.deadlineState}
                                    label={wo.deadlineLabel}
                                    className="-ml-4 self-stretch"
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold">
                                        {wo.jenisShort}
                                    </p>
                                    <p className="mt-0.5 font-mono text-xs text-ink-muted">
                                        {wo.id}
                                    </p>
                                    <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                        <CategoryBadge category={wo.category} />
                                        <StatusBadge status={wo.status} />
                                    </div>
                                    <p className="mt-2 text-xs text-ink-muted">
                                        Deadline {wo.deadline} · {wo.assignee}
                                    </p>
                                </div>
                            </Link>
                        ))}
                    </div>
                </section>

                {/* Right rail */}
                <div className="flex flex-col gap-4">
                    {/* SLA compliance */}
                    <section
                        aria-labelledby="sla-heading"
                        className="rounded-md border border-border bg-card p-4"
                    >
                        <div className="flex items-start justify-between">
                            <h2
                                id="sla-heading"
                                className="text-sm font-semibold"
                            >
                                Kepatuhan SLA
                            </h2>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 rounded-md"
                                aria-label="Opsi SLA"
                            >
                                <Ellipsis className="size-4" />
                            </Button>
                        </div>

                        {/* Percent labels above each segment (mockup anatomy) */}
                        <div className="mt-2 flex text-xs font-medium text-ink-muted">
                            {slaSegments.map((s) => (
                                <span
                                    key={s.label}
                                    style={{ width: `${s.pct}%` }}
                                    className={s.align}
                                >
                                    {String(s.pct).padStart(2, '0')}%
                                </span>
                            ))}
                        </div>

                        <div className="mt-1 flex h-2 w-full overflow-hidden rounded-full bg-border">
                            <span
                                className="h-full bg-success"
                                style={{ width: '80%' }}
                            />
                            <span
                                className="h-full bg-danger"
                                style={{ width: '15%' }}
                            />
                            <span
                                className="h-full bg-escalation"
                                style={{ width: '5%' }}
                            />
                            <span className="sr-only">
                                32 on track, 6 telat, 2 eskalasi
                            </span>
                        </div>

                        {/* Signal tiles with colored icons */}
                        <div className="mt-3 grid grid-cols-3 gap-2">
                            {attentionTiles.map((tile) => (
                                <div
                                    key={tile.label}
                                    className="rounded-md border border-border bg-card px-2 py-1.5 text-center"
                                >
                                    <p className="flex items-center justify-center gap-1 text-xs font-semibold">
                                        <tile.icon
                                            aria-hidden="true"
                                            className={cn(
                                                'size-3.5',
                                                tile.tone,
                                            )}
                                        />
                                        {tile.count}
                                    </p>
                                    <p className="text-xs text-ink-muted">
                                        {tile.label}
                                    </p>
                                </div>
                            ))}
                        </div>

                        {/* Headline numbers */}
                        <div className="mt-4 flex items-baseline gap-6">
                            <p className="flex items-baseline gap-2">
                                <span className="text-3xl font-semibold tracking-tight">
                                    32
                                </span>
                                <span className="text-xs text-ink-muted">
                                    On track
                                    <span className="sr-only"> Work Order</span>
                                </span>
                            </p>
                            <p className="flex items-baseline gap-2">
                                <span className="text-3xl font-semibold text-danger">
                                    6
                                </span>
                                <span className="text-xs text-ink-muted">
                                    Telat
                                    <span className="sr-only"> Work Order</span>
                                </span>
                            </p>
                        </div>

                        {showSlaWarning && (
                            <div className="mt-3 flex items-start justify-between gap-2 rounded-md border border-warning/30 bg-warning/5 px-2.5 py-2">
                                <p className="flex items-start gap-2 text-xs text-foreground">
                                    <TriangleAlert
                                        aria-hidden="true"
                                        className="mt-0.5 size-3.5 shrink-0 text-warning"
                                    />
                                    6 WO telat & 2 eskalasi. Tinjau sekarang agar
                                    SLA tidak memburuk.
                                </p>
                                <button
                                    type="button"
                                    onClick={() => setShowSlaWarning(false)}
                                    aria-label="Tutup peringatan"
                                    className="shrink-0 rounded-sm p-0.5 text-ink-muted hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                                >
                                    <X aria-hidden="true" className="size-3.5" />
                                </button>
                            </div>
                        )}
                    </section>

                    {/* Deadline pulse (mockup: Storage Usage) */}
                    <section
                        aria-labelledby="pulse-heading"
                        className="relative overflow-hidden rounded-md bg-brand p-4 text-on-brand"
                    >
                        <span
                            aria-hidden="true"
                            className="absolute -top-10 -right-10 size-32 rounded-full border-8 border-white/15"
                        />
                        <div className="flex items-start justify-between">
                            <h2
                                id="pulse-heading"
                                className="text-base font-semibold"
                            >
                                Deadline Pulse
                            </h2>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 rounded-md text-white/80 hover:bg-white/10 hover:text-white"
                                aria-label="Opsi Deadline Pulse"
                            >
                                <Ellipsis className="size-4" />
                            </Button>
                        </div>
                        <div className="mt-4 flex items-center gap-4">
                            <div
                                className="relative size-24 shrink-0"
                                role="img"
                                aria-label="40 persen WO mendekati deadline"
                            >
                                <svg
                                    viewBox="0 0 36 36"
                                    className="size-full -rotate-90"
                                >
                                    {/* decorative sunburst outer ring (mockup) */}
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="17.4"
                                        fill="none"
                                        stroke="rgba(255,255,255,0.35)"
                                        strokeWidth="0.8"
                                        strokeDasharray="0.7 2.2"
                                    />
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="14.4"
                                        fill="none"
                                        stroke="rgba(255,255,255,0.25)"
                                        strokeWidth="3"
                                    />
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="14.4"
                                        fill="none"
                                        stroke="#ffffff"
                                        strokeWidth="3"
                                        strokeLinecap="round"
                                        strokeDasharray="40 60"
                                        pathLength={100}
                                    />
                                </svg>
                                <div className="absolute inset-0 flex flex-col items-center justify-center">
                                    <span className="text-xl font-semibold">
                                        40%
                                    </span>
                                    <span className="text-xs whitespace-nowrap text-white/85">
                                        32 dari 80 WO
                                    </span>
                                </div>
                            </div>
                            <ul className="flex-1 space-y-1.5">
                                {pulseTiles.map((tile) => (
                                    <li
                                        key={tile.label}
                                        className="rounded-md border border-white/20 bg-white/10 px-2.5 py-1.5 text-xs"
                                    >
                                        <p className="font-semibold">
                                            <Zap
                                                aria-hidden="true"
                                                className="mr-1 inline size-3 text-white/90"
                                            />
                                            {tile.trend} · {tile.value}
                                        </p>
                                        <p className="text-white/80">
                                            {tile.label}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Beranda',
            href: dashboard(),
        },
    ],
};
