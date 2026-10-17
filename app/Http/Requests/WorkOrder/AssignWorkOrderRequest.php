<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Assign 1+ karyawan pelaksana to a reviewed work order (WO slice 1,
 * ticket 03). Shape validation here; the target-department and existence
 * checks live in WorkOrderService::assign (they need the work order).
 */
class AssignWorkOrderRequest extends FormRequest
{
    /**
     * Authorization happens in the controller via Gate::authorize('work-order.assign').
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
            'employees' => ['required', 'array', 'min:1', 'max:20'],
            'employees.*' => ['required', 'string', 'distinct', 'exists:employees,id'],
            'assignment_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
