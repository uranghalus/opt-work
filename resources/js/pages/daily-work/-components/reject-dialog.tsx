import { router } from '@inertiajs/react';
import { useRequiredTenant } from '@/lib/tenant';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';
import { useCallback, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { reject as rejectRoute } from '@/routes/daily-work';

type Props = {
    item: {
        id: string;
        title: string;
        employee: { nama_employee: string };
    };
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onReject: (id: string, note: string) => void;
    processing: boolean;
};

export function RejectDialog({
    item,
    open,
    onOpenChange,
    onReject,
    processing,
}: Props) {
    const tenant = useRequiredTenant('daily-work.reject-dialog');
    const [note, setNote] = useState('');

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!note.trim()) return;
        onReject(item.id, note.trim());
    };

    if (!open) return null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Kembalikan bukti kerja?</DialogTitle>
                    <DialogDescription>
                        Tuliskan alasan agar{' '}
                        {item.employee.nama_employee}
                        dapat memperbaiki bukti ini.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="flex flex-col gap-3 pt-2">
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="reject-note">Alasan pengembalian</Label>
                        <Textarea
                            id="reject-note"
                            value={note}
                            onChange={(event) =>
                                setNote(event.target.value)
                            }
                            maxLength={1000}
                            className="min-h-[80px]"
                            aria-required="true"
                            aria-invalid={!note.trim()}
                        />
                        <span className="text-xs text-ink-muted">
                            {note.length} / 1000
                        </span>
                        {!note.trim() && (
                            <p className="text-xs text-danger" role="alert">
                                Alasan wajib diisi.
                            </p>
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
                            type="submit"
                            variant="destructive"
                            disabled={processing || !note.trim()}
                            className="min-h-11"
                        >
                            {processing ? 'Memproses…' : 'Kembalikan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
