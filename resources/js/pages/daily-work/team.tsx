import { Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Circle,
    CircleDot,
    CheckCircle2,
    MapPin,
    RefreshCw,
    Search,
    Sparkles,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
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
import { team } from '@/routes/daily-work';

/**
 * Pratinjau HOD (Task 3) — daftar harian satu karyawan bawahan pada satu
 * tanggal; server menjalankan materialize-on-view yang sama sehingga yang
 * dilihat HOD = yang dilihat karyawan (FR-5.3). Read-only: status dikelola
 * karyawan pemilik item (FR-5.4).
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

type PageProps = {
    dailyWork: Pagination & { data: DailyWorkItem[] };
    employee: {
        id: string;
        nama_employee: string;
        department_name: string | null;
    };
    date: string;
};

const STATUS_LABELS: Record<DailyWorkItem['status'], string> = {
    pending: 'Belum',
    in_progress: 'Proses',
    done: 'Selesai',
};

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

export default function DailyWorkTeam({
    dailyWork,
    employee,
    date,
}: PageProps) {
    const tenant = useRequiredTenant('daily-work.team');
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [navigating, setNavigating] = useState(false);

    const goTo = (overrides: Record<string, string | undefined>) => {
        setNavigating(true);
        router.get(
            team({ tenant }, { query: { employee: employee.id } }),
            {
                search: searchTerm || undefined,
                status: statusFilter !== 'all' ? statusFilter : undefined,
                ...overrides,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setNavigating(true),
                onFinish: () => setNavigating(false),
            },
        );
    };

    const hasItems = dailyWork.data.length > 0;

    return (
        <>
            <Head title={`Harian — ${employee.nama_employee}`} />

            <div className="flex flex-col gap-4">
                {/* Identitas karyawan + navigasi tanggal */}
                <div className="flex flex-col gap-3">
                    <p className="flex items-center gap-1.5 text-sm font-semibold text-ink">
                        <UserRound
                            aria-hidden="true"
                            className="size-4 text-ink-muted"
                        />
                        {employee.nama_employee}
                        {employee.department_name && (
                            <span className="text-ink-muted">
                                · {employee.department_name}
                            </span>
                        )}
                    </p>

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="Hari sebelumnya"
                                onClick={() => goTo({ date: shiftDate(date, -1) })}
                            >
                                <ChevronLeft
                                    aria-hidden="true"
                                    className="size-4"
                                />
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="Hari berikutnya"
                                onClick={() => goTo({ date: shiftDate(date, 1) })}
                            >
                                <ChevronRight
                                    aria-hidden="true"
                                    className="size-4"
                                />
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
                </div>

                {/* Filter */}
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
                            onChange={(event) => setSearchTerm(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    goTo({ page: '1' });
                                }
                            }}
                            className="h-11 pl-9"
                        />
                    </div>
                    <Select
                        value={statusFilter}
                        onValueChange={(value) => {
                            setStatusFilter(value);
                            goTo({
                                status:
                                    value === 'all' ? undefined : value,
                                page: '1',
                            });
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
                            {(['pending', 'in_progress', 'done'] as const).map(
                                (status) => (
                                    <SelectItem key={status} value={status}>
                                        {STATUS_LABELS[status]}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>

                {navigating ? (
                    <div className="flex flex-col gap-3" aria-busy="true">
                        <Skeleton className="h-24 w-full rounded-xl" />
                        <Skeleton className="h-24 w-full rounded-xl" />
                        <Skeleton className="h-24 w-full rounded-xl" />
                    </div>
                ) : hasItems ? (
                    <div className="flex flex-col gap-3">
                        {dailyWork.data.map((item) => (
                            <Card
                                key={item.id}
                                className="gap-0 border-border py-4 shadow-card"
                            >
                                <CardContent className="flex flex-col gap-2 px-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <p className="text-base leading-snug font-semibold text-ink">
                                            {item.title}
                                        </p>
                                        <StatusChip status={item.status} />
                                    </div>
                                    {item.description && (
                                        <p className="text-sm text-ink-muted">
                                            {item.description}
                                        </p>
                                    )}
                                    <div className="flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                                        {item.source === 'adhoc' ? (
                                            <Badge className="gap-1 border-transparent bg-owner-urgent-subtle text-owner-urgent">
                                                <Sparkles
                                                    aria-hidden="true"
                                                    className="size-3"
                                                />
                                                Tugas tambahan
                                            </Badge>
                                        ) : (
                                            <Badge className="gap-1 border-transparent bg-info-subtle text-info">
                                                <RefreshCw
                                                    aria-hidden="true"
                                                    className="size-3"
                                                />
                                                Rutin
                                            </Badge>
                                        )}
                                        {item.location && (
                                            <span className="inline-flex items-center gap-1">
                                                <MapPin
                                                    aria-hidden="true"
                                                    className="size-3.5"
                                                />
                                                {item.location}
                                            </span>
                                        )}
                                        {item.done_at && (
                                            <span>
                                                Selesai{' '}
                                                {new Date(
                                                    item.done_at,
                                                ).toLocaleTimeString('id-ID', {
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </span>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={CalendarDays}
                        title="Belum ada tugas untuk tanggal ini"
                        description="Template rutin aktif karyawan ini akan muncul otomatis pada hari yang cocok."
                    />
                )}

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
                            <ChevronLeft
                                aria-hidden="true"
                                className="size-4"
                            />
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

DailyWorkTeam.layout = {
    breadcrumbs: [
        { title: 'Daily Work', href: '/daily-work' },
        { title: 'Harian Karyawan' },
    ],
    title: 'Harian Karyawan',
    description: 'Pratinjau daftar harian yang dilihat karyawan',
};
