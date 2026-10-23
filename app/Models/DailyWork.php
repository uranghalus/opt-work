<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FR-5.3–5.4: Instance tugas harian satu karyawan pada satu tanggal —
 * gabungan template ter-materialize (source=template) dan tugas tambahan
 * HOD (source=adhoc, FR-5.2). Status pekerjaan (belum/proses/selesai) dan
 * lokasi diisi karyawan pemilik item (FR-5.4).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property Carbon $work_date
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property string $status
 * @property Carbon|null $done_at
 * @property string $source
 * @property string|null $template_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'employee_id',
    'work_date',
    'title',
    'description',
    'location',
    'status',
    'done_at',
    'source',
    'template_id',
    'created_by',
])]
class DailyWork extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $casts = [
        'work_date' => 'date',
        'done_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DailyWorkTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
