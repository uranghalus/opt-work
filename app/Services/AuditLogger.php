<?php

namespace App\Services;

use App\AuditAction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Penulis audit trail durable (grill 2026-10-21, keputusan #4).
 *
 * Panggil DARI DALAM transaksi bisnis yang sama dengan aksinya: method ini
 * sengaja tidak membuka transaksi sendiri, sehingga kegagalan menulis audit
 * otomatis membatalkan aksi (atomic). Jangan memanggilnya di luar
 * transaksi kecuali aksinya memang tidak penting untuk diatomic-kan.
 *
 * before/after berupa array kecil berisi snapshot field yang relevan — jangan
 * pernah memasukkan secret (password, token) ke dalamnya.
 */
class AuditLogger
{
    public function record(
        AuditAction $action,
        Model $auditable,
        ?array $before = null,
        ?array $after = null,
        ?int $actorId = null,
        ?string $tenantId = null,
        ?string $reason = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorId,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => (string) $auditable->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ]);
    }
}
