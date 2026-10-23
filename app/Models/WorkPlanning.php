<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FR-2.4: Work Planning — jadwal detail per WO Terjadwal. `tgl_jadwal`
 * hanya berpindah lewat Extend Work Schedule (approval DGM/GM); field
 * lain (jam, jenis pekerjaan, durasi, budget) bebas diperbarui HOD
 * selama WO masih terjadwal dan tidak ada pengajuan pending.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_order_id
 * @property Carbon $tgl_jadwal
 * @property string|null $jam_mulai
 * @property string|null $jam_selesai
 * @property string|null $jenis_pekerjaan
 * @property int|null $lama_pekerjaan_hari
 * @property string|float|null $budget
 * @property string $status_jadwal
 * @property string|null $catatan
 * @property int $extend_count
 * @property Carbon|null $original_tgl_jadwal
 * @property Carbon|null $proposed_tgl_jadwal
 * @property string|null $extend_reason
 * @property int|null $extend_requested_by
 * @property Carbon|null $extend_requested_at
 * @property int|null $extend_approved_by
 * @property Carbon|null $extend_approved_at
 * @property string|null $extend_approval_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'work_order_id',
    'tgl_jadwal',
    'jam_mulai',
    'jam_selesai',
    'jenis_pekerjaan',
    'lama_pekerjaan_hari',
    'budget',
    'status_jadwal',
    'catatan',
    'extend_count',
    'original_tgl_jadwal',
    'proposed_tgl_jadwal',
    'extend_reason',
    'extend_requested_by',
    'extend_requested_at',
    'extend_approved_by',
    'extend_approved_at',
    'extend_approval_notes',
])]
class WorkPlanning extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $table = 'work_plannings';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_PENDING = 'pending_extend_approval';

    public const STATUS_RESCHEDULED = 'rescheduled';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tgl_jadwal' => 'date',
            'original_tgl_jadwal' => 'date',
            'proposed_tgl_jadwal' => 'date',
            'budget' => 'decimal:2',
            'extend_requested_at' => 'datetime',
            'extend_approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function extendRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extend_requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function extendApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extend_approved_by');
    }

    public function isPending(): bool
    {
        return $this->status_jadwal === self::STATUS_PENDING;
    }
}
