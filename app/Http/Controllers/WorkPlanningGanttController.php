<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\WorkOrder;
use App\Models\WorkPlanning;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gantt — jadwal WO dari `work_plannings` (FR-2.4/FR-2.5).
 *
 * Bedanya dengan Timeline: Timeline membaca `work_orders.scheduled_date`
 * (satu tanggal, sumber jadwal lama); Gantt membaca `work_plannings`
 * yang punya `tgl_jadwal` + `lama_pekerjaan_hari` — jadi punya DURASI,
 * syarat mutlak Gantt.
 *
 * Bentuk baris: satu baris induk per departemen (`parentID`), anak-anaknya
 * WO. Syncfusion mengenali baris induk justru dari anak yang menunjuk
 * `parentID` ke-nya, jadi baris departemen tidak perlu flag khusus — hanya
 * butuh tanggal dihitung dari rentang anak-anaknya.
 *
 * `tgl_jadwal` TIDAK bisa dipindah dari UI ini: perpindahan tanggal hanya
 * lewat Extend Work Schedule + approval DGM/GM (FR-2.5). Karena itu
 * taskbar edit dimatikan — bukan karena Syncfusion kurang capable.
 */
class WorkPlanningGanttController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('work-order.read');

        $user = $request->user();

        [$start, $end] = $this->resolveRange($request);

        $reviewableDepartmentIds = Department::query()
            ->where('hod_user_id', $user->getKey())
            ->orWhere('manager_user_id', $user->getKey())
            ->pluck('id');

        $plannings = WorkPlanning::query()
            ->with([
                'workOrder:id,nomor_wo,title,status,deadline_date,target_department_id',
                'workOrder.targetDepartment:id,nama_department',
            ])
            ->whereBetween('tgl_jadwal', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->whereHas('workOrder', fn ($q) => $q->visibleFor($user, $reviewableDepartmentIds))
            ->get()
            ->filter(fn (WorkPlanning $p) => filled($p->workOrder))
            ->sortBy([
                fn (WorkPlanning $a, WorkPlanning $b) => strcmp(
                    (string) $a->workOrder->target_department_id,
                    (string) $b->workOrder->target_department_id,
                ),
                fn (WorkPlanning $a, WorkPlanning $b) => $a->tgl_jadwal->timestamp <=> $b->tgl_jadwal->timestamp,
            ])
            ->values();

        return Inertia::render('work-orders/gantt', [
            'tasks' => $this->buildTasks($plannings),
            'weekStart' => $start->toDateString(),
            'weekEnd' => $end->toDateString(),
            'weekLabel' => $start->format('d M').' – '.$end->format('d M Y'),
            'prevWeek' => $start->copy()->subWeek()->toDateString(),
            'nextWeek' => $start->copy()->addWeek()->toDateString(),
            'totalScheduled' => $plannings->count(),
        ]);
    }

    /**
     * Ubah kumpulan planning jadi baris Gantt: baris induk per departemen +
     * baris anak per WO.
     *
     * @param  Collection<int, WorkPlanning>  $plannings
     * @return array<int, array<string, mixed>>
     */
    private function buildTasks($plannings): array
    {
        $byDepartment = [];

        foreach ($plannings as $planning) {
            $departmentName = $planning->workOrder->targetDepartment?->nama_department ?? 'Tanpa Departemen';
            $byDepartment[$departmentName][] = $planning;
        }

        $tasks = [];

        foreach ($byDepartment as $departmentName => $departmentPlannings) {
            $parentId = 'dept-'.md5($departmentName);
            $children = [];

            foreach ($departmentPlannings as $planning) {
                $children[] = $this->buildTaskRow($planning, $parentId);
            }
            $starts = array_map(fn (array $t) => $t['StartDate'], $children);
            $ends = array_map(fn (array $t) => $t['EndDate'], $children);

            $tasks[] = [
                'TaskID' => $parentId,
                'TaskName' => $departmentName.' ('.count($children).' WO)',
                'StartDate' => min($starts),
                'EndDate' => max($ends),
            ];

            $tasks = [...$tasks, ...$children];
        }

        return $tasks;
    }

    /**
     * Satu baris anak: sebuah WO terjadwal.
     *
     * `Duration` adalah SATUAN HARI KERJA (bukan kalender) sesuai Syncfusion,
     * jadi ini memetahkan `lama_pekerjaan_hari` apa adanya. Bila kolom itu
     * null (belum diisi HOD) kita pakai 1 hari supaya bar tetap terlihat —
     * bar 0-hari akan jadi milestone dan menyembunyikan durasi pekerjaan.
     *
     * @return array<string, mixed>
     */
    private function buildTaskRow(WorkPlanning $planning, string $parentId): array
    {
        $workOrder = $planning->workOrder;
        $duration = max(1, (int) $planning->lama_pekerjaan_hari);
        [$deadlineState, $deadlineLabel] = $this->deadlineState($workOrder);

        return [
            'TaskID' => $planning->getKey(),
            'TaskName' => $workOrder->nomor_wo.' — '.$workOrder->title,
            'ParentID' => $parentId,
            'StartDate' => $planning->tgl_jadwal->toDateString(),
            'EndDate' => $planning->tgl_jadwal->copy()->addDays($duration)->toDateString(),
            'Duration' => $duration,
            'DeadlineState' => $deadlineState,
            'DeadlineLabel' => $deadlineLabel,
            'NomorWo' => $workOrder->nomor_wo,
            'Status' => $workOrder->status?->value,
            'JamMulai' => $planning->jam_mulai,
            'JamSelesai' => $planning->jam_selesai,
            'StatusJadwal' => $planning->status_jadwal,
            'DepartmentName' => $workOrder->targetDepartment?->nama_department,
        ];
    }

    /**
     * Status deadline untuk pewarnaan taskbar (queryTaskbarInfo).
     *
     * Mengembalikan [state, label] — label WAJIB ikut supaya warna bar
     * tidak pernah jadi satu-satunya sinyal (P1: status terbaca tanpa
     * warna). Logika sengaja dipakai ulang apa adanya dari
     * WorkOrderController::deadlineState() supaya Gantt dan Timeline tidak
     * pernah berselisih soal mana WO yang telat.
     *
     * @return array{0: string, 1: string}
     */
    private function deadlineState(WorkOrder $workOrder): array
    {
        if ($workOrder->deadline_date === null) {
            return ['on_track', 'Sesuai jadwal'];
        }

        // DateTime::diff() reports the magnitude in ->days and the direction
        // in ->invert (1 when the second date is the earlier one).
        $diff = Carbon::today()
            ->startOfDay()
            ->diff($workOrder->deadline_date->startOfDay());

        if ($diff->invert === 1) {
            $daysLate = (int) $diff->days;

            return $daysLate >= 3 || $workOrder->is_escalated
                ? ['escalated', 'Eskalasi H+'.$daysLate]
                : ['overdue', 'Telat H+'.$daysLate];
        }

        $daysUntil = (int) $diff->days;

        return match (true) {
            $daysUntil === 0 => ['due_soon', 'Deadline hari ini'],
            $daysUntil === 1 => ['due_soon', 'Deadline besok'],
            default => ['on_track', 'Sesuai jadwal'],
        };
    }

    /**
     * Default satu minggu, Monday-first. Rentang dikirim sebagai
     * `start` (inclusive) supaya bisa di-deep-link dan tombol back jalan.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $today = Carbon::today();
        $defaultStart = $today->copy()->startOfWeek(Carbon::MONDAY);

        $rawStart = $request->query('start');
        $start = is_string($rawStart) && trim($rawStart) !== ''
            ? Carbon::parse(trim($rawStart))
            : $defaultStart->copy();

        // Jangan biarkan tanggal jauh di luar jendela masuk ke kueri.
        if ($start->lt($today->copy()->subYear()) || $start->gt($today->copy()->addYear())) {
            $start = $defaultStart->copy();
        }

        $start->startOfDay();

        return [$start, $start->copy()->addDays(6)->endOfDay()];
    }
}
