<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Branch record.
 *
 * Extends the stock tenancy model with real columns for branch identity. The
 * stock model stores custom attributes in a `data` JSON column, which cannot be
 * queried with a plain `WHERE` and — because `VirtualColumn` discards the `data`
 * attribute passed to `create()` — never actually persisted.
 *
 * `id` is the human-readable slug and the URL segment (`hq`, `plant-1`); it is
 * the primary key and the target of every `tenant_id` foreign key, so it is not
 * changed after creation. See ADR 0002 and
 * `.scratch/tenancy-reconfig/issues/03-tenant-dedicated-columns.md`.
 *
 * @property string $id
 * @property string $name
 * @property string|null $code
 * @property bool $is_active
 * @property array<string, mixed>|null $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Tenant extends BaseTenant
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * Attributes stored as real columns rather than in the `data` JSON blob.
     *
     * `id` must be listed: the base model reads it as a virtual attribute
     * otherwise, and primary-key writes would be stripped on insert.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'code', 'is_active'];
    }

    /**
     * Default the branch name to its slug.
     *
     * Keeps `Tenant::create(['id' => 'hq'])` — the shape used by test helpers and
     * any future script — valid against the non-null `name` column, instead of
     * forcing every call site to repeat what the slug already implies.
     */
    protected static function booted(): void
    {
        static::creating(function (self $tenant): void {
            if (blank($tenant->name)) {
                $tenant->name = (string) $tenant->getKey();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Human label: the branch name, falling back to the slug.
     */
    public function label(): string
    {
        return $this->name ?: $this->getTenantKey();
    }
}
