<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * R12: pembatalan Work Order — dua kasus:
 * (a) requester / approver department pengaju saat belum rilis (alasan
 *     opsional tapi tercatat);
 * (b) reviewer department tujuan membatalkan sebagai DUPLIKAT saat review —
 *     wajib menunjuk WO duplikatnya (kasus isi sama dari dua department
 *     berbeda).
 */
class CancelWorkOrderRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:2000'],
            'duplicate_of' => [
                'nullable',
                'string',
                // Eksistensi + beda-WO divalidasi di service (butuh konteks
                // tenant + WO aktif).
                Rule::prohibitedIf($this->boolean('is_review_duplicate') && blank($this->input('duplicate_of'))),
            ],
            'is_review_duplicate' => ['nullable', 'boolean'],
        ];
    }
}
