import { Form, Head, usePage, usePoll } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    AlertTriangle,
    CheckCircle2,
    MessageCircle,
    Phone,
    Play,
    QrCode,
    RefreshCw,
    ShieldCheck,
    Signal,
    Square,
} from 'lucide-react';
import WhatsAppController from '@/actions/App/Http/Controllers/Settings/WhatsAppController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import Heading from '@/components/heading';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { qr as qrRoute, index as indexRoute } from '@/routes/whatsapp';
import type { InertiaConfig } from '@inertiajs/core';

// oxfmt-ignore
type WaSession = {
    session_name: string;
    phone_number: string | null;
    status: string;
    status_label: string;
    last_connected_at: string | null;
    last_synced_at: string | null;
};

// oxfmt-ignore
type Props = {
    configured: boolean;
    session: WaSession | null;
};

type FlashProps = InertiaConfig['sharedPageProps'] & {
    flash?: { success?: string | null; error?: string | null };
};

/** Status yang sedang menunggu pairing QR (polling gambar otomatis). */
const WAITING_QR = ['starting', 'scan_qr'];

type StatusTone = {
    dot: string;
    soft: string;
};

function toneFor(status?: string): StatusTone {
    if (status === 'working') {
        return {
            dot: 'bg-success',
            soft: 'bg-success-subtle text-success ring-success/25',
        };
    }

    if (status === 'starting' || status === 'scan_qr') {
        return {
            dot: 'bg-warning',
            soft: 'bg-warning-subtle text-warning ring-warning/25',
        };
    }

    if (status === 'error' || status === 'missing') {
        return {
            dot: 'bg-danger',
            soft: 'bg-danger-subtle text-danger ring-danger/25',
        };
    }

    return {
        dot: 'bg-muted-foreground',
        soft: 'bg-muted text-muted-foreground ring-border',
    };
}

function StatusDot({ tone }: { tone: string }) {
    return (
        <span className="relative flex size-3 items-center justify-center">
            <span
                aria-hidden="true"
                className={cn(
                    'absolute inline-flex size-full animate-ping rounded-full opacity-60',
                    tone,
                )}
            />
            <span
                aria-hidden="true"
                className={cn(
                    'relative inline-flex size-2.5 rounded-full ring-2 ring-white',
                    tone,
                )}
            />
        </span>
    );
}

function InfoRow({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-center justify-between gap-4 px-4 py-3">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-right text-sm font-medium text-foreground">
                {value}
            </dd>
        </div>
    );
}

