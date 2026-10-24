<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * R4: penolakan gerbang approval department pengaju — alasan wajib dan
 * wajib disimpan (histori + tracker requester).
 */
class RejectWorkOrderRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan penolakan wajib diisi.',
        ];
    }
}
