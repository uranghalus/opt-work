<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Role;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

/**
 * Hak Akses — Peran (roles). Global surface at /hak-akses, outside the
 * {tenant} prefix; every action is permission-gated (ADR-0004). System roles
 * cannot be renamed or deleted, and the super_admin role's permissions are
 * locked because its Gate bypass makes them decorative.
 */
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('role.read');

        $search = $request->string('search')->trim()->toString();
        $sort = $request->string('sort')->toString();

        [$sortColumn, $sortDirection] = match ($sort) {
            'name' => ['name', 'asc'],
            '-name' => ['name', 'desc'],
            'users' => ['users_count', 'desc'],
            default => ['created_at', 'desc'],
        };

        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate(min($request->integer('per_page', 10), 100))
            ->withQueryString();

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
            ],
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        Gate::authorize('role.create');

        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);

        if ($request->validated('permissions') !== null) {
            $role->syncPermissions($request->validated('permissions'));
        }

        return back();
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        Gate::authorize('role.update');

        $name = $request->validated('name');

        if ($role->is_system && $name !== $role->name) {
            return back()->withErrors(['name' => 'Peran sistem tidak dapat diubah namanya.']);
        }

        $role->update(['name' => $name]);

        if ($request->validated('permissions') !== null) {
            $role->syncPermissions($request->validated('permissions'));
        }

        return back();
    }

    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('role.delete');

        if ($role->is_system) {
            return back()->withErrors(['role' => 'Peran sistem tidak dapat dihapus.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Peran masih dipakai oleh pengguna dan tidak dapat dihapus.']);
        }

        $role->delete();

        return back();
    }

    public function syncPermissions(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('role.update');

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ], [
            'permissions.*.exists' => 'Izin :values tidak ditemukan.',
        ]);

        if ($role->name === 'super_admin') {
            return back()->withErrors(['permissions' => 'Izin super_admin tidak dapat diubah karena peran ini melewati semua gate.']);
        }

        $role->syncPermissions($validated['permissions']);

        return back();
    }

    /**
     * The full catalogue grouped by resource prefix, for the permission Sheet.
     *
     * @return list<array{group: string, permissions: list<array{id: int|string, name: string}>}>
     */
    private function permissionGroups(): array
    {
        $groups = [];

        foreach (Permission::query()->orderBy('name')->get(['id', 'name']) as $permission) {
            $group = Str::beforeLast($permission->name, '.');

            $groups[$group]['group'] = $group;
            $groups[$group]['permissions'][] = ['id' => $permission->id, 'name' => $permission->name];
        }

        return array_values($groups);
    }
}
