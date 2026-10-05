<?php

namespace App\Models;

use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $nomor_wo
 * @property int $requester_user_id
 * @property string $target_department_id
 * @property WorkOrderCategory $category
 * @property string $title
 * @property string $description
 * @property array<int, string>|null $attachments
 * @property WorkOrderStatus $status
 * @property Carbon|null $requested_schedule_date
 * @property Carbon|null $deadline_date
 * @property Carbon|null $escalation_h3_sent_at
 * @property Carbon|null $escalation_h5_sent_at
 * @property Carbon|null $escalation_h6_sent_at
 * @property bool $is_escalated
 * @property int $extend_count
 * @property string|null $extend_reason
 * @property Carbon|null $extended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'nomor_wo',
    'requester_user_id',
    'target_department_id',
    'category',
    'title',
    'description',
    'attachments',
    'status',
    'requested_schedule_date',
    'deadline_date',
    'escalation_h3_sent_at',
    'escalation_h5_sent_at',
    'escalation_h6_sent_at',
    'is_escalated',
    'extend_count',
    'extend_reason',
    'extended_at',
])]
class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => WorkOrderCategory::class,
            'status' => WorkOrderStatus::class,
            'attachments' => 'array',
            'requested_schedule_date' => 'date',
            'deadline_date' => 'date',
            'escalation_h3_sent_at' => 'datetime',
            'escalation_h5_sent_at' => 'datetime',
            'escalation_h6_sent_at' => 'datetime',
            'is_escalated' => 'boolean',
            'extended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }
}
