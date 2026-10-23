import { Form, Head, usePage, usePoll } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
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
} from 'lucide-react';
import WhatsAppController from '@/actions/App/Http/Controllers/Settings/WhatsAppController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
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

type Props = { configured: boolean; session: WaSession | null };
type FlashProps = InertiaConfig['sharedPageProps'] & {
    flash?: { success?: string | null; error?: string | null };
};

const WAITING_QR = ['starting', 'scan_qr'];

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
                    (data.qr
                        ? null
                        : 'QR belum tersedia. Pastikan sesi sudah dimulai.'),
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
                            </AlertDescription>
                        </Alert>
                    )}

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
            </div>
        </>
    );
}

WhatsApp.layout = {
    breadcrumbs: [{ title: 'Pengaturan WhatsApp', href: indexRoute() }],
};
