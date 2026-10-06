import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/tenants';

/**
 * Branch deletion confirm.
 *
 * A branch id is the primary key and every tenant-scoped row points at it, so the
 * confirm names the branch and states the consequence rather than a bare
 * "are you sure". Archiving (`is_active`) is the safer path and is what the UI
 * offers elsewhere.
 */
export function DeleteTenant({
    id,
    name,
}: {
    id: string;
    name: string;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="text-destructive hover:text-destructive"
                    aria-label={`Hapus cabang ${name}`}
                    data-test="delete-tenant-button"
                >
                    <Trash2 aria-hidden className="size-3.5" />
                    Hapus
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hapus cabang {name}?</DialogTitle>
                    <DialogDescription>
                        Cabang <span className="font-mono">{id}</span>{' '}
                        beserta seluruh data cabangnya akan dihapus permanen dan
                        URL <span className="font-mono">/{id}/…</span> tidak
                        akan bisa diakses lagi. Bila hanya ingin menonaktifkan
                        cabang, ubah statusnya menjadi Nonaktif.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="ghost">Batal</Button>
                    </DialogClose>
                    <DialogClose asChild>
                        <Button
                            variant="destructive"
                            data-test="confirm-delete-tenant-button"
                            onClick={() =>
                                router.delete(destroy(id), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Hapus cabang
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}