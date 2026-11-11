import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    ColumnDirective,
    ColumnsDirective,
    GanttComponent,
    Inject,
} from '@syncfusion/ej2-react-gantt';
import { registerLicense } from '@syncfusion/ej2-base';
import { Selection, Toolbar } from '@syncfusion/ej2-gantt';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useRequiredTenant } from '@/lib/tenant';
import { create, gantt, show } from '@/routes/work-orders';

/*
 * Syncfusion React Gantt.
 *
 * PENTING — ini dibaca dulu sebelum mengubah apa pun di file ini.
 *
 * 1. SOURCE DATA. Halaman ini membaca `work_plannings` (punya `tgl_jadwal`
 *    + `lama_pekerjaan_hari` = punya DURASI), BUKAN
 *    `work_orders.scheduled_date` yang cuma satu tanggal. Timeline lama
 *    memakai yang kedua; keduanya masih hidup berdampingan sampai Timeline
 *    ikut pindah sumber jadwal. Jangan bikin sumber ketiga.
 *
 * 2. TASK TIDAK BISA DIPINDAH DARI SINI. `tgl_jadwal` hanya boleh berubah
 *    lewat Extend Work Schedule + approval DGM/GM (FR-2.5). Karena itu
 *    modul `Edit` TIDAK di-Inject dan `allowTaskbarDragAndDrop` sengaja
 *    dibiarkan false. Ini aturan bisnis, bukan kekurangan fitur — kalau
 *    nanti diaktifkan, data akan hilang jalur audit-nya.
 *
 * 3. MODUL MINIMAL. Hanya `Selection`, `Toolbar` (+ `Edit` yang dibutuhkan
 *    Toolbar untuk berfungsi). Semua modul lain (RowDD, Filter, Sort,
 *    CriticalPath, export) menambah bundle dan tidak dipakai.
 *
 * 4. SSR. Syncfusion baru dipasang di klien. Komponen ini dirender bersyarat
 *    supaya Inertia SSR tidak mengirim DOM yang salah.
 */

type DeadlineState = 'on_track' | 'due_soon' | 'overdue' | 'escalated';

/** Pe warna taskbar & label datang BERSEPASANGAN — warna tidak pernah sendiri. */
const taskbarToken: Record<DeadlineState, string> = {
    on_track: 'var(--ink-muted)',
    due_soon: 'var(--warning)',
    overdue: 'var(--danger)',
    escalated: 'var(--escalation)',
};

const deadlineLabelMap: Record<DeadlineState, string> = {
    on_track: 'Sesuai jadwal',
    due_soon: 'Deadline dekat',
    overdue: 'Telat',
    escalated: 'Eskalasi',
};

type GanttTask = {
    TaskID: string;
    TaskName: string;
    ParentID?: string;
    StartDate: string;
    EndDate: string;
    Duration?: number;
    DeadlineState: DeadlineState;
    DeadlineLabel: string;
    NomorWo: string;
    Status?: string | null;
    JamMulai?: string | null;
    JamSelesai?: string | null;
    StatusJadwal?: string | null;
    DepartmentName?: string | null;
};

type PageProps = {
    tasks: GanttTask[];
    weekStart: string;
    weekEnd: string;
    weekLabel: string;
    prevWeek: string;
    nextWeek: string;
    totalScheduled: number;
};

/** Aksi utama halaman, dirender di heading band milik shell. */
function GanttActions() {
    const tenant = useRequiredTenant('work-orders.create');

    return (
        <Button asChild size="lg">
            <Link href={create({ tenant })}>
                <Plus aria-hidden="true" className="size-4" />
                Buat Work Order
            </Link>
        </Button>
    );
}

/**
 * Status jadwal planning → label yang bisa dibaca manusia.
 * Disimpan di sini karena `status_jadwal` tahu soal pengajuan extend yang
 * MENUNGGU, sesuatu yang `deadline_state` tidak tahu.
 */
const scheduleStatusLabel: Record<string, string> = {
    planned: 'Terjadwal',
    pending_extend_approval: 'Menunggu approval extend',
    rescheduled: 'Dijadwalkan ulang',
};

