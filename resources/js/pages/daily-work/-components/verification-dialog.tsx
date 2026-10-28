import { router } from '@inertiajs/react';
import { useRequiredTenant } from '@/lib/tenant';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';
import { useCallback, useState } from 'react';
import { CalendarClock, MapPin, XIcon } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { RejectDialog } from './reject-dialog';
import { StatusChip } from './status';
import { attachment as attachmentRoute } from '@/routes/daily-work';

type Props = {
    item: {
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
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onApprove: (id: string) => void;
    onReject: (id: string, note: string) => void;
    processing: boolean;
};

export function VerificationDialog({
    item,
    open,
    onOpenChange,
    onApprove,
    onReject,
    processing,
}: Props) {
    const tenant = useRequiredTenant('daily-work.verification-dialog');
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [rejectOpen, setRejectOpen] = useState(false);

    const openAttachment = useCallback(
        (index: number) => {
            const url = attachmentRoute.url({
                tenant,
                dailyWork: item.id,
                index,
            });
            window.open(url, '_blank');
        },
        [tenant, item.id],
    );

    const handleApprove = () => {
        setConfirmOpen(false);
        onApprove(item.id);
    };

    const handleReject = (note: string) => {
        setRejectOpen(false);
        onReject(item.id, note);
    };

    if (!open) return null;

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{item.employee.nama_employee}</DialogTitle>
                        <DialogDescription>{item.title}</DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col gap-3 pt-2">
                        <div className="flex items-center gap-2">
                            <StatusChip
                                item={{
                                    status: item.status,
                                    verified_by_name: item.verification
                                        .verified_by_name,
                                }}
                            />
                            <span className="text-xs text-ink-muted">
                                Disubmit{' '}
                                {item.submitted_at
                                    ? new Date(item.submitted_at).toLocaleString(
                                          'id-ID',
                                      )
                                    : '—'}
                            </span>
                        </div>
                        {item.proof_notes && (
                            <div className="flex flex-col gap-1">
                                <label className="text-xs font-semibold text-ink-muted">
                                    Catatan karyawan
                                </label>
                                <p className="text-sm text-ink whitespace-pre-wrap break-words">
                                    {item.proof_notes}
                                </p>
                            </div>
                        )}
                        {item.location && (
                            <div className="flex items-center gap-2 text-xs text-ink-muted">
                                <MapPin className="size-3.5" aria-hidden />
                                {item.location}
                            </div>
                        )}
                        {item.proof_attachments.length > 0 && (
                            <div className="flex flex-col gap-2">
                                <label className="text-xs font-semibold text-ink-muted">
                                    Bukti foto
                                </label>
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
                                                    href={attachmentRoute.url({
                                                        tenant,
                                                        dailyWork: item.id,
                                                        index,
                                                    })}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="block h-full w-full"
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        openAttachment(index);
                                                    }}
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
                                            </li>
                                        ),
                                    )}
                                    {item.proof_attachments.length > 1 && (
                                        <li
                                            className="aspect-square overflow-hidden rounded-md border border-border bg-surface-raised flex items-center justify-center"
                                        >
                                            <span className="text-xs font-semibold text-ink-muted">
                                                +{item.proof_attachments.length - 1}
                                            </span>
                                        </li>
                                    )}
                                </ul>
                            </div>
                        )}
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => setRejectOpen(true)}
                            disabled={processing}
                            className="min-h-11"
                        >
                            Kembali
                        </Button>
                        <Button
                            type="button"
                            onClick={() => setConfirmOpen(true)}
                            disabled={processing}
                            className="min-h-11"
                        >
                            {processing ? 'Memproses…' : 'Tandai telah selesai'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                title="Tandai telah selesai?"
                description={
                    `Bukti kerja "${item.title}" oleh ${item.employee.nama_employee} akan ditandai telah selesai dan tidak dapat dikembalikan.`
                }
                confirmLabel="Ya, tandai selesai"
                cancelLabel="Batal"
                processing={processing}
                onConfirm={handleApprove}
            />

            <RejectDialog
                item={item}
                open={rejectOpen}
                onOpenChange={setRejectOpen}
                onReject={handleReject}
                processing={processing}
            />
        </>
    );
}
