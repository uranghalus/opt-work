<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class LinkAkunRequest extends FormRequest
{
    /**
     * Authorization happens in the controller via Gate::authorize('akun.update').
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The account must be resolved against the employee's tenant in the
     * controller; a plain exists rule would allow cross-cabang targets.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Pilih akun yang akan ditautkan.',
            'user_id.integer' => 'Akun yang dipilih tidak valid.',
            'user_id.min' => 'Akun yang dipilih tidak valid.',
        ];
    }
}