function GanttChart({ tasks }: { tasks: GanttTask[] }) {
    const tenant = useRequiredTenant('work-orders.timeline');

    // Task harus urut naik menurut StartDate — Syncfusion mengembalikan error
    // render kalau tidak. Baris induk (departemen) memakai rentang anak-anaknya
    // yang memang sudah urut dari controller.
    const dataSource = useMemo(
        () => [...tasks].sort((a, b) => a.StartDate.localeCompare(b.StartDate)),
        [tasks],
    );

    const taskFields = useMemo(
        () => ({
            id: 'TaskID',
            name: 'TaskName',
            startDate: 'StartDate',
            endDate: 'EndDate',
            duration: 'Duration',
            parentID: 'ParentID',
        }),
        [],
    );

    /**
     * Warna taskbar mengikuti status deadline — tapi SELALU diecho sebagai
     * teks juga (di tooltip dan di kolom), supaya warna bukan satu-satunya
     * sinyal (P1 + WCAG 1.4.1).
     */
    const queryTaskbarInfo = (args: {
        data?: GanttTask;
        taskbarBgColor: string;
        progressBarBgColor: string;
        milestoneColor: string;
    }) => {
        const state = args.data?.DeadlineState ?? 'on_track';
        args.taskbarBgColor = taskbarToken[state];
        args.progressBarBgColor = taskbarToken[state];
        args.milestoneColor = taskbarToken[state];
    };

    /**
     * Tooltip berisi nomor WO + label deadline + jam + status jadwal.
     * Inilah tempat warna diterjemahkan jadi kalimat — tanpa ini, HOD yang
     * buta warna atau layar di bawah matahari tidak bisa memakai halaman ini.
     */
    const taskbarTooltip = (data: { data: GanttTask }) => {
        const t = data.data;
        const scheduleLabel = t.StatusJadwal
            ? (scheduleStatusLabel[t.StatusJadwal] ?? t.StatusJadwal)
            : '—';

        return (
            <div className="min-w-56 max-w-72 text-sm">
                <p className="font-mono text-xs font-semibold text-ink-muted">
                    {t.NomorWo}
                </p>
                <p className="mt-1 font-semibold text-ink">{t.TaskName}</p>
                <dl className="mt-2 space-y-1 text-xs text-ink-muted">
                    <div className="flex items-center gap-1.5">
                        <dt>Deadline:</dt>
                        <dd className="font-semibold text-ink">
                            {t.DeadlineLabel}
                        </dd>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <dt>Jadwal:</dt>
                        <dd className="font-semibold text-ink">
                            {scheduleLabel}
                        </dd>
                    </div>
                    {t.JamMulai && (
                        <div className="flex items-center gap-1.5">
                            <dt>Waktu:</dt>
                            <dd className="font-semibold text-ink">
                                {t.JamMulai}
                                {t.JamSelesai ? ` – ${t.JamSelesai}` : ''}
                            </dd>
                        </div>
                    )}
                </dl>
                <p className="mt-2 text-xs text-brand">
                    Buka detail → {t.NomorWo}
                </p>
            </div>
        );
    };

    /**
     * Kolom kiri. `field` harus menunjuk properti yang ADA di taskFields,
     * kalau tidak Syncfusion tidak merender sel-nya.
     */
    const nomorWoTemplate = (props: { NomorWo?: string }) => (
        <span className="font-mono text-xs text-ink-muted">
            {props.NomorWo ?? '—'}
        </span>
    );

    const deadlineTemplate = (props: GanttTask) => (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-sm border px-1.5 py-0.5 text-xs font-semibold whitespace-nowrap',
                props.DeadlineState === 'escalated' &&
                    'border-escalation/30 bg-escalation/10 text-escalation',
                props.DeadlineState === 'overdue' &&
                    'border-danger/30 bg-danger/10 text-danger',
                props.DeadlineState === 'due_soon' &&
                    'border-warning/30 bg-warning/10 text-warning',
                props.DeadlineState === 'on_track' &&
                    'border-border bg-surface-raised text-ink-muted',
            )}
        >
            {props.DeadlineLabel}
        </span>
    );

    /**
     * Klik taskbar → detail WO. Ini jalur navigasi utama; tanpa ini Gantt
     * hanya bisa dilihat, tidak bisa ditindaklanjuti.
     */
    const handleTaskbarClick = (args: { data?: GanttTask }) => {
        const taskId = args?.data?.TaskID;
        if (taskId) {
            router.get(show({ tenant, workOrder: taskId }));
        }
    };

    return (
        <GanttComponent
            dataSource={dataSource}
            taskFields={taskFields}
            height="calc(100vh - 17rem)"
            treeColumnIndex={1}
            allowSelection
            gridLines="Both"
            highlightWeekends
            enableImmutableMode
            rowHeight={40}
            taskbarHeight={28}
            timelineSettings={{
                showTooltip: true,
                weekStartDay: 1, // Monday
            }}
            tooltipSettings={{
                showTooltip: true,
                taskbar: taskbarTooltip,
            }}
            queryTaskbarInfo={queryTaskbarInfo}
            /*
             * Toolbar eksplisit: item default memuat "Add" yang membutuhkan
             * modul Edit. Karena task tidak boleh dibuat/dipindah dari sini
             * (aturan approval DGM/GM), tombol itu sengaja tidak disertakan.
             */
            toolbar={[
                'ZoomIn',
                'ZoomOut',
                'ZoomToFit',
                'ExpandAll',
                'CollapseAll',
            ]}
            eventMarkers={[
                // Penanda "hari ini" sebagai event marker + label teks,
                // bukan pewarnaan sel — supaya tetap terbaca tanpa warna.
                { day: new Date(), label: 'Hari ini' },
            ]}
            editSettings={{ allowTaskbarEditing: false }}
            allowTaskbarDragAndDrop={false}
            splitterSettings={{ position: '38%', view: 'Default' }}
            labelSettings={{
                leftLabel: 'NomorWo',
                taskLabel: 'TaskName',
            }}
            onTaskbarClick={handleTaskbarClick}
        >
            <ColumnsDirective>
                <ColumnDirective
                    field="NomorWo"
                    headerText="No. WO"
                    width="130"
                    template={nomorWoTemplate}
                />
                <ColumnDirective
                    field="DeadlineLabel"
                    headerText="Deadline"
                    width="180"
                    template={deadlineTemplate}
                />
                <ColumnDirective
                    field="JamMulai"
                    headerText="Mulai"
                    width="90"
                />
                <ColumnDirective
                    field="StatusJadwal"
                    headerText="Status Jadwal"
                    width="150"
                />
            </ColumnsDirective>
            <Inject services={[Selection, Toolbar]} />
        </GanttComponent>
    );
}

