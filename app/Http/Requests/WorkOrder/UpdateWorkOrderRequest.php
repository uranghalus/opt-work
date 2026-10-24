<?php

namespace App\Http\Requests\WorkOrder;

use App\WorkOrderCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * R12: revisi WO yang ditolak department pengaju, sebelum resubmit —
 * hanya requester, hanya pada status RejectedByDept (dipaksa di service).
 */
class UpdateWorkOrderRequest extends FormRequest
{
    /**
     * Authorization happens in the service (WorkOrderRequestService).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::enum(WorkOrderCategory::class)],
            'target_department_id' => ['required', 'uuid', Rule::exists('departments', 'id')],
        ];
    }
}
