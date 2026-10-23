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
 * FR-5.1: Template tugas rutin harian per karyawan — dikelola HOD (OQ-4,
 * opsi A). `weekdays` menentukan hari berulang (0=Minggu..6=Sabtu); item
 * aktif di-materialize menjadi baris DailyWork pada tanggal yang cocok.
 * Nonaktif (`is_active=false`) berhenti muncul untuk tanggal baru; instance
 * terlanjur-materialize tetap menjadi riwayat.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $title
 * @property string|null $description
 * @property list<int> $weekdays
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'employee_id',
    'title',
    'description',
    'weekdays',
    'is_active',
    'created_by',
])]
class DailyWorkTemplate extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $casts = [
        'weekdays' => 'array',
        'is_active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
