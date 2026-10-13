<?php

namespace App\Http\Requests\Settings;

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    /**
     * Authorization happens in the controller via Gate::authorize('permission.create').
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
            // resource.action names: lowercase segments joined by dots.
            'resource' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(\.[a-z0-9]+)*$/'],
            // New resources are created with the CRUD verbs only; workflow
            // verbs (work-order) exist solely in the seeded catalogue.
            'actions' => ['required', 'array', 'min:1'],
            'actions.*' => ['required', 'string', Rule::in(RoleAndPermissionSeeder::BASE_VERBS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resource.regex' => 'Nama grup harus huruf kecil, contoh: asset.location.',
            'actions.min' => 'Pilih minimal satu tindakan.',
            'actions.*.in' => 'Tindakan harus salah satu dari: read, create, update, delete.',
        ];
    }
}
