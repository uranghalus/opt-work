<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $nomor_wo
 * @property int $requester_user_id
 * @property string $target_department_id
 * @property WorkOrderCategory $category
 * @property string $title
 * @property string $description
 * @property string|null $location
 * @property array<int, string>|null $attachments
 * @property WorkOrderStatus $status
 * @property Carbon|null $released_at
 * @property Carbon|null $requested_schedule_date
 * @property string|null $hod_action
 * @property Carbon|null $scheduled_date
 * @property Carbon|null $assigned_at
 * @property string|null $hod_notes
 * @property list<string>|null $assigned_employees
 * @property string|null $assignment_notes
 * @property Carbon|null $deadline_date
 * @property Carbon|null $escalation_h3_sent_at
 * @property Carbon|null $escalation_h5_sent_at
 * @property Carbon|null $escalation_h6_sent_at
 * @property bool $is_escalated
 * @property int $extend_count
 * @property string|null $extend_reason
 * @property Carbon|null $extended_at
 * @property string|null $request_rejection_reason
 * @property string|null $result_notes
 * @property array<int, string>|null $result_attachments
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
    'location',
    'attachments',
    'status',
    'requested_schedule_date',
    'hod_action',
    'scheduled_date',
    'assigned_at',
    'hod_notes',
    'assigned_employees',
    'assignment_notes',
    'deadline_date',
    'escalation_h3_sent_at',
    'escalation_h5_sent_at',
    'escalation_h6_sent_at',
    'is_escalated',
    'extend_count',
    'extend_reason',
    'extended_at',
    'released_at',
    'request_rejection_reason',
    'result_notes',
    'result_attachments',
])]
class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected static function booted(): void
    {
        // ADR-0011 (R8): WO yang lahir tanpa melewati gerbang approval —
        // alur lama, factory, dan fixtures — langsung dianggap dirilis ke
        // department tujuan. Gerbang hanya hidup di
        // WorkOrderService::create() lintas-department, yang men-set
        // released_at secara eksplisit (null selama menunggu persetujuan).
        static::creating(function (WorkOrder $workOrder): void {
            if (! array_key_exists('released_at', $workOrder->getAttributes())) {
                $workOrder->released_at = now();
            }
        });
    }

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
            'released_at' => 'datetime',
            'requested_schedule_date' => 'date',
            'scheduled_date' => 'date',
            'assigned_at' => 'datetime',
            'assigned_employees' => 'array',
            'deadline_date' => 'date',
            'escalation_h3_sent_at' => 'datetime',
            'escalation_h5_sent_at' => 'datetime',
            'escalation_h6_sent_at' => 'datetime',
            'is_escalated' => 'boolean',
            'extended_at' => 'datetime',
            'result_attachments' => 'array',
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

    /**
     * D1 — pihak yang berhak melihat daftar WO, dipakai index + timeline
     * bersama: Requester, reviewer department target (hod_user_id /
     * manager_user_id), DAN pelaksana yang tercantum di assigned_employees —
     * pelaksana lintas department sebelumnya hanya menjangkau WO-nya lewat
     * link notifikasi (memblokir FR-1.7 submit).
     *
     * Semua klausul or-nya hidup di satu grup where agar men AND dengan
     * filter lain milik pemanggil (mis. WHERE scheduled_date BETWEEN ...).
     *
     * @param  Collection<int|string, mixed>  $departmentIds  id department yang
     *                                                        dipegang user
     *                                                        (hod/manager).
     *                                                        Boleh kosong.
     */
    public function scopeVisibleFor(Builder $query, User $user, Collection $departmentIds): void
    {
        $query->where(function ($group) use ($user, $departmentIds): void {
            $group->where('requester_user_id', $user->getKey());

            if ($departmentIds->isNotEmpty()) {
                $group->orWhere(function ($released) use ($departmentIds): void {
                    // R8: department tujuan tidak melihat WO yang belum
                    // dirilis dari department pengaju (gerbang approval
                    // lintas-department) — termasuk di daftar, hitungan,
                    // dan pencarian.
                    $released->whereIn('target_department_id', $departmentIds)
                        ->whereNotNull('released_at');
                });
            }

            if ($user->employee_id !== null) {
                $group->orWhereJsonContains('assigned_employees', (string) $user->employee_id);
            }
        });
    }

    /**
     * @return HasMany<WorkOrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(WorkOrderEvent::class);
    }

    /**
     * Timer durasi kerja per pelaksana (ADR-0011, R16–R18).
     *
     * @return HasMany<WorkTimeLog, $this>
     */
    public function workTimeLogs(): HasMany
    {
        return $this->hasMany(WorkTimeLog::class);
    }

    /**
     * @return HasMany<ExtendRequest, $this>
     */
    public function extendRequests(): HasMany
    {
        return $this->hasMany(ExtendRequest::class);
    }

    /**
     * FR-2.4: detail jadwal per WO Terjadwal — dibuat saat review
     * 'schedule'; null untuk WO non-terjadwal.
     *
     * @return HasOne<WorkPlanning, $this>
     */
    public function planning(): HasOne
    {
        return $this->hasOne(WorkPlanning::class);
    }

    /**
     * Resolve route binding for implicit model binding.
     * The route parameter is `work_order` (snake_case from resource route).
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        if ($field === 'work_order' || $field === null) {
            return $this->where('id', $value)->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
