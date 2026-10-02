<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $departments = Department::query()
            ->with(['division', 'hod'])
            ->withCount(['employees', 'positions'])
            ->orderBy('kode_department')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('departments/index', [
            'departments' => $departments,
            'can' => [
                'create' => $request->user()?->can('department.create') ?? false,
                'update' => $request->user()?->can('department.update') ?? false,
                'delete' => $request->user()?->can('department.delete') ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('departments/create', [
            'divisions' => Division::query()->orderBy('nama_division')->get(['id', 'nama_division']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_department' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'kode_department')->where('tenant_id', tenant()?->getTenantKey()),
            ],
            'nama_department' => ['required', 'string', 'max:255'],
            'division_id' => ['nullable', Rule::exists('divisions', 'id')->where('tenant_id', tenant()?->getTenantKey())],
            'hod_user_id' => ['nullable', Rule::exists('users', 'id')],
            'manager_user_id' => ['nullable', Rule::exists('users', 'id')],
        ]);

        Department::create($validated);

        return redirect()
            ->route('departments.index', ['tenant' => tenant()?->getTenantKey()])
            ->with('success', 'Department berhasil dibuat.');
    }

    public function show(Department $department): Response
    {
        return Inertia::render('departments/show', [
            'department' => $department->load(['division', 'hod', 'manager'])->loadCount(['employees', 'positions']),
        ]);
    }

    public function edit(Department $department): Response
    {
        return Inertia::render('departments/edit', [
            'department' => $department,
            'divisions' => Division::query()->orderBy('nama_division')->get(['id', 'nama_division']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'kode_department' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'kode_department')
                    ->where('tenant_id', $department->tenant_id)
                    ->ignore($department),
            ],
            'nama_department' => ['required', 'string', 'max:255'],
            'division_id' => ['nullable', Rule::exists('divisions', 'id')->where('tenant_id', $department->tenant_id)],
            'hod_user_id' => ['nullable', Rule::exists('users', 'id')],
            'manager_user_id' => ['nullable', Rule::exists('users', 'id')],
        ]);

        $department->update($validated);

        return redirect()
            ->route('departments.show', ['tenant' => $department->tenant_id, 'department' => $department])
            ->with('success', 'Department berhasil diperbarui.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return redirect()
            ->route('departments.index', ['tenant' => tenant()?->getTenantKey()])
            ->with('success', 'Department berhasil dihapus.');
    }
}
