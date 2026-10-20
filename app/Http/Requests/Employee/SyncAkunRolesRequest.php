<?php

namespace App\Http\Requests\Employee;

use App\Models\Role;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SyncAkunRolesRequest extends FormRequest
{
    /**
     * Authorization happens in the controller via Gate::authorize('akun.update').
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The submitted set replaces the akun's current roles, so it must be
     * present (empty array = revoke everything allowed). Validation reports a
     * single error under the `roles` key — the dialog surfaces exactly that
     * destructive alert (ADR-0005). Roles are loaded from the database so
     * custom roles created through Hak Akses are assignable; super_admin is
     * never assignable here (ADR-0005 decision 4) even though it exists in
     * the same table.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $assignable = Role::query()
            ->where('name', '!=', 'super_admin')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return [
            'roles' => [
                'present',
                'array',
                function (string $attribute, mixed $value, Closure $fail) use ($assignable): void {
                    foreach ((array) $value as $role) {
                        if ($role === 'super_admin') {
                            $fail('Peran super_admin tidak dapat diberikan lewat sini.');

                            return;
                        }

                        if (! is_string($role) || ! in_array($role, $assignable, true)) {
                            $fail('Peran '.(is_string($role) ? $role : 'tidak valid').' tidak ditemukan.');

                            return;
                        }
                    }
                },
            ],
        ];
    }
}
