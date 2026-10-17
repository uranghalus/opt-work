<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $nik_employee
 * @property string $nama_employee
 * @property string|null $email
 * @property string|null $number
 * @property string|null $photo_url
 * @property string|null $department_id
 * @property string|null $position_id
 * @property string|null $division_id
 * @property string|null $last_login_ip
 * @property Carbon|null $banned_at
 * @property string|null $optigate_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nik_employee', 'nama_employee', 'email', 'number', 'photo_url', 'department_id', 'position_id', 'division_id', 'last_login_ip', 'banned_at', 'optigate_user_id'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $casts = [
        'banned_at' => 'datetime',
    ];

    /**
     * Find a karyawan by email across all branches, case-insensitively.
     *
     * Platform provisioning (RoleAndPermissionSeeder,
     * app:grant-super-admin-role, LoginProvisioningService) runs before any
     * tenant exists, so the tenant scope is deliberately bypassed — the
     * platform account is not tenant-scoped by definition (ADR-0006).
     */
    public static function withEmailAnyTenant(string $email): ?self
    {
        return static::withoutGlobalScope('tenant')
            ->whereRaw('LOWER(email) = LOWER(?)', [$email])
            ->first();
    }

    /**
     * Whether this karyawan is currently diblokir (record-level block).
     * The portal sync never touches banned_at, so a ban survives syncs.
     */
    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id');
    }
}
