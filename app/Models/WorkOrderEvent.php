<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\WorkOrderEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only history row for a work order (issue 03 checkbox 4): who did
 * what, when, moving the WO from one status to another. Rows are never
 * updated or deleted (cascade only follows its work order).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_order_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $from_status
 * @property string|null $to_status
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'work_order_id',
    'user_id',
    'action',
    'from_status',
    'to_status',
    'meta',
])]
class WorkOrderEvent extends Model
{
    /** @use HasFactory<WorkOrderEventFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(
        WorkOrder $workOrder,
        ?User $user,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        array $meta = [],
    ): self {
        return static::create([
            'work_order_id' => $workOrder->getKey(),
            'user_id' => $user?->getKey(),
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
