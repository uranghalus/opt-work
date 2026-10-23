import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Circle,
    CircleDot,
    MapPin,
    RefreshCw,
    Search,
    Sparkles,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { InertiaConfig } from '@inertiajs/core';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState } from '@/components/ui/EmptyState';
import { useRequiredTenant } from '@/lib/tenant';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/daily-work';

/**
 * FR-5.3–5.4 — daftar harian Daily Work milik karyawan (materialize-on-view
 * di server), plus update status/lokasi item miliknya sendiri. Desain
 * .scratch/daily-work/design.md: card list (bukan tabel), navigasi tanggal,
 * search + filter status, paginasi server-side.
 */
type DailyWorkItem = {
    id: string;
    work_date: string;
    title: string;
    description: string | null;
    location: string | null;
    status: 'pending' | 'in_progress' | 'done';
    done_at: string | null;
    source: 'template' | 'adhoc';
    template_id: string | null;
};

type Pagination = {
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type PageProps = InertiaConfig['sharedPageProps'] & {
    dailyWork: Pagination & { data: DailyWorkItem[] };
    date: string;
    can_update: boolean;
};

const STATUS_LABELS: Record<DailyWorkItem['status'], string> = {
    pending: 'Belum',
    in_progress: 'Proses',
    done: 'Selesai',
};

const STATUS_ORDER: DailyWorkItem['status'][] = [
    'pending',
    'in_progress',
    'done',
];

/** Tiga sinyal: teks label + ikon + warna (WCAG 1.4.1 — bukan warna saja). */
const STATUS_STYLES: Record<
    DailyWorkItem['status'],
    { icon: typeof Circle; badge: string }
> = {
    pending: {
        icon: Circle,
        badge: 'bg-surface-raised text-ink-muted border-border',
    },
    in_progress: {
        icon: CircleDot,
        badge: 'bg-info-subtle text-info border-transparent',
    },
    done: {
        icon: CheckCircle2,
        badge: 'bg-success-subtle text-success border-transparent',
    },
};

function formatDateId(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function shiftDate(value: string, days: number): string {
    const next = new Date(`${value}T00:00:00`);
    next.setDate(next.getDate() + days);
    return next.toISOString().slice(0, 10);
}

/** Format lokal 'YYYY-MM-DD' untuk hari ini — tanpa geser timezone. */
function todayLocal(): string {
    return new Date().toLocaleDateString('sv-SE');
}

function StatusChip({ status }: { status: DailyWorkItem['status'] }) {
    const style = STATUS_STYLES[status];
    const Icon = style.icon;

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold',
                style.badge,
            )}
        >
            <Icon aria-hidden="true" className="size-3" />
            {STATUS_LABELS[status]}
        </span>
    );
}

