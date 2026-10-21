<?php

namespace App\Models;

use App\WaMessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Outbox pengiriman WhatsApp per penerima (ADR-0009).
 *
 * Append-only dalam arti bisnis: kolom status hanya bergerak maju
 * (pending → sent/failed; skipped final sejak awal), tidak pernah kembali ke
 * null. Baris tidak pernah dihapus selain cascade WO/tenant-nya.
 *
 * TIDAK memakai BelongsToTenant — lihat docblock migration: job queue membaca
 * baris ini tanpa jaminan konteks tenant.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string|null $work_order_id
 * @property int|null $recipient_user_id
 * @property string $event
 * @property string|null $phone
 * @property string|null $chat_id
 * @property string $payload
 * @property WaMessageStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'work_order_id',
    'recipient_user_id',
    'event',
    'phone',
    'chat_id',
    'payload',
    'status',
    'attempts',
    'last_error',
    'sent_at',
])]
class WaMessage extends Model
{
    use HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WaMessageStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
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
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
