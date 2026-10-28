<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkTimeLog;
use App\WorkOrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Timer durasi kerja pelaksana (R16-R18): accept → start → pause/resume →
 * finish. Semua timestamp ditulis server dari request konteks (R17) dan
 * elapsed dihitung dari data tersimpan — aman setelah refresh atau dari
 * perangkat lain.
 *
 * Pemulihan: kelas ini terhapus pada penggabungan cabang sementara
 * WorkOrderController masih constructor-inject-nya.
 */
class WorkTimeLogService
{
    /**
     * R16: pelaksana menerima penugasan — timestamp diterima dicatat sekali.
     */
    public function accept(User $actor, WorkOrder $workOrder): WorkTimeLog
    {
        return DB::transaction(function () use ($actor, $workOrder): WorkTimeLog {
            $employeeId = $this->assertAssignee($actor, $workOrder);
            $this->assertExecutable($workOrder);

            $log = $this->firstOrNew($workOrder->getKey(), $employeeId);

            if ($log->accepted_at !== null) {
                abort(409, 'Work Order ini sudah Anda terima.');
            }

            $log->accepted_at = now();
            $log->save();

            WorkOrderEvent::record($workOrder, $actor, 'accepted', null, null, [
                'employee_id' => $employeeId,
            ]);

            return $log;
        });
    }

    /**
     * Mulai bekerja — WO berpindah ke InProgress (sekali per pelaksana;
     * pelaksana kedua pada WO yang sama tidak mengubah status lagi).
     */
    public function start(User $actor, WorkOrder $workOrder): WorkTimeLog
    {
        return DB::transaction(function () use ($actor, $workOrder): WorkTimeLog {
            $employeeId = $this->assertAssignee($actor, $workOrder);
            $this->assertExecutable($workOrder);

            $log = $this->firstOrNew($workOrder->getKey(), $employeeId);

            if ($log->accepted_at === null) {
                abort(409, 'Terima Work Order terlebih dahulu sebelum mulai bekerja.');
            }

            if ($log->started_at !== null) {
                abort(409, 'Timer Anda untuk Work Order ini sudah berjalan atau selesai.');
            }

            $log->started_at = now();
            $log->save();

            if ($workOrder->status === WorkOrderStatus::Assigned) {
                $workOrder->update(['status' => WorkOrderStatus::InProgress->value]);
            }

            WorkOrderEvent::record($workOrder, $actor, 'time_started', $workOrder->status->value, WorkOrderStatus::InProgress->value, [
                'employee_id' => $employeeId,
            ]);

            return $log;
        });
    }

    /**
     * Jeda — segmen terbuka dicatat; total jeda tertutup dihitung saat
     * resume (elapsed efektif selalu server-computed, R17).
     */
    public function pause(User $actor, WorkOrder $workOrder): WorkTimeLog
    {
        return DB::transaction(function () use ($actor, $workOrder): WorkTimeLog {
            $employeeId = $this->assertAssignee($actor, $workOrder);
            $this->assertExecutable($workOrder);

            $log = $this->existing($workOrder->getKey(), $employeeId);

            if ($log->started_at === null || $log->finished_at !== null) {
                abort(409, 'Timer belum berjalan atau sudah selesai.');
            }

            if ($log->paused_at !== null) {
                abort(409, 'Timer sudah dalam keadaan dipause.');
            }

            $log->paused_at = now();
            $log->pause_log = [
                ...($log->pause_log ?? []),
                ['paused_at' => $log->paused_at->toIso8601String()],
            ];
            $log->save();

            WorkOrderEvent::record($workOrder, $actor, 'time_paused', null, null, [
                'employee_id' => $employeeId,
            ]);

            return $log;
        });
    }

    public function resume(User $actor, WorkOrder $workOrder): WorkTimeLog
    {
        return DB::transaction(function () use ($actor, $workOrder): WorkTimeLog {
            $employeeId = $this->assertAssignee($actor, $workOrder);
            $this->assertExecutable($workOrder);

            $log = $this->existing($workOrder->getKey(), $employeeId);

            if ($log->paused_at === null) {
                abort(409, 'Timer tidak sedang dipause.');
            }

            $pausedAt = $log->paused_at;
            $log->paused_seconds += (int) $pausedAt->diffInSeconds(now());
            $log->paused_at = null;

            $entries = $log->pause_log ?? [];
            $last = count($entries) - 1;

            if ($last >= 0) {
                $entries[$last]['resumed_at'] = now()->toIso8601String();
            }

            $log->pause_log = $entries;
            $log->save();

            WorkOrderEvent::record($workOrder, $actor, 'time_resumed', null, null, [
                'employee_id' => $employeeId,
            ]);

            return $log;
        });
    }

    /**
     * Akhiri timer pelaksana — dipanggil otomatis dari submit (Selesai).
     * Idempoten: baris yang belum ada / sudah selesai diabaikan.
     */
    public function finish(WorkOrder $workOrder, string $employeeId): void
    {
        $log = WorkTimeLog::query()
            ->where('work_order_id', $workOrder->getKey())
            ->where('employee_id', $employeeId)
            ->first();

        if ($log === null || $log->finished_at !== null || $log->started_at === null) {
            return;
        }

        if ($log->paused_at !== null) {
            $log->paused_seconds += (int) $log->paused_at->diffInSeconds(now());

            $entries = $log->pause_log ?? [];
            $last = count($entries) - 1;

            if ($last >= 0 && ! isset($entries[$last]['resumed_at'])) {
                $entries[$last]['resumed_at'] = now()->toIso8601String();
            }

            $log->pause_log = $entries;
            $log->paused_at = null;
        }

        $log->finished_at = now();
        $log->save();
    }

    /**
     * R15/R17: timer milik pelaksana pada WO itu — dipakai payload detail
     * dan aksi pause/resume.
     */
    public function logFor(WorkOrder $workOrder, string $employeeId): ?WorkTimeLog
    {
        return WorkTimeLog::query()
            ->where('work_order_id', $workOrder->getKey())
            ->where('employee_id', $employeeId)
            ->first();
    }

    /**
     * 403 bila actor bukan pelaksana tercantum pada WO (R15 — server-side).
     *
     * @return string employee id pelaksana
     */
    private function assertAssignee(User $actor, WorkOrder $workOrder): string
    {
        $employeeId = (string) $actor->employee_id;

        if ($employeeId === ''
            || ! in_array($employeeId, $workOrder->assigned_employees ?? [], true)) {
            abort(403, 'Hanya pelaksana yang ditugaskan pada Work Order ini yang dapat melakukan aksi ini.');
        }

        return $employeeId;
    }

    /**
     * Aksi timer hanya bermakna saat WO sedang dieksekusi.
     */
    private function assertExecutable(WorkOrder $workOrder): void
    {
        if (! in_array($workOrder->status, [WorkOrderStatus::Assigned, WorkOrderStatus::InProgress], true)) {
            abort(409, 'Work Order tidak dalam tahap eksekusi.');
        }
    }

    private function firstOrNew(string $workOrderId, string $employeeId): WorkTimeLog
    {
        return WorkTimeLog::query()
            ->firstOrNew([
                'work_order_id' => $workOrderId,
                'employee_id' => $employeeId,
            ]);
    }

    private function existing(string $workOrderId, string $employeeId): WorkTimeLog
    {
        $log = $this->firstOrNew($workOrderId, $employeeId);

        if (! $log->exists) {
            abort(409, 'Timer belum dimulai untuk Work Order ini.');
        }

        return $log;
    }
}