function DailyWorkCard({
    item,
    canUpdate,
}: {
    item: DailyWorkItem;
    canUpdate: boolean;
}) {
    const tenant = useRequiredTenant('daily-work.index');
    const [location, setLocation] = useState(item.location ?? '');
    const form = useForm<{
        status: DailyWorkItem['status'];
        location: string;
    }>({
        status: item.status,
        location: item.location ?? '',
    });

    const patch = (status: DailyWorkItem['status'], nextLocation?: string) => {
        form.setData({ status, location: nextLocation ?? location });
        form.patch(update.url({ tenant, dailyWork: item.id }), {
            preserveScroll: true,
        });
    };

    return (
        <Card className="gap-0 border-border py-4 shadow-card">
            <CardContent className="flex flex-col gap-3 px-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-base leading-snug font-semibold text-ink">
                            {item.title}
                        </p>
                        {item.description && (
                            <p className="mt-1 text-sm text-ink-muted">
                                {item.description}
                            </p>
                        )}
                    </div>
                    <StatusChip status={form.data.status} />
                </div>

                <div className="flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                    {item.source === 'adhoc' ? (
                        <Badge className="gap-1 border-transparent bg-owner-urgent-subtle text-owner-urgent">
                            <Sparkles aria-hidden="true" className="size-3" />
                            Tugas tambahan
                        </Badge>
                    ) : (
                        <Badge className="gap-1 border-transparent bg-info-subtle text-info">
                            <RefreshCw aria-hidden="true" className="size-3" />
                            Rutin
                        </Badge>
                    )}
                    {(form.data.location || item.location) && (
                        <span className="inline-flex items-center gap-1">
                            <MapPin aria-hidden="true" className="size-3.5" />
                            {form.data.location || item.location}
                        </span>
                    )}
                    {item.done_at && (
                        <span>
                            Selesai{' '}
                            {new Date(item.done_at).toLocaleTimeString('id-ID', {
                                hour: '2-digit',
                                minute: '2-digit',
                            })}
                        </span>
                    )}
                </div>

                {canUpdate && (
                    <div className="flex flex-col gap-2 border-t border-border pt-3 sm:flex-row sm:items-center">
                        <Input
                            aria-label={`Lokasi untuk ${item.title}`}
                            placeholder="Lokasi pekerjaan (opsional)"
                            value={location}
                            onChange={(event) => setLocation(event.target.value)}
                            onBlur={() => {
                                if (location !== (item.location ?? '')) {
                                    patch(form.data.status, location);
                                }
                            }}
                            className="h-11 flex-1"
                        />
                        <div
                            role="group"
                            aria-label={`Status ${item.title}`}
                            className="flex gap-1.5"
                        >
                            {STATUS_ORDER.map((status) => {
                                const active = form.data.status === status;
                                return (
                                    <Button
                                        key={status}
                                        type="button"
                                        size="sm"
                                        variant={active ? 'default' : 'outline'}
                                        aria-pressed={active}
                                        disabled={form.processing}
                                        onClick={() => patch(status)}
                                        className="min-h-11 flex-1 sm:flex-none"
                                    >
                                        {STATUS_LABELS[status]}
                                    </Button>
                                );
                            })}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function DailyWorkIndex({
    dailyWork,
    date,
    can_update,
}: PageProps) {
    const tenant = useRequiredTenant('daily-work.index');
    const [searchTerm, setSearchTerm] = useState('');
    const [appliedSearch, setAppliedSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [navigating, setNavigating] = useState(false);
    const skipFirstQuery = useRef(true);

    // Debounce 300ms — pola employees/index.tsx (server-side search).
    useEffect(() => {
        const timer = setTimeout(() => setAppliedSearch(searchTerm), 300);
        return () => clearTimeout(timer);
    }, [searchTerm]);

    const buildParams = (
        overrides: Record<string, string | undefined> = {},
    ): Record<string, string | undefined> => ({
        search: appliedSearch || undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
        // Pertahankan tanggal yang sedang dilihat — filter jangan me-reset
        // konteks hari ke hari ini.
        date: date !== todayLocal() ? date : undefined,
        ...overrides,
    });

    const goTo = (overrides: Record<string, string | undefined>) => {
        setNavigating(true);
        router.get(index({ tenant }), buildParams(overrides), {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setNavigating(true),
            onFinish: () => setNavigating(false),
        });
    };

    // Sinkronisasi URL untuk filter (search ter-debounce + status) —
    // skip pada render pertama agar tidak menimpa query tanggal dari URL.
    useEffect(() => {
        if (skipFirstQuery.current) {
            skipFirstQuery.current = false;
            return;
        }
        router.get(index({ tenant }), buildParams({ page: '1' }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setNavigating(true),
            onFinish: () => setNavigating(false),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [appliedSearch, statusFilter]);

    const hasItems = dailyWork.data.length > 0;

    return (
        <>
            <Head title="Daily Work" />

            <div className="flex flex-col gap-4">
                {/* Navigasi tanggal */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Hari sebelumnya"
                            onClick={() => goTo({ date: shiftDate(date, -1) })}
                        >
                            <ChevronLeft aria-hidden="true" className="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Hari berikutnya"
                            onClick={() => goTo({ date: shiftDate(date, 1) })}
                        >
                            <ChevronRight aria-hidden="true" className="size-4" />
                        </Button>
                        <p className="ml-1 flex items-center gap-1.5 text-sm font-semibold text-ink">
                            <CalendarDays
                                aria-hidden="true"
                                className="size-4 text-ink-muted"
                            />
                            {formatDateId(date)}
                        </p>
                        {date !== todayLocal() && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="ml-1"
                                onClick={() => goTo({ date: undefined })}
                            >
                                Hari ini
                            </Button>
                        )}
                    </div>

                    <p className="text-xs text-ink-muted">
                        {dailyWork.total} tugas
                    </p>
                </div>

                {/* Filter bar: search + status */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div className="relative sm:max-w-xs sm:flex-1">
                        <Search
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-subtle"
                        />
                        <Input
                            type="search"
                            aria-label="Cari judul tugas"
                            placeholder="Cari tugas…"
                            value={searchTerm}
                            onChange={(event) =>
                                setSearchTerm(event.target.value)
                            }
                            className="h-11 pl-9"
                        />
                    </div>
                    <Select
                        value={statusFilter}
                        onValueChange={(value) => {
                            // Navigasi dipicu efek sinkronisasi URL di atas
                            // (single driver) — goTo di sini akan mendobel
                            // request yang identik.
                            setStatusFilter(value);
                        }}
                    >
                        <SelectTrigger
                            aria-label="Filter status"
                            className="h-11 sm:w-44"
                        >
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua status</SelectItem>
                            {STATUS_ORDER.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {STATUS_LABELS[status]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {navigating ? (
                    <div className="flex flex-col gap-3" aria-busy="true">
                        <Skeleton className="h-28 w-full rounded-xl" />
                        <Skeleton className="h-28 w-full rounded-xl" />
                        <Skeleton className="h-28 w-full rounded-xl" />
                    </div>
                ) : hasItems ? (
                    <div className="flex flex-col gap-3">
                        {dailyWork.data.map((item) => (
                            <DailyWorkCard
                                key={item.id}
                                item={item}
                                canUpdate={can_update}
                            />
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={CalendarDays}
                        title="Belum ada tugas untuk tanggal ini"
                        description="Template rutin dari HOD akan muncul otomatis di sini setiap harinya."
                    />
                )}

                {/* Paginasi server-side (pola departments/index.tsx) */}
                {dailyWork.last_page > 1 && (
                    <nav
                        aria-label="Paginasi daftar tugas"
                        className="flex items-center justify-between gap-3"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={dailyWork.current_page <= 1}
                            onClick={() =>
                                goTo({
                                    page: String(dailyWork.current_page - 1),
                                })
                            }
                        >
                            <ChevronLeft aria-hidden="true" className="size-4" />
                            Sebelumnya
                        </Button>
                        <p
                            aria-live="polite"
                            className="text-xs text-ink-muted"
                        >
                            Halaman {dailyWork.current_page} dari{' '}
                            {dailyWork.last_page}
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={
                                dailyWork.current_page >= dailyWork.last_page
                            }
                            onClick={() =>
                                goTo({
                                    page: String(dailyWork.current_page + 1),
                                })
                            }
                        >
                            Berikutnya
                            <ChevronRight
                                aria-hidden="true"
                                className="size-4"
                            />
                        </Button>
                    </nav>
                )}
            </div>
        </>
    );
}

DailyWorkIndex.layout = {
    breadcrumbs: [{ title: 'Daily Work' }],
    title: 'Daily Work',
    description: 'Tugas harian rutin dan tambahan untuk Anda',
};
