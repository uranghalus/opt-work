<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unit Bisnis (branch/tenant) administration — platform level, no tenant prefix.
 * Tenants are synchronized from Optigate API (Companies).
 * Manual create/update/delete is disabled — use the sync action instead.
 */
class TenantController extends Controller
{
    /** Tenant-scoped tables that keep a branch alive once it has data. */
    private const TENANT_SCOPED = [
        WorkOrder::class => 'work order',
        Employee::class => 'karyawan',
        Department::class => 'department',
        Division::class => 'divisi',
        Position::class => 'posisi',
    ];

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $status = $request->query('status', 'all');

        $tenants = Tenant::query()
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhere('code', 'like', '%'.addcslashes($search, '%_\\').'%')
            ))
            ->when(
                in_array($status, ['active', 'inactive'], true),
                fn (Builder $query) => $query->where('is_active', $status === 'active'),
            )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('settings/tenants/index', [
            'tenants' => $tenants,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        return Inertia::render('settings/tenants/show', [
            'branch' => $tenant,
            'usage' => $this->usageCounts($tenant->id),
        ]);
    }

    /**
     * Run the Optigate tenant sync from the UI. Idempotent, same as the
     * nightly scheduled run; the button only saves typing the command.
     */
    public function sync(): RedirectResponse
    {
        $exitCode = Artisan::call('app:sync-tenants');
        $output = Artisan::output();

        if ($exitCode !== Command::SUCCESS) {
            Log::error('Manual unit bisnis sync from UI failed.', ['output' => $output]);

            return back()->with('error', 'Sinkronisasi gagal. Periksa log untuk detail.');
        }

        $summary = collect(explode("\n", $output))
            ->map(fn (string $line) => trim($line))
            ->first(fn (string $line) => str_contains($line, 'Sinkronisasi selesai'));

        return back()->with('success', $summary ?? 'Sinkronisasi selesai.');
    }

    /**
     * Row counts for the detail page.
     *
     * @return array<string, int>
     */
    private function usageCounts(string $tenantId): array
    {
        $counts = [];

        foreach (self::TENANT_SCOPED as $model => $label) {
            $counts[$label] = $model::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->count();
        }

        return $counts;
    }
}
