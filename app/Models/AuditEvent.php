<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Satu baris audit per aksi sensitif (fase 1: akun.link/unlink,
 * employee.ban/unban, akun.roles.sync). Append-only: aplikasi tidak pernah
 * meng-update atau menghapus baris ini (kontrak di docblock, tanpa metode
 * mutasi selain pembuatan via record()).
 *
 * TIDAK memakai BelongsToTenant — lihat docblock migration: tenant_id
 * adalah data (pola wa_messages), supaya baris tetap terbaca tanpa konteks
 * tenant saat ini.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property int|null $actor_user_id
 * @property string|null $actor_email
 * @property string $subject_type
 * @property string $subject_id
 * @property string $action
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $context
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'actor_user_id',
    'actor_email',
    'subject_type',
    'subject_id',
    'action',
    'before',
    'after',
    'ip_address',
    'user_agent',
    'context',
])]
class AuditEvent extends Model
{
    use HasUuids;

    /**
     * Tulis satu baris audit. Sengaja TIDAK membuka transaksi sendiri:
     * pemanggil wajib berada di dalam DB::transaction yang sama dengan
     * aksi yang diaudit, supaya aksi ikut ter-rollback bila catatan audit
     * gagal ditulis (atomic — pola WorkOrderEvent::record).
     *
     * Actor dilewatkan eksplisit (bukan fallback auth()); actor null
     * berarti aksi sistem. actor_email disimpan sebagai snapshot karena
     * FK-nya nullOnDelete — jejak tetap terbaca setelah user dihapus.
     *
     * @param  array<string, mixed>|null  $before  snapshot sebelum aksi
     * @param  array<string, mixed>|null  $after  snapshot sesudah aksi
     * @param  array<string, mixed>  $context  data pendukung tambahan
     */
    public static function record(
        Model $subject,
        ?User $actor,
        string $action,
        ?array $before = null,
        ?array $after = null,
        array $context = [],
    ): self {
        // Subject fase 1 (Employee) selalu membawa tenant_id; fallback ke
        // konteks request bila subject masa depan tidak memilikinya.
        $tenantId = $subject->getAttribute('tenant_id')
            ?? Tenant::current()?->id;

        return static::create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actor?->getKey(),
            'actor_email' => $actor?->email,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'context' => $context === [] ? null : $context,
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
            'before' => 'array',
            'after' => 'array',
            'context' => 'array',
        ];
    }
}
