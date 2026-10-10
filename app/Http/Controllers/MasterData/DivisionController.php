<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DivisionController extends Controller
{
    public function index(): Response
    {
        $divisions = Division::query()
            ->withCount('departments')
            ->orderBy('nama_division')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('divisions/index', [
            'divisions' => $divisions,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('divisions/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_division' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('divisions', 'kode_division')->where('tenant_id', tenant()?->getTenantKey()),
            ],
            'nama_division' => ['required', 'string', 'max:255'],
        ]);

        Division::create($validated);

        return redirect()
            ->route('divisions.index', ['tenant' => tenant()?->code])
            ->with('success', 'Divisi berhasil dibuat.');
    }

    public function show(Division $division): Response
    {
        return Inertia::render('divisions/show', [
            'division' => $division->loadCount('departments'),
        ]);
    }

    public function edit(Division $division): Response
    {
        return Inertia::render('divisions/edit', [
            'division' => $division,
        ]);
    }

    public function update(Request $request, Division $division): RedirectResponse
    {
        $validated = $request->validate([
            'kode_division' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('divisions', 'kode_division')
                    ->where('tenant_id', $division->tenant_id)
                    ->ignore($division),
            ],
            'nama_division' => ['required', 'string', 'max:255'],
        ]);

        $division->update($validated);

        return redirect()
            ->route('divisions.show', ['tenant' => $division->tenant->code, 'division' => $division])
            ->with('success', 'Divisi berhasil diperbarui.');
    }

    public function destroy(Division $division): RedirectResponse
    {
        $division->delete();

        return redirect()
            ->route('divisions.index', ['tenant' => tenant()?->code])
            ->with('success', 'Divisi berhasil dihapus.');
    }

    /**
     * Run the Optigate division sync from the UI. Idempotent, same as the
     * scheduled command; the button only saves typing the command.
     */
    public function sync(): RedirectResponse
    {
        $tenant = tenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return back()->with('error', 'Tidak ada tenant aktif.');
        }

        $exitCode = Artisan::call('app:sync-divisions', ['--tenant' => $tenantId]);
        $output = Artisan::output();

        if ($exitCode !== Command::SUCCESS) {
            Log::error('Manual division sync from UI failed.', ['output' => $output, 'tenant_id' => $tenantId]);

            return back()->with('error', 'Sinkronisasi gagal. Periksa log untuk detail.');
        }

        $summary = collect(explode("\n", $output))
            ->map(fn (string $line) => trim($line))
            ->first(fn (string $line) => str_contains($line, 'Sinkronisasi selesai'));

        return back()->with('success', $summary ?? 'Sinkronisasi selesai.');
    }
}
