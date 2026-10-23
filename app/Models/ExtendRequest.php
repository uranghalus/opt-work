<?php

namespace App\Models;

use App\ExtendRequestStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pengajuan extend deadline WO telat (FR-2.3–2.5, OQ-9 settled: tabel
 * terpisah). Batas maks 3 hari per request dan maks 3 approved per WO
 * ditegakkan di ExtendRequestService; baris ini menyimpan jejak audit
 * approval berjenjang Team Leader → HOD (siapa, kapan, alasan).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_order_id
 * @property int $requested_by_user_id
 * @property int $days
 * @property string $reason
 * @property ExtendRequestStatus $status
 * @property int|null $team_leader_user_id
 * @property Carbon|null $team_leader_approved_at
 * @property int|null $approved_by_user_id
 * @property Carbon|null $approved_at
 * @property int|null $rejected_by_user_id
 * @property Carbon|null $rejected_at
 * @property string|null $rejected_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'work_order_id',
    'requested_by_user_id',
    'days',
    'reason',
    'status',
    'team_leader_user_id',
    'team_leader_approved_at',
    'approved_by_user_id',
    'approved_at',
    'rejected_by_user_id',
    'rejected_at',
    'rejected_reason',
])]
class ExtendRequest extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExtendRequestStatus::class,
            'team_leader_approved_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }
}
