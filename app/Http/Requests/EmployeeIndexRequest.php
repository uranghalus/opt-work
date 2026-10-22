<?php

namespace App\Http\Requests;

use App\Queries\EmployeeQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'sort' => ['nullable', 'string', Rule::in(EmployeeQuery::SORTABLE_COLUMNS)],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'department_id' => ['nullable', 'uuid'],
            'division_id' => ['nullable', 'uuid'],
<<<<<<< HEAD
            'status' => ['nullable', 'string', Rule::in(['active', 'banned'])],
            'akun' => ['nullable', 'string', Rule::in(['linked', 'unlinked'])],
=======
            'position_id' => ['nullable', 'uuid'],
            'has_account' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:all,active,banned'],
        ];
    }

    /**
     * The validated organization/account/status filters, normalised for
     * EmployeeQuery (null = no filter, status defaults to "all").
     *
     * @return array{department_id: ?string, division_id: ?string, position_id: ?string, has_account: ?bool, status: string}
     */
    public function filters(): array
    {
        $hasAccount = $this->validated('has_account');

        return [
            'department_id' => $this->validated('department_id'),
            'division_id' => $this->validated('division_id'),
            'position_id' => $this->validated('position_id'),
            'has_account' => $hasAccount === null ? null : filter_var($hasAccount, FILTER_VALIDATE_BOOLEAN),
            'status' => $this->validated('status', 'all'),
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
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
     * Filter by department (organizational scope). UUID — departments.id.
     */
    public function departmentId(): ?string
    {
        return $this->validated('department_id');
    }

    /**
     * Filter by division (organizational scope). UUID — divisions.id.
     */
    public function divisionId(): ?string
    {
        return $this->validated('division_id');
    }

    /**
     * Filter by ban status: active = not banned, banned = banned_at set.
     */
    public function status(): ?string
    {
        return $this->validated('status');
    }

    /**
     * Filter by akun link: linked = has a linked user account, unlinked = not.
     */
    public function akun(): ?string
    {
        return $this->validated('akun');
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
