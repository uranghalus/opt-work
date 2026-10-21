<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\TenantWaSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pasangan sesi WAHA ↔ tenant: satu baris per tenant yang menunjuk sesi/nomor
 * pengirim milik tenant itu di instance WAHA bersama (ADR-0009 amendmen —
 * sesi global diganti per-tenant).
 *
 * Berbeda dari `wa_messages`, tabel ini memakai BelongsToTenant: konfigurasi
 * memang milik tenant dan hanya dibaca dari UI request (scoped otomatis) atau
 * dari command/job yang sengaja memakai `activeFor()`/`withoutGlobalScope`.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $session_name
 * @property string|null $phone_number
 * @property TenantWaSessionStatus $status
 * @property Carbon|null $last_connected_at
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'session_name',
    'phone_number',
    'status',
    'last_connected_at',
    'last_synced_at',
])]
class TenantWaSession extends Model
{
    use BelongsToTenant, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantWaSessionStatus::class,
            'last_connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * SATU-satunya pintu resolusi sesi pengirim (dipakai channel, job, dan
     * command ping): selalu terikat `tenant_id` eksplisit dan tanpa scope
     * tenant, karena job/command berjalan tanpa konteks tenant current.
     *
     * Pesan tenant TIDAK PERNAH jatuh ke sesi tenant lain maupun sesi global —
     * tidak ada jalur fallback sama sekali (bukan null → gagal tercatat).
     */
    public static function activeFor(?string $tenantId): ?self
    {
        if (blank($tenantId)) {
            return null;
        }

        return static::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('status', TenantWaSessionStatus::Working->value)
            ->first();
    }

    /** Baris milik tenant tertentu tanpa mengandalkan konteks tenant current. */
    public static function forTenant(?string $tenantId): ?self
    {
        if (blank($tenantId)) {
            return null;
        }

        return static::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->first();
    }
}
