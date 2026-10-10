<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        $departments = Department::query()
            ->with(['division', 'hod'])
            ->withCount(['employees', 'positions'])
            ->orderBy('kode_department')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('departments/index', [
            'departments' => $departments,
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
            ->route('departments.index', ['tenant' => tenant()?->code])
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
            ->route('departments.show', ['tenant' => $department->tenant->code, 'department' => $department])
            ->with('success', 'Department berhasil diperbarui.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return redirect()
            ->route('departments.index', ['tenant' => tenant()?->code])
            ->with('success', 'Department berhasil dihapus.');
    }

    /**
     * Run the Optigate department sync from the UI. Idempotent, same as the
     * scheduled command; the button only saves typing the command.
     */
    public function sync(): RedirectResponse
    {
        $tenant = tenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return back()->with('error', 'Tidak ada tenant aktif.');
        }

        $exitCode = Artisan::call('app:sync-departments', ['--tenant' => $tenantId]);
        $output = Artisan::output();

        if ($exitCode !== Command::SUCCESS) {
            Log::error('Manual department sync from UI failed.', ['output' => $output, 'tenant_id' => $tenantId]);

            return back()->with('error', 'Sinkronisasi gagal. Periksa log untuk detail.');
        }

        $summary = collect(explode("\n", $output))
            ->map(fn (string $line) => trim($line))
            ->first(fn (string $line) => str_contains($line, 'Sinkronisasi selesai'));

        return back()->with('success', $summary ?? 'Sinkronisasi selesai.');
    }
}
