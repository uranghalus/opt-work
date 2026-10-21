<?php

namespace App\Models;

use App\AuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Satu baris audit trail durable (grill 2026-10-21, keputusan #4).
 *
 * Append-only: tidak ada permukaan update/delete di aplikasi. Ditulis di
 * dalam transaksi bisnis yang sama dengan aksinya — atomic.
 *
 * TIDAK memakai BelongsToTenant — sama seperti wa_messages: tenant_id adalah
 * data, dan pembaca tidak harus berada di konteks tenant penulis.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property int|null $actor_user_id
 * @property AuditAction $action
 * @property string $auditable_type
 * @property string $auditable_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'actor_user_id',
    'action',
    'auditable_type',
    'auditable_id',
    'before',
    'after',
    'reason',
])]
class AuditLog extends Model
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
            'action' => AuditAction::class,
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Aktor manusia; null = aksi sistem (mis. auto-assign karyawan saat link).
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