export default function Gantt({
    weekLabel,
    prevWeek,
    nextWeek,
    totalScheduled,
    tasks,
}: PageProps) {
    const tenant = useRequiredTenant('work-orders.timeline');

    // Mount di klien saja: Syncfusion menyentuh DOM saat konstruksi, dan
    // Inertia SSR tidak boleh mengirim markup yang tidak cocok saat hydrate.
    const [mounted, setMounted] = useState(false);
    useEffect(() => {
        setMounted(true);
    }, []);

    // Lisensi: tanpa ini muncul banner trial Syncfusion di atas halaman.
    // Nilai diambil dari variabel lingkungan Vite — JANGAN ditulis literal
    // di source karena akan ikut terbundel ke JS publik.
    const licenseKey = import.meta.env.VITE_SYNCFUSION_LICENSE_KEY;
    useEffect(() => {
        if (licenseKey) {
            registerLicense(licenseKey);
        }
    }, [licenseKey]);

    const goToWeek = (week: string) => {
        router.get(
            gantt({ tenant }),
            { start: week },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Gantt Jadwal WO" />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-1.5">
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Minggu sebelumnya"
                            onClick={() => goToWeek(prevWeek)}
                        >
                            <ChevronLeft aria-hidden="true" className="size-4" />
                        </Button>

                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Minggu berikutnya"
                            onClick={() => goToWeek(nextWeek)}
                        >
                            <ChevronRight aria-hidden="true" className="size-4" />
                        </Button>

                        <p className="ml-1 text-sm font-semibold text-ink">
                            {weekLabel}
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <p className="text-xs text-ink-muted">
                            {totalScheduled} WO terjadwal · dikelompokkan per
                            departemen
                        </p>
                        <Link
                            href={gantt({ tenant })}
                            className="inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-hover"
                        >
                            <CalendarClock aria-hidden="true" className="size-3.5" />
                            Minggu ini
                        </Link>
                    </div>
                </div>

                {mounted ? (
                    <div className="rounded-xl border border-border bg-card p-4 shadow-card">
                        <GanttChart tasks={tasks} />
                    </div>
                ) : (
                    // Placeholder sebelum hydration: tinggi dipertahankan
                    // supaya layout tidak melompat (CLS) saat Gantt muncul.
                    <div
                        className="rounded-xl border border-border bg-card p-4 shadow-card"
                        style={{ minHeight: 420 }}
                        aria-hidden="true"
                    />
                )}

                <p className="text-xs text-ink-subtle">
                    Jadwal diambil dari perencanaan WO. Perpindahan tanggal hanya
                    bisa melalui pengajuan extend yang disetujui DGM/GM — karena
                    itu jadwal di sini bersifat baca saja.
                </p>
            </div>
        </>
    );
}

Gantt.layout = {
    breadcrumbs: [{ title: 'Work Order' }, { title: 'Gantt' }],
    title: 'Gantt Jadwal',
    description: 'Jadwal WO per departemen, dari perencanaan WO aktif',
    actions: GanttActions,
};
