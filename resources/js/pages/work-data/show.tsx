import { Head, useForm } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import {
    CalendarClock,
    ClipboardList,
    Clock3,
    Database,
    Hourglass,
    UserCheck,
} from 'lucide-react';
import { show as showAttachment } from '@/routes/work-orders/attachments';
import { finalize as finalizeWorkData } from '@/routes/work-data';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { useRequiredTenant } from '@/lib/tenant';

type PekerjaRow = {
    id: string;
    nama_employee: string;
    role_pekerja: string;
    employee: { nama_employee: string | null } | null;
};

type WorkDataDetail = {
    id: string;
    work_order_id: string;
    no_kerja: string;
    status_pekerjaan: string;
    tgl_mulai_aktual: string | null;
    tgl_selesai_aktual: string | null;
    jam_pengerjaan: string | null;
    gambar_sebelum: string[] | null;
    gambar_sesudah: string[] | null;
    prediksi_penyebab: string | null;
    tindakan: string | null;
    hasil_kesimpulan: string | null;
    saran_solusi: string | null;
    finalized_at: string | null;
    department: { nama_department: string | null } | null;
    pekerja: PekerjaRow[];
    work_order: { nomor_wo: string; title: string } | null;
    pic_lead: { name: string } | null;
};

type PageProps = InertiaConfig['sharedPageProps'] & {
    workData: WorkDataDetail;
};

const formatDate = (value: string | null): string =>
    value
        ? new Date(value).toLocaleDateString('id-ID', {
              day: '2-digit',
              month: 'long',
              year: 'numeric',
          })
        : '—';

const formatDateTime = (value: string): string =>
    new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

function DetailRow({
    label,
    children,
    last = false,
}: {
    label: string;
    children: React.ReactNode;
    last?: boolean;
}) {
    return (
        <div
            className={`flex items-start justify-between gap-4 px-4 py-3 ${last ? '' : 'border-b'}`}
        >
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="max-w-md text-right text-sm">{children}</dd>
        </div>
    );
}

function PhotoGrid({
    paths,
    workOrderId,
    tenant,
    type,
    emptyLabel,
}: {
    paths: string[] | null;
    workOrderId: string;
    tenant: string;
    type?: 'result';
    emptyLabel: string;
}) {
    if (!paths || paths.length === 0) {
        return <p className="text-sm text-ink-muted">{emptyLabel}</p>;
    }

    return (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {paths.map((path, index) => (
                <li key={path}>
                    <img
                        src={showAttachment.url({
                            tenant,
                            workOrder: workOrderId,
                            index,
                            type,
                        })}
                        alt={`Foto ${type === 'result' ? 'sesudah' : 'sebelum'} ${index + 1}`}
                        loading="lazy"
                        className="aspect-video w-full rounded-lg border bg-surface-raised object-cover"
                    />
                </li>
            ))}
        </ul>
    );
}

/**
 * FR-3.3: analitik post-mortem ditulis SATU kali (append-only) — setelah
 * finalized_at terisi record beku dan panel ini tidak pernah muncul lagi.
 */
