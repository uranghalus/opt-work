<?php

namespace App\Http\Requests\Employee;

use Closure;
use Database\Seeders\RoleAndPermissionSeeder;
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
     * destructive alert (ADR-0005). super_admin is never assignable here
     * (ADR-0005 decision 4): it exists in the roles table, so an exists rule
     * would pass it; the closure rejects it explicitly regardless of who asks.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $assignable = array_values(array_diff(
            RoleAndPermissionSeeder::SYSTEM_ROLES,
            ['super_admin'],
        ));

        return [
            'roles' => [
                'present',
                'array',
                function (string $attribute, mixed $value, Closure $fail) use ($assignable): void {
                    foreach ((array) $value as $role) {
                        if (! is_string($role) || ! in_array($role, $assignable, true)) {
                            $fail(
                                $role === 'super_admin'
                                    ? 'Peran super_admin tidak dapat diberikan lewat sini.'
                                    : 'Peran '.$role.' tidak ditemukan.'
                            );

                            return;
                        }
                    }
                },
            ],
        ];
    }
}
