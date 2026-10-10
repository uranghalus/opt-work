<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
            'sort' => ['nullable', 'string', 'in:nik_employee,nama_employee,email,number,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * Get the validated search term.
     */
    public function search(): ?string
    {
        return $this->validated('search');
    }

    /**
     * Get the validated per_page value with default.
     */
    public function perPage(): int
    {
        return $this->validated('per_page', 10);
    }

    /**
     * Get the validated sort column with default.
     */
    public function sort(): string
    {
        return $this->validated('sort', 'nama_employee');
    }

    /**
     * Get the validated sort direction with default.
     */
    public function direction(): string
    {
        return $this->validated('direction', 'asc');
    }
}
