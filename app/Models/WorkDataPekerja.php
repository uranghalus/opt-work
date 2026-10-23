<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris pelaksana pada sebuah Work Data — snapshot dari penugasan WO saat
 * close (nama tetap tersimpan meski employee dihapus).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_data_id
 * @property string|null $employee_id
 * @property string $nama_employee
 * @property string $role_pekerja
 * @property string|null $catatan
 */
#[Fillable([
    'work_data_id',
    'employee_id',
    'nama_employee',
    'role_pekerja',
    'catatan',
])]
class WorkDataPekerja extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $table = 'work_data_pekerja';

    /**
     * @return BelongsTo<WorkData, $this>
     */
    public function workData(): BelongsTo
    {
        return $this->belongsTo(WorkData::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
