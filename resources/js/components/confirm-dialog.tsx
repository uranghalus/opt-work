import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { AlertTriangle } from 'lucide-react';

interface ConfirmDialogProps {
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    onConfirm: () => void;
    processing?: boolean;
    danger?: boolean;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    /** The trigger button to render. Receives onClick to open the dialog. */
    children?: React.ReactNode;
}

export function ConfirmDialog({
    title,
    description,
    confirmLabel = 'Lanjutkan',
    cancelLabel = 'Batal',
    onConfirm,
    processing = false,
    danger = false,
    open,
    onOpenChange,
    children,
}: ConfirmDialogProps) {
    return (
        <>
            {children && (
                <Button
                    variant={danger ? 'destructive' : 'default'}
                    disabled={processing}
                    onClick={() => onOpenChange?.(true)}
                >
                    {children}
                </Button>
            )}
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        {danger && (
                            <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-danger-subtle text-danger">
                                <AlertTriangle className="h-6 w-6" aria-hidden />
                            </div>
                        )}
                        <DialogTitle className="text-lg font-semibold">{title}</DialogTitle>
                        <DialogDescription className="text-sm text-ink-muted">
                            {description}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button variant="outline" onClick={() => onOpenChange?.(false)} disabled={processing}>
                            {cancelLabel}
                        </Button>
                        <Button
                            variant={danger ? 'destructive' : 'default'}
                            onClick={() => {
                                onConfirm();
                                onOpenChange?.(false);
                            }}
                            disabled={processing}
                        >
                            {confirmLabel}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}