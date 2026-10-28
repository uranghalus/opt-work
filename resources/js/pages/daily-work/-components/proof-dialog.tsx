import { useForm, router } from '@inertiajs/react';
import { useRequiredTenant } from '@/lib/tenant';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';
import { useCallback, useEffect, useRef, useState } from 'react';
import { XIcon } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { submit as submitRoute } from '@/routes/daily-work';

const MAX_FILES = 3;
const MAX_SIZE = 2 * 1024 * 1024; // 2 MB
const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp'];

type Props = {
    selected: { id: string } | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function ProofDialog({ selected, open, onOpenChange }: Props) {
    const tenant = useRequiredTenant('daily-work.proof-dialog');
    const [files, setFiles] = useState<File[]>([]);
    const [previews, setPreviews] = useState<Record<string, string>>({});
    const urlsRef = useRef<string[]>([]);

    const revokeAll = useCallback(() => {
        urlsRef.current.forEach((url) => URL.revokeObjectURL(url));
        urlsRef.current = [];
    }, []);

    const form = useForm<{ notes: string; attachments: File[] }>({
        notes: '',
        attachments: [],
    });

    // Revoke object URLs when the dialog closes or unmounts.
    useEffect(() => {
        return () => revokeAll();
    }, [revokeAll]);

    const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFiles = Array.from(event.target.files ?? []);
        const next = [...files, ...selectedFiles];

        if (next.length > MAX_FILES) {
            toast.error('Maksimal 3 foto.');
            return;
        }

        for (const file of selectedFiles) {
            if (!ACCEPTED.includes(file.type)) {
                toast.error('Format JPG, PNG, atau WEBP.');
                return;
            }
            if (file.size > MAX_SIZE) {
                toast.error('Ukuran foto maksimal 2 MB.');
                return;
            }
        }

        const nextPreviews = { ...previews };
        for (const file of selectedFiles) {
            const url = URL.createObjectURL(file);
            urlsRef.current.push(url);
            nextPreviews[file.name + file.size + file.lastModified] = url;
        }

        setFiles(next);
        setPreviews(nextPreviews);
        event.target.value = '';
    };

    const removeFile = (file: File) => {
        const key = file.name + file.size + file.lastModified;
        const url = previews[key];
        if (url) URL.revokeObjectURL(url);
        setFiles((current) => current.filter((f) => f !== file));
        setPreviews((current) => {
            const next = { ...current };
            delete next[key];
            return next;
        });
    };

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();

        // Client-side validation (field errors via role="alert").
        let valid = true;
        if (!form.data.notes.trim()) {
            form.setError('notes', 'Catatan wajib diisi.');
            valid = false;
        } else if (form.data.notes.length > 2000) {
            form.setError('notes', 'Maksimal 2000 karakter.');
            valid = false;
        } else {
            form.clearErrors('notes');
        }

        if (files.length === 0) {
            form.setError('attachments', 'Minimal 1 foto wajib diunggah.');
            valid = false;
        } else {
            form.clearErrors('attachments');
        }

        if (!valid) return;

        const data = new FormData();
        data.append('proof_notes', form.data.notes);
        for (const file of files) {
            data.append('proof_attachments[]', file);
        }

        // Inertia multipart post to the submit route.
        router.post(submitRoute.url({ tenant, dailyWork: selected!.id }), data, {
            preserveScroll: true,
            onSuccess: () => {
                toast('Bukti dikirim — menunggu verifikasi HOD.');
                onOpenChange(false);
                form.reset();
                form.clearErrors();
                setFiles([]);
                setPreviews({});
                revokeAll();
            },
            onError: () => {
                // Field errors surface automatically via form.errors (server 422).
            },
            onFinish: () => {
                form.clearErrors();
            },
        });
    };

    if (!open || !selected) return null;

    const notesError = form.errors.notes;
    const attachmentsError = form.errors.attachments;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Kirim bukti pekerjaan</DialogTitle>
                    <DialogDescription>
                        Tambahkan catatan dan 1–3 foto hasil pekerjaan untuk
                        diverifikasi HOD/TL.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="proof-notes">Catatan</Label>
                        <Textarea
                            id="proof-notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            maxLength={2000}
                            className="min-h-[80px]"
                            aria-invalid={Boolean(notesError)}
                        />
                        <span className="text-xs text-ink-muted">
                            {form.data.notes.length} / 2000
                        </span>
                        {notesError && (
                            <p className="text-xs text-danger" role="alert">
                                {notesError}
                            </p>
                        )}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="proof-attachments">
                            Foto bukti (1–3)
                        </Label>
                        <Input
                            id="proof-attachments"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            onChange={handleFileChange}
                            className="sr-only"
                            aria-invalid={Boolean(attachmentsError)}
                        />
                        <button
                            type="button"
                            onClick={() =>
                                document
                                    .getElementById('proof-attachments')
                                    ?.click()
                            }
                            className="inline-flex items-center justify-center rounded-xl border-2 border-dashed border-border bg-surface-raised px-6 py-4 text-center text-sm text-ink-muted cursor-pointer hover:border-brand hover:bg-brand-subtle transition-colors"
                        >
                            <span>Pilih foto</span>
                        </button>
                        {attachmentsError && (
                            <p className="text-xs text-danger" role="alert">
                                {attachmentsError}
                            </p>
                        )}

                        {files.length > 0 && (
                            <ul
                                className="grid grid-cols-2 sm:grid-cols-3 gap-2"
                                role="list"
                                aria-label="Foto bukti terpilih"
                            >
                                {files.map((file) => {
                                    const key =
                                        file.name +
                                        file.size +
                                        file.lastModified;
                                    const url = previews[key];
                                    return (
                                        <li
                                            key={key}
                                            className="relative rounded-lg border border-border bg-surface-raised p-1"
                                        >
                                            {url ? (
                                                <img
                                                    src={url}
                                                    alt={`Bukti foto ${file.name}`}
                                                    className="h-16 w-full object-cover rounded-md"
                                                />
                                            ) : null}
                                            <button
                                                type="button"
                                                onClick={() => removeFile(file)}
                                                aria-label={`Hapus ${file.name}`}
                                                className="absolute top-1 right-1 rounded-md p-1 text-ink-muted hover:text-danger hover:bg-danger-subtle transition-colors"
                                            >
                                                <XIcon
                                                    aria-hidden="true"
                                                    className="size-4"
                                                />
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            className="min-h-11"
                            disabled={
                                form.processing ||
                                !form.data.notes.trim() ||
                                files.length === 0
                            }
                        >
                            {form.processing ? 'Mengirim…' : 'Kirim bukti'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
