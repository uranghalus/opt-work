<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The HOD execution decision (WO slice 1, ticket 02). Errors report under
 * their own keys: `hod_action` carries both the enum and the Urgent by
 * Accident guard (server-side — the UI merely hides the option).
 */
class ReviewWorkOrderRequest extends FormRequest
{
    /**
     * Authorization happens in the controller via Gate::authorize('work-order.review').
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
            'action' => ['required', 'in:execute_immediately,schedule'],
            'scheduled_date' => [
                'required_if:action,schedule',
                // Mirror aturan store: WO eksekusi langsung tidak punya jadwal —
                // tanggal liar akan membuat WO muncul di Timeline padahal
                // statusnya HodApproved (whereNotNull scheduled_date).
                Rule::prohibitedIf($this->input('action') === 'execute_immediately'),
                'nullable',
                'date',
                'after_or_equal:today',
            ],
            'hod_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
