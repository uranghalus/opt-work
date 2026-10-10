<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeIndexRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Queries\EmployeeQuery;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(EmployeeIndexRequest $request): Response
    {
        $query = (new EmployeeQuery())->build(
            $request->search(),
            $request->sort(),
            $request->direction()
        );

        $perPage = $request->perPage();

        $employees = $query->paginate($perPage)
            ->withQueryString()
            ->through(fn (Employee $employee) => EmployeeResource::make($employee));

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => [
                'search' => $request->search() ?? '',
                'per_page' => $perPage,
                'sort' => $request->sort(),
                'direction' => $request->direction(),
            ],
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
            ->route('employees.index', ['tenant' => tenant()?->code])
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
            ->route('employees.show', ['tenant' => $employee->tenant->code, 'employee' => $employee])
            ->with('success', 'Karyawan berhasil diperbarui.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()
            ->route('employees.index', ['tenant' => tenant()?->code])
            ->with('success', 'Karyawan berhasil dihapus.');
    }

    /**
     * Run the Optigate employee sync from the UI. Idempotent, same as the
     * scheduled command; the button only saves typing the command.
     */
    public function sync(): RedirectResponse
    {
        $tenant = tenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return back()->with('error', 'Tidak ada tenant aktif.');
        }

        $exitCode = Artisan::call('app:sync-employees', ['--tenant' => $tenantId]);
        $output = Artisan::output();

        if ($exitCode !== Command::SUCCESS) {
            Log::error('Manual employee sync from UI failed.', ['output' => $output, 'tenant_id' => $tenantId]);

            return back()->with('error', 'Sinkronisasi gagal. Periksa log untuk detail.');
        }

        $summary = collect(explode("\n", $output))
            ->map(fn (string $line) => trim($line))
            ->first(fn (string $line) => str_contains($line, 'Sinkronisasi selesai'));

        return back()->with('success', $summary ?? 'Sinkronisasi selesai.');
    }
}