function formatWhen(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export default function WhatsApp(props: Props) {
    const { flash, auth } = usePage<FlashProps>().props;

    const canManage = Boolean(
        auth?.isSuperAdmin || auth?.can?.['whatsapp.update'],
    );

    const session = props.session;
    const waitingQr = session !== null && WAITING_QR.includes(session.status);
    const tone = toneFor(session?.status);

    // Status sesi di-poll dari DB (diperbarui cron app:waha-sync-sessions
    // tiap menit) — tanpa webhook, sesuai ADR-0009.
    usePoll(15_000, { only: ['session'] });

    const [qrSrc, setQrSrc] = useState<string | null>(null);
    const [qrError, setQrError] = useState<string | null>(null);
    const [qrOpen, setQrOpen] = useState(false);
    const [qrLoading, setQrLoading] = useState(false);
    const qrVisibleRef = useRef(false);
    qrVisibleRef.current = qrOpen;

    const fetchQr = useCallback(async () => {
        setQrLoading(true);

        try {
            const response = await fetch(qrRoute.url());

            const data = (await response.json()) as {
                qr?: string | null;
                error?: string | null;
            };

            setQrSrc(data.qr ?? null);
            setQrError(
                data.error ??
                (data.qr
                    ? null
                    : 'QR belum tersedia — pastikan sesi sudah dimulai.'),
            );
        } catch {
            setQrSrc(null);
            setQrError('Gagal mengambil QR dari gateway.');
        } finally {
            setQrLoading(false);
        }
    }, []);

    // QR pairing kadaluarsa cepat: selama panel terbuka dan sesi masih
    // menunggu pairing, gambar di-refresh tiap 5 detik.
    useEffect(() => {
        if (!qrOpen || !waitingQr) {
            return;
        }

        void fetchQr();

        const timer = setInterval(() => {
            if (qrVisibleRef.current) {
                void fetchQr();
            }
        }, 5_000);

        return () => clearInterval(timer);
    }, [qrOpen, waitingQr, fetchQr]);

    return (
        <>
            <Head title="Pengaturan WhatsApp" />

            <h1 className="sr-only">Pengaturan WhatsApp</h1>

            <div className="relative mx-auto w-full max-w-6xl space-y-8 rounded-[2rem] bg-gradient-to-br from-emerald-100 via-white to-indigo-200 p-4 shadow-xl shadow-emerald-500/10 sm:p-6 lg:p-8">
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Gateway WhatsApp"
                        description="Sesi dan nomor pengirim WhatsApp unit bisnis ini (per tenant)"
                    />

                    {flash?.success && (
                        <Alert className="border-success/30 bg-success-subtle text-success [&>svg]:text-success">
                            <CheckCircle2 className="size-4" />
                            <AlertDescription className="font-medium text-success">
                                {flash.success}
                            </AlertDescription>
                        </Alert>
                    )}

                    {flash?.error && (
                        <Alert className="border-destructive/30 bg-destructive/10 text-destructive [&>svg]:text-destructive">
                            <AlertTriangle className="size-4" />
                            <AlertDescription className="font-medium text-destructive">
                                {flash.error}
                            </AlertDescription>
                        </Alert>
                    )}

                    {!props.configured && (
                        <Alert className="border-destructive/30 bg-destructive/10 text-destructive [&>svg]:text-destructive">
                            <AlertTriangle className="text-destructive" />
                            <AlertDescription className="font-medium text-destructive">
                                WAHA belum dikonfigurasi (WAHA_URL kosong di
                                .env). Pengiriman WhatsApp dinonaktifkan sampai
                                gateway diisi.
                            </AlertDescription>
                        </Alert>
                    )}
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <Card className="w-full overflow-hidden rounded-3xl border bg-card shadow-xl">
                        <div className="bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 p-6 text-white">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <span className="grid size-10 place-items-center rounded-xl bg-white/20 text-white ring-1 ring-white/30">
                                    <MessageCircle className="size-5" />
                                </span>
                                <div>
                                    <CardTitle className="text-base text-white">
                                        Status sesi
                                    </CardTitle>
                                    <CardDescription className="text-white/80">
                                        {session
                                            ? session.session_name
                                            : 'Belum ada sesi aktif'}
                                    </CardDescription>
                                </div>
                            </div>

                            <Badge
                                variant="secondary"
                                className="gap-2 border-0 bg-white/20 px-3 py-1 text-white ring-1 ring-white/30"
                            >
                                <StatusDot tone={tone.dot} />
                                {session
                                    ? session.status_label
                                    : 'Tidak ada sesi'}
                            </Badge>
                        </div>
                        </div>

                        <CardContent className="space-y-5">
                            {waitingQr && (
                                <div className="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-subtle p-4 text-sm text-warning">
                                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-warning" />
                                    <p>
                                        Menunggu pemindaian QR. Buka WhatsApp →
                                        Perangkat tertaut → Tautkan perangkat.
                                    </p>
                                </div>
                            )}

                            {session ? (
                                <dl className="divide-y rounded-xl border bg-muted/30">
                                    <InfoRow
                                        label="Nomor"
                                        value={session.phone_number ?? '-'}
                                    />
                                    <InfoRow
                                        label="Terakhir tersambung"
                                        value={formatWhen(
                                            session.last_connected_at,
                                        )}
                                    />
                                    <InfoRow
                                        label="Disinkronkan"
                                        value={formatWhen(
                                            session.last_synced_at,
                                        )}
                                    />
                                    <InfoRow
                                        label="Kanal"
                                        value="WhatsApp Gateway"
                                    />
                                </dl>
                            ) : (
                                <div className="rounded-xl border border-dashed border-cyan-300 bg-gradient-to-br from-cyan-50 to-indigo-50 p-5 text-center">
                                    <span className="mx-auto grid size-10 place-items-center rounded-lg bg-gradient-to-br from-cyan-500 to-indigo-600 text-white shadow-lg shadow-cyan-500/20">
                                        <Signal className="size-5" />
                                    </span>
                                    <p className="mt-3 text-sm font-medium text-foreground">
                                        Sesi WhatsApp belum aktif
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Daftarkan nomor, mulai sesi, lalu pindai
                                        QR untuk menghubungkan gateway.
                                    </p>
                                </div>
                            )}

                            {canManage && (
                                <div className="flex flex-wrap gap-3 border-t pt-5">
                                    <Form
                                        {...WhatsAppController.start.form()}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                className="bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-lg shadow-emerald-500/20 transition-transform hover:-translate-y-0.5 hover:from-emerald-500 hover:to-teal-500"
                                                disabled={
                                                    processing ||
                                                    !props.configured
                                                }
                                                data-test="start-wa-session-button"
                                            >
                                                <Play className="size-4" />
                                                Mulai sesi
                                            </Button>
                                        )}
                                    </Form>

                                    <Form
                                        {...WhatsAppController.stop.form()}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                variant="destructive"
                                                className="bg-gradient-to-r from-rose-600 to-red-600 text-white shadow-lg shadow-rose-500/20 transition-transform hover:-translate-y-0.5 hover:from-rose-500 hover:to-red-500"
                                                disabled={
                                                    processing ||
                                                    !props.configured
                                                }
                                                data-test="stop-wa-session-button"
                                            >
                                                <Square className="size-4" />
                                                Putuskan sesi
                                            </Button>
                                        )}
                                    </Form>

                                    <Button
                                        variant="outline"
                                        className="border-cyan-300 bg-cyan-50 text-cyan-800 transition-colors hover:bg-cyan-100 hover:text-cyan-900"
                                        disabled={
                                            !props.configured || !session
                                        }
                                        onClick={() => {
                                            if (qrOpen) {
                                                setQrOpen(false);
                                                return;
                                            }

                                            setQrOpen(true);
                                            void fetchQr();
                                        }}
                                        data-test="show-wa-qr-button"
                                    >
                                        <QrCode className="size-4" />
                                        {qrOpen ? 'Sembunyikan QR' : 'Tampilkan QR'}
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {canManage && (
                        <Card className="w-full overflow-hidden rounded-3xl border bg-card shadow-xl">
                            <div className="bg-gradient-to-r from-indigo-600 via-violet-600 to-fuchsia-600 p-6 text-white">
                            <div className="flex items-center gap-3">
                                <span className="grid size-10 place-items-center rounded-xl bg-white/20 text-white ring-1 ring-white/30">
                                    <Phone className="size-5" />
                                </span>
                                <div>
                                    <CardTitle className="text-base text-white">
                                        Nomor pengirim
                                    </CardTitle>
                                    <CardDescription className="text-white/80">
                                        Rencana nomor unit bisnis ini
                                    </CardDescription>
                                </div>
                            </div>
                            </div>

                            <CardContent className="space-y-4">
                                <Form
                                    {...WhatsAppController.store.form()}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="space-y-4"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="phone_number">
                                                    {session
                                                        ? 'Ganti nomor (lalu pasang ulang QR)'
                                                        : 'Nomor WhatsApp unit bisnis'}
                                                </Label>

                                                <Input
                                                    id="phone_number"
                                                    name="phone_number"
                                                    key={`phone-${session?.phone_number ?? 'empty'}`}
                                                    defaultValue={
                                                        session?.phone_number ??
                                                        ''
                                                    }
                                                    placeholder="081234567890"
                                                    autoComplete="tel"
                                                    className="h-10 rounded-lg"
                                                />

                                                <InputError
                                                    message={errors.phone_number}
                                                />

                                                {!session && (
                                                    <p className="text-xs text-muted-foreground">
                                                        Nomor dicatat sebagai
                                                        rencana pasangan; nomor
                                                        asli terisi otomatis
                                                        setelah pairing
                                                        (sinkronisasi).
                                                    </p>
                                                )}
                                            </div>

                                            <Button
                                                className="h-10 w-full bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-lg shadow-indigo-500/20 transition-transform hover:-translate-y-0.5 hover:from-indigo-500 hover:to-violet-500"
                                                disabled={processing}
                                                data-test="save-wa-number-button"
                                            >
                                                <ShieldCheck className="size-4" />
                                                {session
                                                    ? 'Simpan nomor'
                                                    : 'Daftarkan nomor'}
                                            </Button>
                                        </>
                                    )}
                                </Form>

                                <div className="rounded-xl border bg-muted/30 p-4 text-xs leading-relaxed text-muted-foreground">
                                    <p className="text-sm font-medium text-foreground">
                                        Tips
                                    </p>
                                    <p className="mt-1">
                                        Satu nomor hanya bisa aktif pada satu
                                        sesi gateway per tenant. Jika WhatsApp
                                        menampilkan perangkat tertaut, putuskan
                                        sesi lama terlebih dahulu.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Dialog open={qrOpen} onOpenChange={setQrOpen}>
                    <DialogContent
                        className="sm:max-w-md"
                        data-test="wa-qr-panel"
                    >
                        <div className="relative space-y-5">
                            <div
                                aria-hidden="true"
                                className="absolute inset-x-0 top-0 h-1 rounded-full bg-gradient-to-r from-emerald-500 via-cyan-500 to-indigo-500"
                            />
                            <DialogHeader>
                                <DialogTitle>
                                    Pindai QR dengan WhatsApp
                                </DialogTitle>
                                <DialogDescription>
                                    WhatsApp → Perangkat tertaut →
                                    Tautkan perangkat. QR diperbarui
                                    otomatis selama sesi menunggu pairing.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="flex flex-wrap items-center gap-2 text-xs font-medium text-muted-foreground">
                                <span className="flex items-center gap-1.5">
                                    <span className="grid size-5 place-items-center rounded-full bg-emerald-100 text-emerald-700">
                                        1
                                    </span>
                                    Mulai
                                </span>
                                <span aria-hidden="true">→</span>
                                <span className="flex items-center gap-1.5">
                                    <span className="grid size-5 place-items-center rounded-full bg-cyan-100 text-cyan-700">
                                        2
                                    </span>
                                    Tampilkan QR
                                </span>
                                <span aria-hidden="true">→</span>
                                <span className="flex items-center gap-1.5">
                                    <span className="grid size-5 place-items-center rounded-full bg-indigo-100 text-indigo-700">
                                        3
                                    </span>
                                    Pindai
                                </span>
                            </div>

                            {qrLoading && !qrSrc && (
                                <div className="flex justify-center py-4">
                                    <Skeleton className="h-64 w-64 rounded-lg" />
                                </div>
                            )}

                            {qrError && !qrSrc && (
                                <p className="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                                    {qrError}
                                </p>
                            )}

                            {qrSrc && (
                                <div className="flex justify-center">
                                    <div className="relative animate-[pulse_2.5s_ease-in-out_infinite] rounded-2xl bg-gradient-to-r from-emerald-500 via-cyan-500 to-indigo-600 p-[3px] shadow-lg shadow-cyan-500/20">
                                        <img
                                            src={qrSrc}
                                            alt="QR pairing WhatsApp"
                                            className="h-64 w-64 rounded-xl bg-white p-3"
                                            data-test="wa-qr-image"
                                        />
                                    </div>
                                </div>
                            )}

                            <div className="flex justify-end border-t pt-4">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => void fetchQr()}
                                    disabled={qrLoading}
                                    className="gap-2 text-cyan-700 hover:bg-cyan-50 hover:text-cyan-800"
                                >
                                    <RefreshCw
                                        className={cn(
                                            'size-4',
                                            qrLoading && 'animate-spin',
                                        )}
                                    />
                                    Muat ulang QR
                                </Button>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

WhatsApp.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan WhatsApp',
            href: indexRoute(),
        },
    ],
};
