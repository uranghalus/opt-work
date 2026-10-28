import { Head, router } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    MapPin,
    RefreshCw,
    Search,
    Sparkles,
    UserRound,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
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
import {
    index as verificationIndex,
    verify as verifyRoute,
    reject as rejectRoute,
    attachment as attachmentRoute,
} from '@/routes/daily-work';
import { VerificationDialog } from './-components/verification-dialog';
import { RejectDialog } from './-components/reject-dialog';
import { StatusChip } from './-components/status';

type VerificationQueueItem = {
    id: string;
    work_date: string;
    title: string;
    description: string | null;
    location: string | null;
    status: 'pending_verify';
    submitted_at: string | null;
    done_at: string | null;
    source: 'template' | 'adhoc';
    template_id: string | null;
    proof_notes: string | null;
    proof_attachments: string[];
    employee: { id: string; nama_employee: string };
    verification: {
        verified_by_name: string | null;
        verified_at: string | null;
        rejected: boolean;
        rejection_note: string | null;
    };
};

type Pagination = {
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type PageProps = InertiaConfig['sharedPageProps'] & {
    verificationQueue: Pagination & { data: VerificationQueueItem[] };
    employees: { id: string; nama_employee: string }[];
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

export default function DailyWorkVerification({
    verificationQueue,
    employees,
}: PageProps) {
    const tenant = useRequiredTenant('daily-work.verification');
    const [searchTerm, setSearchTerm] = useState('');
    const [appliedSearch, setAppliedSearch] = useState('');
    const [employeeFilter, setEmployeeFilter] = useState<string | null>(null);
    const [navigating, setNavigating] = useState(false);
    const [proofDialogOpen, setProofDialogOpen] = useState(false);
    const [selectedItem, setSelectedItem] = useState<VerificationQueueItem | null>(
        null,
    );
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
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
        employee_id: employeeFilter || undefined,
        ...overrides,
    });

    const goTo = (overrides: Record<string, string | undefined>) => {
        setNavigating(true);
        router.get(verificationIndex({ tenant }), buildParams(overrides), {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setNavigating(true),
            onFinish: () => setNavigating(false),
        });
    };

    // Sinkronisasi URL untuk filter (search ter-debounce + karyawan) —
    // skip pada render pertama agar tidak menimpa query dari URL.
    useEffect(() => {
        if (skipFirstQuery.current) {
            skipFirstQuery.current = false;
            return;
        }
        router.get(verificationIndex({ tenant }), buildParams({ page: '1' }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setNavigating(true),
            onFinish: () => setNavigating(false),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [appliedSearch, employeeFilter]);

    const handleApprove = useCallback(
        (id: string) => {
            router.post(verifyRoute.url({ tenant, dailyWork: id }), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Daily Work ditandai telah selesai.');
                    setProofDialogOpen(false);
                    setSelectedItem(null);
                },
                onError: () => {
                    toast.error('Gagal menyetujui Daily Work.');
                },
            });
        },
        [tenant],
    );

    const handleReject = useCallback(
        (id: string, note: string) => {
            router.post(rejectRoute.url({ tenant, dailyWork: id }), { rejection_note: note }, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Bukti dikembalikan kepada karyawan.');
                    setRejectDialogOpen(false);
                    setSelectedItem(null);
                },
                onError: () => {
                    toast.error('Gagal mengembalikan bukti.');
                },
            });
        },
        [tenant],
    );

    const hasItems = verificationQueue.data.length > 0;

    return (
        <>
            <Head title="Perlu Verifikasi" />

            <div className="flex flex-col gap-4">
                {/* Filter bar: karyawan + search */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative sm:max-w-xs sm:flex-1">
                        <Select
                            value={employeeFilter ?? ''}
                            onValueChange={(value) =>
                                setEmployeeFilter(value || null)
                            }
                        >
                            <SelectTrigger className="h-11 sm:w-44">
                                <SelectValue placeholder="Semua karyawan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Semua karyawan</SelectItem>
                                {employees.map((emp) => (
                                    <SelectItem key={emp.id} value={emp.id}>
                                        {emp.nama_employee}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
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
                </div>

                {navigating ? (
                    <div className="flex flex-col gap-3" aria-busy="true">
                        <Skeleton className="h-32 w-full rounded-xl" />
                        <Skeleton className="h-32 w-full rounded-xl" />
                        <Skeleton className="h-32 w-full rounded-xl" />
                    </div>
                ) : hasItems ? (
                    <div className="flex flex-col gap-3">
                        {verificationQueue.data.map((item) => (
                            <Card
                                key={item.id}
                                className="border border-l-[3px] gap-0 py-4 shadow-card"
                            >
                                <CardContent className="flex flex-col gap-3 px-4 pl-3">
                                    {/* Header: karyawan + title + status chip */}
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="text-base font-semibold text-ink">
                                                {item.employee.nama_employee}
                                            </p>
                                            <p className="text-base leading-snug font-semibold text-ink">
                                                {item.title}
                                            </p>
                                            {item.description && (
                                                <p className="mt-1 text-sm text-ink-muted">
                                                    {item.description}
                                                </p>
                                            )}
                                        </div>
                                        <StatusChip
                                            item={{
                                                status: item.status,
                                                verified_by_name:
                                                    item.verification
                                                        .verified_by_name,
                                            }}
                                        />
                                    </div>

                                    {/* Meta row: source + location + submitted_at */}
                                    <div className="flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                                        {item.source === 'adhoc' ? (
                                            <Badge
                                                className="gap-1 border-transparent bg-owner-urgent-subtle text-owner-urgent"
                                            >
                                                <Sparkles
                                                    aria-hidden="true"
                                                    className="size-3"
                                                />
                                                Tugas tambahan
                                            </Badge>
                                        ) : (
                                            <Badge
                                                className="gap-1 border-transparent bg-info-subtle text-info"
                                            >
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
                                        <span className="text-xs text-ink-muted">
                                            Disubmit{' '}
                                            {item.submitted_at
                                                ? new Date(
                                                      item.submitted_at,
                                                  ).toLocaleString('id-ID', {
                                                      weekday: 'long',
                                                      day: 'numeric',
                                                      month: 'long',
                                                      year: 'numeric',
                                                      hour: '2-digit',
                                                      minute: '2-digit',
                                                  })
                                                : '—'}
                                        </span>
                                    </div>

                                    {/* Photo thumbnails */}
                                    {item.proof_attachments.length > 0 && (
                                        <div className="flex flex-col gap-2">
                                            <ul
                                                className="grid grid-cols-2 sm:grid-cols-3 gap-2"
                                                role="list"
                                                aria-label="Foto bukti"
                                            >
                                                {item.proof_attachments.map(
                                                    (_, index) => (
                                                        <li
                                                            key={index}
                                                            className="relative aspect-square overflow-hidden rounded-md border border-border bg-surface-raised"
                                                        >
                                                            <a
                                                                href={verifyRoute.url({
                                                                    tenant,
                                                                    dailyWork: item.id,
                                                                }).replace(
                                                                    '/verify',
                                                                    `/attachments/${index}`,
                                                                )}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="block h-full w-full"
                                                                onClick={
                                                                    (event) => {
                                                                        event.preventDefault();
                                                                        window
                                                                            .open(
                                                                                attachmentRoute.url({
                                                                                    tenant,
                                                                                    dailyWork: item.id,
                                                                                    index,
                                                                                }),
                                                                                '_blank',
                                                                            );
                                                                    }
                                                                }
                                                            >
                                                                <img
                                                                    src={attachmentRoute.url({
                                                                        tenant,
                                                                        dailyWork: item.id,
                                                                        index,
                                                                    })}
                                                                    alt={`Bukti foto ${index + 1}`}
                                                                    className="h-full w-full object-cover"
                                                                    loading="lazy"
                                                                />
                                                                <span
                                                                    className="absolute inset-0 flex items-center justify-center bg-black/40 text-white text-xs font-semibold"
                                                                >
                                                                    Lihat
                                                                </span>
                                                            </a>
                                                            {index === 0 &&
                                                            item.proof_attachments.length >
                                                                1 && (
                                                                <span
                                                                    className="absolute inset-0 flex items-center justify-center bg-black/40 text-white text-xs font-semibold"
                                                                >
                                                                    +{item.proof_attachments.length - 1}
                                                                </span>
                                                            )}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    )}

                                    {/* Action buttons */}
                                    <div className="flex flex-col gap-2 sm:flex-row sm:justify-end pt-3 border-t border-border">
                                        <Button
                                            type="button"
                                            onClick={() => {
                                                setSelectedItem(item);
                                                setProofDialogOpen(true);
                                            }}
                                            disabled={false}
                                            className="min-h-11 flex-1 sm:flex-none"
                                        >
                                            Tandai telah selesai
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            onClick={() => {
                                                setSelectedItem(item);
                                                setRejectDialogOpen(true);
                                            }}
                                            className="min-h-11 flex-1 sm:flex-none"
                                        >
                                            Kembali
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={CalendarClock}
                        title="Tidak ada tugas menunggu verifikasi"
                        description="Semua bukti kerja tim sudah diverifikasi atau belum ada yang disubmit."
                    />
                )}

                {/* Pagination */}
                {verificationQueue.last_page > 1 && (
                    <nav
                        aria-label="Paginasi antrean verifikasi"
                        className="flex items-center justify-between gap-3"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={
                                verificationQueue.current_page <= 1
                            }
                            onClick={() =>
                                goTo({
                                    page: String(
                                        verificationQueue.current_page - 1,
                                    ),
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
                            Halaman {verificationQueue.current_page} dari{' '}
                            {verificationQueue.last_page}
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={
                                verificationQueue.current_page >=
                                verificationQueue.last_page
                            }
                            onClick={() =>
                                goTo({
                                    page: String(
                                        verificationQueue.current_page + 1,
                                    ),
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

                {/* Dialogs */}
                <VerificationDialog
                    item={selectedItem!}
                    open={proofDialogOpen}
                    onOpenChange={setProofDialogOpen}
                    onApprove={handleApprove}
                    onReject={handleReject}
                    processing={false}
                />
                <RejectDialog
                    item={selectedItem!}
                    open={rejectDialogOpen}
                    onOpenChange={setRejectDialogOpen}
                    onReject={handleReject}
                    processing={false}
                />
            </div>
        </>
    );
}

DailyWorkVerification.layout = (props: PageProps & InertiaConfig['sharedPageProps']) => ({
    breadcrumbs: [
        {
            title: 'Daily Work',
            href: props.activeTenant
                ? verificationIndex({ tenant: props.activeTenant }).url
                : undefined,
        },
        { title: 'Perlu Verifikasi' },
    ],
    title: 'Perlu Verifikasi',
    description: 'Bukti kerja karyawan yang menunggu verifikasi',
});
