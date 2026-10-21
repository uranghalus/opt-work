import { Form, Head, usePage, usePoll } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import WhatsAppController from '@/actions/App/Http/Controllers/Settings/WhatsAppController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Heading from '@/components/heading';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
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
} ;

// oxfmt-ignore
type Props = {
    configured: boolean;
    session: WaSession | null;
} ;

type FlashProps = InertiaConfig['sharedPageProps'] & {
    flash?: { success?: string | null; error?: string | null };
};

/** Status yang sedang menunggu pairing QR (polling gambar otomatis). */
const WAITING_QR = ['starting', 'scan_qr'];

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    working: 'default',
    starting: 'secondary',
    scan_qr: 'secondary',
    stopping: 'secondary',
    stopped: 'outline',
    error: 'destructive',
    missing: 'destructive',
};

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

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Gateway WhatsApp"
                    description="Sesi dan nomor pengirim WhatsApp unit bisnis ini (per tenant)"
                />

                {flash?.success && (
                    <Alert>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {flash?.error && (
                    <Alert variant="destructive">
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                {!props.configured && (
                    <Alert variant="destructive">
                        <AlertDescription>
                            WAHA belum dikonfigurasi (WAHA_URL kosong di .env).
                            Pengiriman WhatsApp dinonaktifkan sampai gateway
                            diisi.
                        </AlertDescription>
                    </Alert>
                )}

                <section className="space-y-4 rounded-lg border border-border-strong bg-card p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="space-y-1">
                            <p className="text-sm font-medium">
                                Sesi pengirim
                            </p>

                            <p className="text-sm text-ink-muted">
                                {session
                                    ? session.session_name
                                    : 'Belum terdaftar'}
                            </p>
                        </div>

                        <Badge
                            variant={
                                session
                                    ? (STATUS_VARIANT[session.status] ??
                                      'outline')
                                    : 'outline'
                            }
                        >
                            {session ? session.status_label : 'Tidak ada sesi'}
                        </Badge>
                    </div>

                    {session && (
                        <dl className="grid gap-1 text-sm text-ink-muted">
                            <div className="flex gap-2">
                                <dt className="font-medium">Nomor:</dt>
                                <dd>{session.phone_number ?? '-'}</dd>
                            </div>

                            <div className="flex gap-2">
                                <dt className="font-medium">
                                    Terakhir tersambung:
                                </dt>
                                <dd>{formatWhen(session.last_connected_at)}</dd>
                            </div>

                            <div className="flex gap-2">
                                <dt className="font-medium">
                                    Disinkronkan:
                                </dt>
                                <dd>{formatWhen(session.last_synced_at)}</dd>
                            </div>
                        </dl>
                    )}
                </section>

                {canManage && (
                    <section className="space-y-6">
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
                                                session?.phone_number ?? ''
                                            }
                                            placeholder="081234567890"
                                            autoComplete="tel"
                                        />

                                        <InputError
                                            message={errors.phone_number}
                                        />

                                        {!session && (
                                            <p className="text-xs text-ink-muted">
                                                Nomor dicatat sebagai rencana
                                                pasangan; nomor asli terisi
                                                otomatis setelah pairing
                                                (sinkronisasi).
                                            </p>
                                        )}
                                    </div>

                                    <Button
                                        disabled={processing}
                                        data-test="save-wa-number-button"
                                    >
                                        {session
                                            ? 'Simpan nomor'
                                            : 'Daftarkan nomor'}
                                    </Button>
                                </>
                            )}
                        </Form>

                        <div className="flex flex-wrap gap-3">
                            <Form
                                {...WhatsAppController.start.form()}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        variant="secondary"
                                        disabled={
                                            processing || !props.configured
                                        }
                                        data-test="start-wa-session-button"
                                    >
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
                                        disabled={
                                            processing || !props.configured
                                        }
                                        data-test="stop-wa-session-button"
                                    >
                                        Putuskan sesi
                                    </Button>
                                )}
                            </Form>

                            <Button
                                variant="outline"
                                disabled={!props.configured || !session}
                                onClick={() => {
                                    setQrOpen((open) => !open);

                                    if (!qrOpen) {
                                        void fetchQr();
                                    }
                                }}
                                data-test="show-wa-qr-button"
                            >
                                {qrOpen ? 'Sembunyikan QR' : 'Tampilkan QR'}
                            </Button>
                        </div>
                    </section>
                )}

                {canManage && qrOpen && (
                    <section
                        className="space-y-4 rounded-lg border border-border-strong bg-card p-4"
                        data-test="wa-qr-panel"
                    >
                        <div className="space-y-1">
                            <p className="text-sm font-medium">
                                Pindai QR dengan WhatsApp
                            </p>

                            <p className="text-sm text-ink-muted">
                                WhatsApp → Perangkat tertaut → Tautkan
                                perangkat. QR diperbarui otomatis selama sesi
                                menunggu pairing.
                            </p>
                        </div>

                        {qrLoading && !qrSrc && (
                            <p className="text-sm text-ink-muted">
                                Mengambil QR…
                            </p>
                        )}

                        {qrError && !qrSrc && (
                            <p className="text-sm text-destructive">
                                {qrError}
                            </p>
                        )}

                        {qrSrc && (
                            <img
                                src={qrSrc}
                                alt="QR pairing WhatsApp"
                                className="h-64 w-64 rounded-md border border-border-strong bg-white p-2"
                                data-test="wa-qr-image"
                            />
                        )}

                        <div>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => void fetchQr()}
                                disabled={qrLoading}
                            >
                                Muat ulang QR
                            </Button>
                        </div>
                    </section>
                )}
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
