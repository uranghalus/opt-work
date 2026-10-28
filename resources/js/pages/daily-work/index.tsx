import { Head, useForm, router } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { CheckCircle2, Clock, MoreHorizontal } from 'lucide-react';
import { useRequiredTenant } from '@/lib/tenant';

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
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type PageProps = InertiaConfig['sharedPageProps'] & {
    dailyWork: Pagination & { data: DailyWorkItem[] };
    date: string;
    can_update: boolean;
};

const statusLabel = (status: string): string => {
    switch (status) {
        case 'pending':
            return 'Belum';
        case 'in_progress':
            return 'Proses';
        case 'done':
            return 'Selesai';
        default:
            return status;
    }
};

const statusVariant = (status: string): string => {
    switch (status) {
        case 'pending':
            return 'outline text-ink-muted border-border';
        case 'in_progress':
            return 'outline text-brand border-brand/30 bg-brand-subtle';
        case 'done':
            return 'text-success-ink border-success/30 bg-success-subtle';
        default:
            return 'outline text-ink-muted border-border';
    }
};

const StatusChip = ({ status }: { status: string }) => (
    <span
        className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold ${statusVariant(status)}`}
    >
        {status === 'done' && (
            <CheckCircle2 className="size-3" aria-hidden="true" />
        )}
        {status === 'in_progress' && (
            <Clock className="size-3" aria-hidden="true" />
        )}
        {statusLabel(status)}
    </span>
);

DailyWorkIndex.layout = {
    breadcrumbs: [{ title: 'Daily Work' }],
    title: 'Daily Work',
    description: 'Daftar tugas harian Anda — gabungan template rutin dan tugas tambahan HOD.',
};

export default function DailyWorkIndex({ dailyWork, date, can_update }: PageProps) {
    const tenant = useRequiredTenant('daily-work.index');
    const { data, ...pagination } = dailyWork;

    const form = useForm<{
        status: string;
        location: string;
    }>({
        status: 'pending',
        location: '',
    });

    const updateStatus = (id: string, status: string, location: string | null) => {
        form.data.status = status;
        form.data.location = location ?? '';
        form.patch(`/${tenant}/daily-work/${id}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Daily Work" />

            <div className="space-y-5">
                <div className="flex items-center gap-2">
                    <span className="text-[11px] font-semibold tracking-wide text-ink-muted uppercase">
                        Tanggal
                    </span>
                    <Input
                        type="date"
                        value={date}
                        className="w-44 bg-card"
                        onChange={(event) => {
                            router.get(
                                `/${tenant}/daily-work?date=${event.target.value}`,
                                {},
                                { preserveScroll: true },
                            );
                        }}
                    />
                </div>

                <div className="flex items-center justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-card">
                    <div className="space-y-1">
                        <Label htmlFor="dw-search">Cari</Label>
                        <Input
                            id="dw-search"
                            placeholder="Cari tugas…"
                            className="w-48 bg-card"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="dw-status">Status</Label>
                        <Select
                            value="all"
                            onValueChange={() => {}}
                        >
                            <SelectTrigger id="dw-status-trigger" className="w-36 bg-card">
                                <SelectValue placeholder="Semua status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua</SelectItem>
                                <SelectItem value="pending">Belum</SelectItem>
                                <SelectItem value="in_progress">Proses</SelectItem>
                                <SelectItem value="done">Selesai</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {data.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border-strong/60 bg-card px-6 py-16 text-center shadow-card">
                        <span
                            aria-hidden="true"
                            className="mx-auto flex size-12 items-center justify-center rounded-xl bg-brand/10 text-brand"
                        >
                            <MoreHorizontal className="size-5" />
                        </span>
                        <p className="mt-4 text-base font-semibold tracking-[-0.01em] text-ink">
                            Belum ada tugas harian
                        </p>
                        <p className="mx-auto mt-1.5 max-w-sm text-sm leading-relaxed text-ink-muted">
                            Tugas rutin dan tugas tambahan dari HOD akan muncul di sini setiap hari.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-border bg-card shadow-card">
                        <ul className="divide-y divide-border">
                            {data.map((item) => (
                                <li key={item.id} className="flex items-start justify-between gap-4 px-4 py-3.5">
                                    <div className="min-w-0 space-y-1">
                                        <p className="font-mono text-[10px] font-medium tracking-[0.14em] text-ink-muted uppercase">
                                            {item.source === 'template' ? 'Rutin' : 'Tambahan'}
                                        </p>
                                        <p className="mt-0.5 text-[14.5px] font-semibold tracking-[-0.01em] text-ink">
                                            {item.title}
                                        </p>
                                        {item.description && (
                                            <p className="mt-1 text-[11.5px] text-ink-muted line-clamp-2">
                                                {item.description}
                                            </p>
                                        )}
                                        {item.location && (
                                            <p className="mt-1 flex items-center gap-1 text-[11.5px] text-ink-muted tabular-nums">
                                                <span className="size-3.5">📍</span>
                                                {item.location}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-3 shrink-0">
                                        <StatusChip status={item.status} />
                                        {can_update && item.status !== 'done' ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    updateStatus(item.id, 'done', item.location)
                                                }
                                            >
                                                Tandai selesai
                                            </Button>
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ul>

                        {pagination.last_page > 1 && (
                            <div className="border-t border-border bg-surface-raised/60 px-4 py-2.5">
                                <div className="flex items-center justify-between gap-3 text-[11px] text-ink-muted">
                                    <span>
                                        {pagination.from ?? 0}–{pagination.to ?? 0} dari{' '}
                                        {pagination.total} tugas
                                    </span>
                                    <div className="flex items-center gap-1.5">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="h-8 bg-card px-2.5 sm:px-3"
                                            disabled={!pagination.prev_page_url}
                                            onClick={() =>
                                                router.get(
                                                    pagination.prev_page_url ?? '',
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Sebelumnya
                                        </Button>
                                        <span className="px-1.5 font-mono tabular-nums">
                                            {pagination.current_page} / {pagination.last_page}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="h-8 bg-card px-2.5 sm:px-3"
                                            disabled={!pagination.next_page_url}
                                            onClick={() =>
                                                router.get(
                                                    pagination.next_page_url ?? '',
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Selanjutnya
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
