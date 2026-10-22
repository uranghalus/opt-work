import { Form, Head, usePage, usePoll } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
<<<<<<< HEAD
    CheckCircle2,
    Download,
    Loader2,
    Pause,
    Play,
    Power,
    PowerOff,
    QrCode,
    RefreshCw,
    Smartphone,
    TriangleAlert,
    Zap,
=======
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
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
} from 'lucide-react';
import WhatsAppController from '@/actions/App/Http/Controllers/Settings/WhatsAppController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
<<<<<<< HEAD
=======
    Card,
    CardContent,
    CardDescription,
    CardTitle,
} from '@/components/ui/card';
import {
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
<<<<<<< HEAD
=======
import Heading from '@/components/heading';
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { qr as qrRoute, index as indexRoute } from '@/routes/whatsapp';
import { cn } from '@/lib/utils';
import type { InertiaConfig } from '@inertiajs/core';

type WaSession = {
    session_name: string;
    phone_number: string | null;
    status: string;
    status_label: string;
    last_connected_at: string | null;
    last_synced_at: string | null;
};
<<<<<<< HEAD
=======

// oxfmt-ignore
type Props = {
    configured: boolean;
    session: WaSession | null;
};
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda

type Props = { configured: boolean; session: WaSession | null };
type FlashProps = InertiaConfig['sharedPageProps'] & {
    flash?: { success?: string | null; error?: string | null };
};

const WAITING_QR = ['starting', 'scan_qr'];

<<<<<<< HEAD
const STATUS_META: Record<
    string,
    { label: string; icon: any; from: string; to: string }
> = {
    working: {
        label: 'Terhubung',
        icon: CheckCircle2,
        from: 'from-success/15',
        to: 'to-brand/10',
    },
    starting: {
        label: 'Menyiapkan',
        icon: Loader2,
        from: 'from-warning/15',
        to: 'to-warning-subtle/70',
    },
    scan_qr: {
        label: 'Menunggu Pindai QR',
        icon: QrCode,
        from: 'from-info/15',
        to: 'to-brand/10',
    },
    stopped: {
        label: 'Tidak Aktif',
        icon: PowerOff,
        from: 'from-ink-muted/10',
        to: 'to-surface-raised',
    },
    error: {
        label: 'Error',
        icon: TriangleAlert,
        from: 'from-danger/15',
        to: 'to-danger-subtle/40',
    },
    missing: {
        label: 'Belum Ada Sesi',
        icon: TriangleAlert,
        from: 'from-danger/15',
        to: 'to-danger-subtle/40',
    },
};

function formatWhen(iso: string | null) {
    if (!iso) return '-';
=======
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

>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function formatPhone(raw?: string | null) {
    if (!raw) return '-';
    const digits = raw.replace(/\D/g, '');
    if (digits.startsWith('62'))
        return (
            '+62 ' + digits.slice(2).replace(/(\d{3})(\d{4})(\d+)/, '$1 $2 $3')
        );
    if (digits.startsWith('0'))
        return (
            '+62 ' + digits.slice(1).replace(/(\d{3})(\d{4})(\d+)/, '$1 $2 $3')
        );
    return '+' + raw;
}

export default function WhatsApp({ configured, session }: Props) {
    const { flash, auth } = usePage<FlashProps>().props;
    const canManage = Boolean(
        auth?.isSuperAdmin || auth?.can?.['whatsapp.update'],
    );
    const waitingQr = session !== null && WAITING_QR.includes(session.status);
<<<<<<< HEAD
=======
    const tone = toneFor(session?.status);

    // Status sesi di-poll dari DB (diperbarui cron app:waha-sync-sessions
    // tiap menit) — tanpa webhook, sesuai ADR-0009.
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
    usePoll(15_000, { only: ['session'] });

    const [qrSrc, setQrSrc] = useState<string | null>(null);
    const [qrError, setQrError] = useState<string | null>(null);
    const [qrOpen, setQrOpen] = useState(false);
    const [qrLoading, setQrLoading] = useState(false);
    const [autoRefresh, setAutoRefresh] = useState(true);
    const qrOpenRef = useRef(false);
    qrOpenRef.current = qrOpen;

    const fetchQr = useCallback(async () => {
        setQrLoading(true);
        try {
            const res = await fetch(qrRoute.url());
            const data = (await res.json()) as {
                qr?: string | null;
                error?: string | null;
            };
            setQrSrc(data.qr ?? null);
            setQrError(
                data.error ??
<<<<<<< HEAD
                    (data.qr
                        ? null
                        : 'QR belum tersedia. Pastikan sesi sudah dimulai.'),
=======
                (data.qr
                    ? null
                    : 'QR belum tersedia — pastikan sesi sudah dimulai.'),
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
            );
        } catch {
            setQrSrc(null);
            setQrError('Gagal mengambil QR dari gateway.');
        } finally {
            setQrLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!qrOpen || !waitingQr) return;
        void fetchQr();
        if (!autoRefresh) return;
        const timer = setInterval(() => {
            if (qrOpenRef.current) void fetchQr();
        }, 5000);
        return () => clearInterval(timer);
    }, [qrOpen, waitingQr, autoRefresh, fetchQr]);

    const meta = session
        ? (STATUS_META[session.status] ?? STATUS_META.missing)
        : STATUS_META.missing;
    const Icon = meta.icon;
    const steps = [
        { label: 'Konfigurasi', done: configured },
        { label: 'Nomor', done: !!session?.phone_number },
        { label: 'Pairing', done: session?.status === 'working' },
    ];
    const progress = Math.round(
        (steps.filter((step) => step.done).length / steps.length) * 100,
    );

    const startDisabledReason = !configured
        ? 'WAHA belum dikonfigurasi di .env'
        : undefined;
    const qrDisabledReason = !configured
        ? 'WAHA belum dikonfigurasi di .env'
        : !session
          ? 'Mulai sesi terlebih dahulu untuk mendapatkan QR'
          : undefined;

    return (
        <>
            <Head title="Gateway WhatsApp" />

            <div className="relative">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
                >
                    <div className="absolute -top-32 -right-32 h-96 w-96 rounded-full bg-gradient-to-br from-brand/10 to-brand-strong/10 blur-3xl" />
                    <div className="absolute -bottom-32 -left-32 h-96 w-96 rounded-full bg-gradient-to-br from-info/10 to-brand/10 blur-3xl" />
                </div>

<<<<<<< HEAD
                <div className="space-y-6">
                    <div
                        className={`relative overflow-hidden rounded-4xl border border-border bg-gradient-to-br shadow-glass-deep ${meta.from} ${meta.to} p-px`}
                    >
                        <div className="relative rounded-[calc(1.75rem-1px)] bg-surface/70 p-6 backdrop-blur-xl sm:p-10">
                            <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                                <div className="flex items-center gap-5">
                                    <div className="relative">
                                        <div
                                            aria-hidden="true"
                                            className="absolute inset-0 rounded-3xl bg-gradient-to-br from-brand/30 to-brand-strong/30 blur-xl"
                                        />
                                        <div className="relative flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-br from-brand to-brand-strong text-on-brand shadow-glow-brand">
                                            <Icon
                                                aria-hidden="true"
                                                className={`h-8 w-8 ${session?.status === 'starting' ? 'animate-spin' : ''}`}
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <h2 className="text-2xl font-bold tracking-tight">
                                            Gateway WhatsApp
                                        </h2>
                                        <p className="text-sm text-ink-muted">
                                            Sesi pengirim WhatsApp unit bisnis
                                        </p>
                                    </div>
                                </div>

                                <div
                                    role="status"
                                    aria-live="polite"
                                    aria-atomic="true"
                                >
                                    <Badge className="border-border bg-surface-raised/70 px-3 py-1.5 text-sm font-medium text-ink backdrop-blur">
                                        <Icon
                                            aria-hidden="true"
                                            className="h-3.5 w-3.5"
                                        />
                                        {meta.label}
                                    </Badge>
                                </div>
                            </div>

                            <div className="mt-8">
                                <div className="mb-3 flex items-center justify-between text-xs font-medium tracking-wide text-ink-muted uppercase">
                                    <span>Progres aktivasi</span>
                                    <span className="font-mono">
                                        {progress}%
                                    </span>
                                </div>
                                <div
                                    role="progressbar"
                                    aria-label="Progres aktivasi gateway WhatsApp"
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-valuenow={progress}
                                    className="h-2 w-full overflow-hidden rounded-full bg-surface-raised"
                                >
                                    <div
                                        className="h-full rounded-full bg-gradient-to-r from-brand to-brand-strong transition-all duration-500"
                                        style={{ width: `${progress}%` }}
                                    />
                                </div>

                                <ol className="mt-4 grid gap-3 sm:grid-cols-3">
                                    {steps.map((step, index) => (
                                        <li
                                            key={step.label}
                                            className={`rounded-3xl border p-3 ${
                                                step.done
                                                    ? 'border-success/30 bg-success-subtle/60'
                                                    : 'border-border bg-surface-raised/60'
                                            }`}
                                        >
                                            <div className="flex items-center gap-2">
                                                <div
                                                    aria-hidden="true"
                                                    className={`flex h-7 w-7 items-center justify-center rounded-xl text-xs font-bold ${
                                                        step.done
                                                            ? 'bg-success text-on-brand'
                                                            : 'border border-border bg-surface text-ink-muted'
                                                    }`}
                                                >
                                                    {index + 1}
                                                </div>
                                                <span
                                                    className={`text-sm ${
                                                        step.done
                                                            ? 'font-medium text-ink'
                                                            : 'text-ink-muted'
                                                    }`}
                                                >
                                                    {step.label}
                                                    {step.done
                                                        ? ' · selesai'
                                                        : ' · belum'}
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            </div>

                            {session && (
                                <dl className="mt-8 grid gap-3 sm:grid-cols-3">
                                    {[
                                        {
                                            k: 'Nomor',
                                            v: formatPhone(
                                                session.phone_number,
                                            ),
                                        },
                                        {
                                            k: 'Terakhir tersambung',
                                            v: formatWhen(
                                                session.last_connected_at,
                                            ),
                                        },
                                        {
                                            k: 'Disinkronkan',
                                            v: formatWhen(
                                                session.last_synced_at,
                                            ),
                                        },
                                    ].map((item) => (
                                        <div
                                            key={item.k}
                                            className="rounded-3xl border border-border bg-surface-raised/60 p-4 backdrop-blur"
                                        >
                                            <dt className="text-[11px] tracking-wider text-ink-muted uppercase">
                                                {item.k}
                                            </dt>
                                            <dd className="mt-1 font-mono font-semibold break-words">
                                                {item.v}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </div>
                    </div>

                    {flash?.success && (
                        <Alert role="status">
                            <AlertDescription>{flash.success}</AlertDescription>
                        </Alert>
                    )}
                    {flash?.error && (
                        <Alert variant="destructive" role="alert">
                            <AlertDescription>{flash.error}</AlertDescription>
                        </Alert>
                    )}
                    {!configured && (
                        <Alert variant="destructive" role="alert">
                            <AlertDescription>
                                WAHA belum dikonfigurasi{' '}
                                <code className="rounded bg-surface-raised px-1 py-0.5 text-xs">
                                    WAHA_URL
                                </code>{' '}
                                di <code className="text-xs">.env</code>
=======
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
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
                            </AlertDescription>
                        </Alert>
                    )}

<<<<<<< HEAD
                    {canManage && (
                        <div className="grid gap-6 lg:grid-cols-2">
                            <div className="relative overflow-hidden rounded-3xl border border-border bg-surface/70 p-6 shadow-glass backdrop-blur-xl">
                                <div
                                    aria-hidden="true"
                                    className="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-gradient-to-br from-brand/15 to-brand-strong/15 blur-2xl"
                                />
                                <div className="relative">
                                    <div className="mb-4 flex items-center gap-2">
                                        <Smartphone
                                            aria-hidden="true"
                                            className="h-4 w-4 text-brand"
                                        />
                                        <h3 className="font-semibold">
                                            Nomor Pengirim
                                        </h3>
                                    </div>

                                    <Form
                                        {...WhatsAppController.store.form()}
                                        options={{ preserveScroll: true }}
                                        resetOnSuccess
                                    >
                                        {({ errors, processing }) => (
                                            <div className="space-y-4">
                                                <div>
                                                    <Label htmlFor="phone_number">
                                                        Nomor WhatsApp
                                                    </Label>
                                                    <Input
                                                        id="phone_number"
                                                        name="phone_number"
                                                        key={`phone-${session?.phone_number ?? 'empty'}`}
                                                        defaultValue={
                                                            session?.phone_number ??
                                                            ''
                                                        }
                                                        placeholder="+62 812 3456 7890"
                                                        autoComplete="tel"
                                                        inputMode="tel"
                                                        className="mt-2 h-12 w-full rounded-2xl border-border bg-surface-raised/60 backdrop-blur"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.phone_number
                                                        }
                                                    />
                                                    {!session && (
                                                        <p className="mt-2 text-xs text-ink-muted">
                                                            Nomor dicatat
                                                            sebagai rencana;
                                                            nomor asli terisi
                                                            setelah pairing.
                                                        </p>
                                                    )}
                                                </div>
                                                <Button
                                                    disabled={processing}
                                                    className="h-12 w-full rounded-2xl bg-gradient-to-r from-brand to-brand-strong text-on-brand shadow-glow-brand transition-transform hover:scale-[1.01] active:scale-[0.99]"
                                                >
                                                    {processing ? (
                                                        <Loader2
                                                            aria-hidden="true"
                                                            className="mr-2 h-4 w-4 animate-spin"
                                                        />
                                                    ) : (
                                                        <Zap
                                                            aria-hidden="true"
                                                            className="mr-2 h-4 w-4"
                                                        />
                                                    )}
                                                    {processing
                                                        ? 'Menyimpan...'
                                                        : session
                                                          ? 'Simpan nomor'
                                                          : 'Daftarkan nomor'}
                                                </Button>
                                            </div>
                                        )}
                                    </Form>
                                </div>
                            </div>

                            <div className="relative overflow-hidden rounded-3xl border border-border bg-surface/70 p-6 shadow-glass backdrop-blur-xl">
                                <div
                                    aria-hidden="true"
                                    className="absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-gradient-to-br from-info/15 to-brand/15 blur-2xl"
                                />
                                <div className="relative">
                                    <h3 className="mb-4 font-semibold">
                                        Kontrol Sesi
                                    </h3>
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <Form
                                            {...WhatsAppController.start.form()}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    variant="secondary"
                                                    disabled={
                                                        processing ||
                                                        !configured
                                                    }
                                                    title={startDisabledReason}
                                                    className="h-12 w-full rounded-2xl border-border bg-surface-raised/80 backdrop-blur"
                                                >
                                                    {processing ? (
                                                        <Loader2
                                                            aria-hidden="true"
                                                            className="mr-2 h-4 w-4 animate-spin"
                                                        />
                                                    ) : (
                                                        <Power
                                                            aria-hidden="true"
                                                            className="mr-2 h-4 w-4"
                                                        />
                                                    )}
                                                    {processing
                                                        ? 'Memulai...'
                                                        : 'Mulai sesi'}
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
                                                    disabled={
                                                        processing ||
                                                        !configured
                                                    }
                                                    title={startDisabledReason}
                                                    className="h-12 w-full rounded-2xl"
                                                >
                                                    {processing ? (
                                                        <Loader2
                                                            aria-hidden="true"
                                                            className="mr-2 h-4 w-4 animate-spin"
                                                        />
                                                    ) : (
                                                        <PowerOff
                                                            aria-hidden="true"
                                                            className="mr-2 h-4 w-4"
                                                        />
                                                    )}
                                                    {processing
                                                        ? 'Memutuskan...'
                                                        : 'Putuskan sesi'}
                                                </Button>
                                            )}
                                        </Form>
                                        <Button
                                            variant="outline"
                                            disabled={!configured || !session}
                                            title={qrDisabledReason}
                                            onClick={() => {
                                                setQrOpen(true);
                                                if (!qrSrc) void fetchQr();
                                            }}
                                            className="h-12 w-full rounded-2xl border-border bg-surface-raised/80 backdrop-blur"
                                        >
                                            <QrCode
                                                aria-hidden="true"
                                                className="mr-2 h-4 w-4"
                                            />
                                            Tampilkan QR
                                        </Button>
                                    </div>
                                    <p className="mt-4 text-xs text-ink-muted">
                                        {qrDisabledReason
                                            ? `QR nonaktif: ${qrDisabledReason}.`
                                            : 'Buka QR lalu pindai dari WhatsApp → Perangkat tertaut → Tautkan perangkat.'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    <Dialog open={qrOpen} onOpenChange={setQrOpen}>
                        <DialogContent className="max-w-lg rounded-3xl border-border bg-surface/85 backdrop-blur-2xl">
                            <DialogHeader>
                                <DialogTitle className="text-xl">
                                    Pindai QR WhatsApp
                                </DialogTitle>
                                <DialogDescription>
                                    Buka WhatsApp → Perangkat tertaut → Tautkan
                                    perangkat
                                </DialogDescription>
                            </DialogHeader>

                            <div className="flex flex-col items-center gap-5 py-4">
                                {qrLoading && !qrSrc && (
                                    <Loader2
                                        aria-hidden="true"
                                        className="h-8 w-8 animate-spin text-ink-muted"
                                    />
                                )}
                                {qrError && !qrSrc && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {qrError}
                                    </p>
                                )}
                                {qrSrc && (
                                    <div className="relative rounded-3xl bg-surface p-4 shadow-glass-deep">
                                        <img
                                            src={qrSrc}
                                            alt="Kode QR untuk menautkan sesi WhatsApp"
                                            className="h-72 w-72"
                                        />
                                        <div className="pointer-events-none absolute inset-0 rounded-3xl ring-1 ring-border" />
                                    </div>
                                )}

                                <div className="flex flex-wrap items-center justify-center gap-2">
                                    <Button
                                        variant="ghost"
                                        onClick={() => void fetchQr()}
                                        disabled={qrLoading}
                                    >
                                        <RefreshCw
                                            aria-hidden="true"
                                            className="mr-2 h-4 w-4"
                                        />
                                        Muat ulang
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            setAutoRefresh((value) => !value)
                                        }
                                        aria-pressed={!autoRefresh}
                                        className={cn(
                                            !autoRefresh &&
                                                'border-brand/30 text-brand',
                                        )}
                                    >
                                        {autoRefresh ? (
                                            <Pause
                                                aria-hidden="true"
                                                className="mr-2 h-4 w-4"
                                            />
                                        ) : (
                                            <Play
                                                aria-hidden="true"
                                                className="mr-2 h-4 w-4"
                                            />
                                        )}
                                        {autoRefresh
                                            ? 'Jeda otomatis'
                                            : 'Lanjut otomatis'}
                                    </Button>
                                    {qrSrc && (
                                        <Button
                                            variant="ghost"
                                            onClick={() => {
                                                const link =
                                                    document.createElement('a');
                                                link.href = qrSrc;
                                                link.download =
                                                    'whatsapp-qr.png';
                                                link.click();
                                            }}
                                        >
                                            <Download
                                                aria-hidden="true"
                                                className="mr-2 h-4 w-4"
                                            />
                                            Unduh QR
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>
=======
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
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
            </div>
        </>
    );
}

WhatsApp.layout = {
    breadcrumbs: [{ title: 'Pengaturan WhatsApp', href: indexRoute() }],
};
