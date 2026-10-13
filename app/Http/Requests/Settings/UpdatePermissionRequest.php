<?php

namespace App\Http\Requests\Settings;

use Closure;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    /**
     * Authorization happens in the controller via Gate::authorize('permission.update').
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
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(\.[a-z0-9]+)+$/',
                Rule::unique('permissions', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($this->route('permission')),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $resource = Str::beforeLast((string) $value, '.');
                    $verb = Str::afterLast((string) $value, '.');

                    if (! in_array($verb, RoleAndPermissionSeeder::allowedVerbs($resource), true)) {
                        $allowed = implode(', ', RoleAndPermissionSeeder::allowedVerbs($resource));
                        $fail("Verb izin harus salah satu dari: {$allowed}.");
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Format izin harus berupa resource.tindakan, contoh: division.read.',
            'name.unique' => 'Nama izin sudah dipakai.',
        ];
    }
}