function FinalizePanel({
    workDataId,
    noKerja,
}: {
    workDataId: string;
    noKerja: string;
}) {
    const tenant = useRequiredTenant('work-data.finalize');

    const form = useForm<{
        prediksi_penyebab: string;
        tindakan: string;
        hasil_kesimpulan: string;
        saran_solusi: string;
    }>({
        prediksi_penyebab: '',
        tindakan: '',
        hasil_kesimpulan: '',
        saran_solusi: '',
    });

    return (
        <section className="space-y-3 rounded-lg border border-warning/40 bg-card p-4">
            <div className="flex items-center gap-2">
                <Hourglass aria-hidden className="size-4 text-warning" />
                <h3 className="text-base font-semibold tracking-tight">
                    Finalisasi Analitik
                </h3>
            </div>
            <p className="text-xs text-muted-foreground">
                Analitik post-mortem untuk {noKerja} hanya bisa ditulis{' '}
                <strong>sekali</strong> — setelah tersimpan, Work Data ini
                mengunci dirinya sendiri selamanya (append-only).
            </p>

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(
                        finalizeWorkData({
                            tenant,
                            workData: workDataId,
                        }).url,
                        {
                            preserveScroll: true,
                        },
                    );
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="prediksi_penyebab">
                        Prediksi Penyebab
                    </Label>
                    <Textarea
                        id="prediksi_penyebab"
                        rows={2}
                        value={form.data.prediksi_penyebab}
                        onChange={(event) =>
                            form.setData(
                                'prediksi_penyebab',
                                event.target.value,
                            )
                        }
                        aria-invalid={Boolean(form.errors.prediksi_penyebab)}
                    />
                    {form.errors.prediksi_penyebab && (
                        <p className="text-xs text-danger">
                            {form.errors.prediksi_penyebab}
                        </p>
                    )}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="tindakan">Tindakan yang Diambil</Label>
                    <Textarea
                        id="tindakan"
                        rows={2}
                        value={form.data.tindakan}
                        onChange={(event) =>
                            form.setData('tindakan', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.tindakan)}
                    />
                    {form.errors.tindakan && (
                        <p className="text-xs text-danger">
                            {form.errors.tindakan}
                        </p>
                    )}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="hasil_kesimpulan">Hasil Kesimpulan</Label>
                    <Textarea
                        id="hasil_kesimpulan"
                        rows={2}
                        value={form.data.hasil_kesimpulan}
                        onChange={(event) =>
                            form.setData(
                                'hasil_kesimpulan',
                                event.target.value,
                            )
                        }
                        aria-invalid={Boolean(form.errors.hasil_kesimpulan)}
                    />
                    {form.errors.hasil_kesimpulan && (
                        <p className="text-xs text-danger">
                            {form.errors.hasil_kesimpulan}
                        </p>
                    )}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="saran_solusi">Saran Solusi</Label>
                    <Textarea
                        id="saran_solusi"
                        rows={2}
                        value={form.data.saran_solusi}
                        onChange={(event) =>
                            form.setData('saran_solusi', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.saran_solusi)}
                    />
                    {form.errors.saran_solusi && (
                        <p className="text-xs text-danger">
                            {form.errors.saran_solusi}
                        </p>
                    )}
                </div>

                <Button type="submit" disabled={form.processing}>
                    Kunci Analitik
                </Button>
            </form>
        </section>
    );
}

WorkDataShow.layout = {
    breadcrumbs: [{ title: 'Work Data' }, { title: '' }],
    title: (workData: WorkDataDetail) => workData.no_kerja,
    description: (workData: WorkDataDetail) =>
        workData.department?.nama_department ?? 'Histori pekerjaan',
};

