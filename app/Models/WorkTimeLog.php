<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\WorkTimeLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Timer durasi kerja satu pelaksana pada satu WO (R16-R18) — satu baris
 * per (work_order, employee), unique. Semua timestamp disimpan server dan
 * elapsed dihitung dari data tersimpan (R17): tetap benar setelah refresh
 * atau dibuka dari perangkat lain.
 *
 * Siklus: accept (diterima) → start (mulai, WO jadi InProgress) →
 * pause/resume (segmen jeda tercatat di pause_log, totalnya paused_seconds)
 * → finished_at (diisi otomatis saat pelaksana submit hasil).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_order_id
 * @property string|null $employee_id
 * @property Carbon|null $accepted_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int $paused_seconds
 * @property Carbon|null $paused_at
 * @property array<int, array{paused_at: string, resumed_at?: string}>|null $pause_log
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'work_order_id',
    'employee_id',
    'accepted_at',
    'started_at',
    'finished_at',
    'paused_seconds',
    'paused_at',
    'pause_log',
])]
class WorkTimeLog extends Model
{
    /** @use HasFactory<WorkTimeLogFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $casts = [
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'paused_at' => 'datetime',
        'paused_seconds' => 'integer',
        'pause_log' => 'array',
    ];

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Durasi kerja efektif (detik) sampai titik waktu acuan: selesai →
     * finished_at; sedang dipause → paused_at; masih jalan → sekarang.
     * Total jeda yang sudah tertutup (paused_seconds) dipotong; segmen
     * jeda yang masih terbuka otomatis tidak terhitung karena acuannya
     * paused_at, bukan now().
     */
    public function elapsedSeconds(?Carbon $now = null): int
    {
        if ($this->started_at === null) {
            return 0;
        }

        $end = $this->finished_at
            ?? $this->paused_at
            ?? $now
            ?? Date::now();

        return (int) max(0, $this->started_at->diffInSeconds($end) - $this->paused_seconds);
    }

    /**
     * Status timer untuk tampilan (R17): belum mulai, berjalan, dipause,
     * selesai.
     */
    public function timerState(): string
    {
        if ($this->finished_at !== null) {
            return 'finished';
        }

        if ($this->paused_at !== null) {
            return 'paused';
        }

        if ($this->started_at !== null) {
            return 'running';
        }

        return $this->accepted_at !== null ? 'accepted' : 'not_started';
    }
}
