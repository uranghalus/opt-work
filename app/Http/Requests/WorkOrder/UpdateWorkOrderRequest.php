<?php

namespace App\Http\Requests\WorkOrder;

use App\WorkOrderCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Perbarui field inti work order (judul, deskripsi, kategori, department
 * tujuan, lokasi, jadwal, dan field pendukung). Tidak mencakup lampiran
 * (ganti lampiran = v2).
 *
 * Otorisasi dilakukan di controller via Gate::authorize('work-order.update');
 * authorize() di FormRequest mengembalikan true (pola AssignWorkOrderRequest).
 */
class UpdateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category' => ['required', Rule::enum(WorkOrderCategory::class)],
            'target_department_id' => [
                'required',
                // FR-4.1: hanya department AKTIF milik cabang (tenant) ini.
                Rule::exists('departments', 'id')
                    ->where('tenant_id', tenant()?->getTenantKey())
                    ->where('is_active', true),
            ],
            'requested_schedule_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
                Rule::prohibitedIf(
                    $this->enum('category', WorkOrderCategory::class)?->allowsScheduling() === false,
                ),
            ],
            'priority' => ['nullable', 'string', 'max:255'],
            'personnel_count' => ['nullable', 'integer', 'min:1'],
            'damage_type' => ['nullable', 'string', 'max:255'],
        ];
    }
}
