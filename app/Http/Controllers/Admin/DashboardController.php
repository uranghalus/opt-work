<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Division;
use App\Models\Department;
use App\Models\Position;
use App\Models\Employee;
use App\Models\WorkOrder;
use App\Services\TenantAccess;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected TenantAccess $tenantAccess,
    ) {}

    public function index(): Response
    {
        $user = auth()->user();

        // Get operable tenants for the current user
        $tenants = $this->tenantAccess->operableTenants($user);

        $tenantData = $tenants->map(function (Tenant $tenant) {
            // In single-database mode, temporarily make tenant current to query scoped models
            $tenant->makeCurrent();

            $data = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'code' => $tenant->code,
                'is_active' => (bool) $tenant->is_active,
                'stats' => [
                    'divisions' => Division::count(),
                    'departments' => Department::count(),
                    'positions' => Position::count(),
                    'employees' => Employee::count(),
                    'work_orders' => WorkOrder::count(),
                    'open_work_orders' => WorkOrder::where('status', '!=', 'closed')->count(),
                ],
            ];

            // Reset current tenant
            Tenant::forgetCurrent();

            return $data;
        });

        return Inertia::render('admin/dashboard', [
            'tenants' => $tenantData,
            'canManageTenants' => $this->tenantAccess->canManageTenants(auth()->user()),
        ]);
    }
}