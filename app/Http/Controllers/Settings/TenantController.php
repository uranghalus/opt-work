<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Branch (tenant) administration — platform level, no tenant prefix.
 *
 * Registered centrally so it cannot be captured by `/{tenant}/*`; see
 * `TenancyServiceProvider::mapRoutes()`, which loads tenant routes after web.php.
 *
 * Branch identity (`id`) is immutable: it is the primary key, the URL segment, and
 * the target of every `tenant_id` foreign key. Retiring a branch means archiving
 * it via `is_active`, not deleting.
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

    public function create(): Response
    {
        return Inertia::render('settings/tenants/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('tenants', 'code'),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['id'] = $this->uniqueSlug($validated);
        $validated['is_active'] = $request->boolean('is_active', true);

        Tenant::create($validated);

        return redirect()
            ->route('tenants.show', ['tenant' => $validated['id']])
            ->with('success', 'Cabang '.$validated['name'].' berhasil dibuat.');
    }

    public function show(string $tenant): Response
    {
        return Inertia::render('settings/tenants/show', [
            'branch' => $this->findOrFail($tenant),
            'usage' => $this->usageCounts($tenant),
        ]);
    }

    public function edit(string $tenant): Response
    {
        return Inertia::render('settings/tenants/edit', [
            'branch' => $this->findOrFail($tenant),
        ]);
    }

    public function update(Request $request, string $tenant): RedirectResponse
    {
        $branch = $this->findOrFail($tenant);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('tenants', 'code')->ignore($branch->getKey()),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branch->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('tenants.show', ['tenant' => $branch->getKey()])
            ->with('success', 'Cabang '.$branch->name.' berhasil diperbarui.');
    }

    public function destroy(string $tenant): RedirectResponse
    {
        $branch = $this->findOrFail($tenant);
        $blocking = $this->blockingTables($tenant);

        if ($blocking !== []) {
            return redirect()
                ->route('tenants.show', ['tenant' => $branch->getKey()])
                ->withErrors(['tenant_id' => 'Cabang tidak dapat dihapus karena masih memiliki '.implode(', ', $blocking).'. Nonaktifkan cabang sebagai gantinya.']);
        }

        $branch->delete();

        return redirect()
            ->route('tenants.index')
            ->with('success', 'Cabang '.$branch->name.' berhasil dihapus.');
    }

    /**
     * Resolve a branch id to a model, or fail with a 404.
     */
    private function findOrFail(string $tenant): Tenant
    {
        return Tenant::query()->findOrFail($tenant);
    }

    /**
     * Derive a URL-safe slug from the code, else the name.
     *
     * The slug is the primary key, so a collision would silently fail the insert.
     * A numeric suffix keeps creation predictable instead of erroring.
     */
    private function uniqueSlug(array $validated): string
    {
        // `code` is nullable, so validation drops the key entirely when the field
        // is left blank — reading it unguarded raises "Undefined array key".
        $source = $validated['code'] ?? null;
        $base = Str::slug((string) ($source ?: $validated['name']));
        $base = $base !== '' ? $base : 'cabang';

        $slug = $base;
        $suffix = 2;

        while (Tenant::query()->whereKey($slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Row counts for the detail page.
     *
     * @return array<string, int>
     */
    private function usageCounts(string $tenant): array
    {
        $counts = [];

        foreach (self::TENANT_SCOPED as $model => $label) {
            $counts[$label] = $model::query()
                ->withoutTenancy()
                ->where('tenant_id', $tenant)
                ->count();
        }

        return $counts;
    }

    /**
     * Human labels of the tenant-scoped tables still holding data.
     *
     * @return array<int, string>
     */
    private function blockingTables(string $tenant): array
    {
        $blocking = [];

        foreach ($this->usageCounts($tenant) as $label => $count) {
            if ($count > 0) {
                $blocking[] = $label;
            }
        }

        return $blocking;
    }
}
