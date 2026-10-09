<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Branch (tenant) administration — platform level, no tenant prefix.
 * Tenants are synchronized from Optigate API (Companies).
 * Manual create/update/delete is disabled — use the sync command instead.
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

    public function index(): Response
    {
        $tenants = Tenant::query()
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('settings/tenants/index', [
            'tenants' => $tenants,
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
