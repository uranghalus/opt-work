<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DivisionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('division.read');

        $tenant = tenant();

        $divisions = Division::query()
            ->with('department')
            ->orderBy('nama_division')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('divisions/index', [
            'divisions' => $divisions,
            'last_synced_at' => $tenant?->last_division_synced_at,
        ]);
    }

    /**
     * Divisions are resolved manually against the route's tenant: implicit
     * binding runs before tenancy bootstraps, so the BelongsToTenant scope
     * cannot be trusted at bind time (same pattern as DepartmentController
     * and EmployeeController).
     */
    protected function resolveDivisionFromRoute(Request $request): Division
    {
        $tenant = Tenant::whereCode((string) $request->route('tenant'))->first();
        abort_unless($tenant !== null, 404);

        return Division::withoutGlobalScope('tenant')
            ->where('id', (string) $request->route('division'))
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
    }

    public function show(Request $request): Response
    {
        Gate::authorize('division.read');

        $division = $this->resolveDivisionFromRoute($request);

        return Inertia::render('divisions/show', [
            'division' => $division->load('department'),
        ]);
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

        // Refresh so the index page picks up the updated last_division_synced_at.
        $tenant->refresh();

        $summary = collect(explode("\n", $output))
            ->map(fn (string $line) => trim($line))
            ->first(fn (string $line) => str_contains($line, 'Sinkronisasi selesai'));

        return back()->with('success', $summary ?? 'Sinkronisasi selesai.');
    }
}
