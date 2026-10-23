<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * FR-3.1–3.3: Work Data — satu record auto-generated saat WO closed,
 * snapshot data turunan WO (no kerja, department, jam pengerjaan, status
 * akhir, gambar sebelum/sesudah, pelaksana). Append-only: analitik
 * post-mortem boleh ditulis SATU kali (finalize), setelah itu beku
 * selamanya — tidak ada edit, tidak ada delete.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_order_id
 * @property string $no_kerja
 * @property string|null $department_id
 * @property string $status_pekerjaan
 * @property Carbon|null $tgl_mulai_aktual
 * @property Carbon|null $tgl_selesai_aktual
 * @property string|float|null $jam_pengerjaan
 * @property array<int, string>|null $gambar_sebelum
 * @property array<int, string>|null $gambar_sesudah
 * @property string|null $prediksi_penyebab
 * @property string|null $tindakan
 * @property string|null $hasil_kesimpulan
 * @property string|null $saran_solusi
 * @property int|null $pic_lead_user_id
 * @property Carbon|null $finalized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'work_order_id',
    'no_kerja',
    'department_id',
    'status_pekerjaan',
    'tgl_mulai_aktual',
    'tgl_selesai_aktual',
    'jam_pengerjaan',
    'gambar_sebelum',
    'gambar_sesudah',
    'prediksi_penyebab',
    'tindakan',
    'hasil_kesimpulan',
    'saran_solusi',
    'pic_lead_user_id',
    'finalized_at',
])]
class WorkData extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $table = 'work_data';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tgl_mulai_aktual' => 'date',
            'tgl_selesai_aktual' => 'date',
            'jam_pengerjaan' => 'decimal:2',
            'gambar_sebelum' => 'array',
            'gambar_sesudah' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function picLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_lead_user_id');
    }

    /**
     * @return HasMany<WorkDataPekerja, $this>
     */
    public function pekerja(): HasMany
    {
        return $this->hasMany(WorkDataPekerja::class);
    }
}