export default function WorkDataShow(props: PageProps) {
    const { workData } = props;
    const tenant = useRequiredTenant('work-data.show');
    const { auth } = props;
    const canFinalize =
        (auth.can?.['work-data.update'] ?? false) &&
        workData.finalized_at === null;

    return (
        <>
            <Head title={workData.no_kerja} />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-2.5 py-1 text-[11px] font-semibold text-ink">
                        <Database aria-hidden="true" className="size-3" />
                        {workData.status_pekerjaan === 'selesai'
                            ? 'Selesai'
                            : workData.status_pekerjaan}
                    </span>
                    {workData.finalized_at === null && (
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-warning/30 bg-warning-subtle px-2.5 py-1 text-[11px] font-semibold text-warning-ink">
                            <span
                                aria-hidden="true"
                                className="size-1.5 rounded-full bg-warning"
                            />
                            Menunggu analitik
                        </span>
                    )}
                </div>

                <dl className="overflow-hidden rounded-lg border bg-card">
                    <DetailRow label="No Kerja">
                        <span className="font-mono text-sm">
                            {workData.no_kerja}
                        </span>
                    </DetailRow>
                    <DetailRow label="Department">
                        <span className="font-semibold">
                            {workData.department?.nama_department ?? '—'}
                        </span>
                    </DetailRow>
                    {workData.work_order && (
                        <DetailRow label="Work Order">
                            <span className="font-mono text-xs text-ink-muted">
                                {workData.work_order.nomor_wo}
                            </span>
                            {' — '}
                            {workData.work_order.title}
                        </DetailRow>
                    )}
                    <DetailRow label="Tanggal Mulai">
                        {formatDate(workData.tgl_mulai_aktual)}
                    </DetailRow>
                    <DetailRow label="Tanggal Selesai">
                        {formatDate(workData.tgl_selesai_aktual)}
                    </DetailRow>
                    <DetailRow label="Jam Pengerjaan">
                        <span className="inline-flex items-center gap-1.5 tabular-nums">
                            <Clock3 aria-hidden="true" className="size-3.5 text-ink-muted" />
                            {workData.jam_pengerjaan ?? '—'} jam
                        </span>
                    </DetailRow>
                    <DetailRow label="PIC Lead">
                        {workData.pic_lead?.name ?? '—'}
                    </DetailRow>
                    <DetailRow label="Difinalisasi" last>
                        {workData.finalized_at
                            ? formatDateTime(workData.finalized_at)
                            : 'Belum'}
                    </DetailRow>
                </dl>

                {workData.pekerja.length > 0 && (
                    <section className="space-y-2">
                        <h3 className="flex items-center gap-2 text-base font-semibold tracking-tight">
                            <UserCheck aria-hidden className="size-4 text-ink-muted" />
                            Pelaksana
                        </h3>
                        <Separator />
                        <ul className="flex flex-wrap gap-2">
                            {workData.pekerja.map((pekerja) => (
                                <li
                                    key={pekerja.id}
                                    className="rounded-full border bg-card px-3 py-1 text-[12.5px] text-ink"
                                >
                                    {pekerja.employee?.nama_employee ??
                                        pekerja.nama_employee}
                                    <span className="ml-1.5 text-[10.5px] tracking-[0.08em] text-ink-muted uppercase">
                                        {pekerja.role_pekerja}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="space-y-2">
                    <h3 className="text-base font-semibold tracking-tight">
                        Gambar Sebelum
                    </h3>
                    <Separator />
                    <PhotoGrid
                        paths={workData.gambar_sebelum}
                        workOrderId={workData.work_order_id}
                        tenant={tenant}
                        emptyLabel="Tidak ada foto kondisi awal."
                    />
                </section>

                <section className="space-y-2">
                    <h3 className="text-base font-semibold tracking-tight">
                        Gambar Sesudah
                    </h3>
                    <Separator />
                    <PhotoGrid
                        paths={workData.gambar_sesudah}
                        workOrderId={workData.work_order_id}
                        tenant={tenant}
                        type="result"
                        emptyLabel="Tidak ada foto hasil pekerjaan."
                    />
                </section>

                {workData.finalized_at !== null && (
                    <section className="space-y-2">
                        <h3 className="flex items-center gap-2 text-base font-semibold tracking-tight">
                            <ClipboardList aria-hidden className="size-4 text-ink-muted" />
                            Analitik Post-Mortem
                        </h3>
                        <Separator />
                        <dl className="overflow-hidden rounded-lg border bg-card">
                            <DetailRow label="Prediksi Penyebab">
                                {workData.prediksi_penyebab ?? '—'}
                            </DetailRow>
                            <DetailRow label="Tindakan">
                                {workData.tindakan ?? '—'}
                            </DetailRow>
                            <DetailRow label="Hasil Kesimpulan">
                                {workData.hasil_kesimpulan ?? '—'}
                            </DetailRow>
                            <DetailRow label="Saran Solusi" last>
                                {workData.saran_solusi ?? '—'}
                            </DetailRow>
                        </dl>
                    </section>
                )}

                {canFinalize && (
                    <FinalizePanel
                        workDataId={workData.id}
                        noKerja={workData.no_kerja}
                    />
                )}
            </div>
        </>
    );
}
