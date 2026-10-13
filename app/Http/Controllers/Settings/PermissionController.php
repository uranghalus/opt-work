<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StorePermissionRequest;
use App\Http\Requests\Settings\UpdatePermissionRequest;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

/**
 * Hak Akses — Izin (permissions). Batch-creates resource+actions in one
 * transaction (port of opti-asset, adapted to the closed per-resource verb
 * map). Deleting is refused while a role still uses the permission (ADR-0004).
 */
class PermissionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('permission.read');

        $perPage = min($request->integer('per_page', 10), 100);

        $search = $request->string('search')->trim()->toString();
        $group = $request->string('group')->trim()->toString();
        $sort = $request->string('sort')->toString();

        [$sortColumn, $sortDirection] = match ($sort) {
            '-name' => ['name', 'desc'],
            default => ['name', 'asc'],
        };

        $permissions = Permission::query()
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($group !== '', fn (Builder $query) => $query->where('name', 'like', addcslashes($group, '%_\\').'.%'))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        $groups = Permission::query()
            ->pluck('name')
            ->map(fn (string $name): string => Str::beforeLast($name, '.'))
            ->countBy()
            ->map(fn (int $count, string $name): array => ['name' => $name, 'count' => $count])
            ->sortBy('name')
            ->values();

        return Inertia::render('settings/permissions/index', [
            'permissions' => $permissions,
            'filters' => [
                'search' => $search,
                'group' => $group,
                'sort' => $sort,
            ],
            'groups' => $groups,
        ]);
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        Gate::authorize('permission.create');

        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            foreach ($validated['actions'] as $action) {
                Permission::firstOrCreate([
                    'name' => $validated['resource'].'.'.$action,
                    'guard_name' => 'web',
                ]);
            }
        });

        return back();
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        Gate::authorize('permission.update');

        $permission->update(['name' => $request->validated('name')]);

        return back();
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        Gate::authorize('permission.delete');

        if ($permission->roles()->exists()) {
            return back()->withErrors(['permission' => 'Izin tidak dapat dihapus karena masih digunakan oleh peran.']);
        }

        $permission->delete();

        return back();
    }
}
