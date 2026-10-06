<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        $employees = Employee::query()
            ->with(['department', 'position', 'division'])
            ->orderBy('nama_employee')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('employees/index', [
            'employees' => $employees,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('employees/create', [
            'divisions' => Division::query()->orderBy('nama_division')->get(['id', 'nama_division']),
            'departments' => Department::query()->orderBy('kode_department')->get(['id', 'kode_department', 'nama_department']),
            'positions' => Position::query()->orderBy('nama_position')->get(['id', 'nama_position']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nik_employee' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('employees', 'nik_employee')->where('tenant_id', tenant()?->getTenantKey()),
            ],
            'nama_employee' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'email')->where('tenant_id', tenant()?->getTenantKey()),
            ],
            'number' => ['nullable', 'string', 'max:255'],
            'photo_url' => ['nullable', 'string', 'max:2048'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', tenant()?->getTenantKey())],
            'position_id' => ['nullable', Rule::exists('positions', 'id')->where('tenant_id', tenant()?->getTenantKey())],
            'division_id' => ['nullable', Rule::exists('divisions', 'id')->where('tenant_id', tenant()?->getTenantKey())],
        ]);

        Employee::create($validated);

        return redirect()
            ->route('employees.index', ['tenant' => tenant()?->getTenantKey()])
            ->with('success', 'Karyawan berhasil dibuat.');
    }

    public function show(Employee $employee): Response
    {
        return Inertia::render('employees/show', [
            'employee' => $employee->load(['department', 'position', 'division']),
        ]);
    }

    public function edit(Employee $employee): Response
    {
        return Inertia::render('employees/edit', [
            'employee' => $employee,
            'divisions' => Division::query()->orderBy('nama_division')->get(['id', 'nama_division']),
            'departments' => Department::query()->orderBy('kode_department')->get(['id', 'kode_department', 'nama_department']),
            'positions' => Position::query()->orderBy('nama_position')->get(['id', 'nama_position']),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'nik_employee' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('employees', 'nik_employee')
                    ->where('tenant_id', $employee->tenant_id)
                    ->ignore($employee),
            ],
            'nama_employee' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'email')
                    ->where('tenant_id', $employee->tenant_id)
                    ->ignore($employee),
            ],
            'number' => ['nullable', 'string', 'max:255'],
            'photo_url' => ['nullable', 'string', 'max:2048'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $employee->tenant_id)],
            'position_id' => ['nullable', Rule::exists('positions', 'id')->where('tenant_id', $employee->tenant_id)],
            'division_id' => ['nullable', Rule::exists('divisions', 'id')->where('tenant_id', $employee->tenant_id)],
        ]);

        $employee->update($validated);

        return redirect()
            ->route('employees.show', ['tenant' => $employee->tenant_id, 'employee' => $employee])
            ->with('success', 'Karyawan berhasil diperbarui.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()
            ->route('employees.index', ['tenant' => tenant()?->getTenantKey()])
            ->with('success', 'Karyawan berhasil dihapus.');
    }
}
