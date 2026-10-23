<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;

/**
 * Page-level behaviour of the Departments master data page.
 *
 * The visual fixes (mobile card list, focus ring, heading band on the detail
 * page) are verified by headless browser capture, not by PHP — this file
 * covers what the server owns: the sync timestamp, pagination reachability and
 * the payload both pages depend on.
 */
beforeEach(function () {
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

function makeDepartmentRow(string $kode, string $nama): Department
{
    $department = Department::withoutGlobalScopes()->make([
        'kode_department' => $kode,
        'nama_department' => $nama,
    ]);
    $department->tenant_id = Tenant::where('code', 'hq')->first()->id;
    $department->save();

    return $department;
}

it('formats the last sync timestamp on the server in Indonesian', function () {
    initTenant('hq');
    $tenant = Tenant::where('code', 'hq')->first();
    $tenant->update(['last_department_synced_at' => '2026-10-22 09:05:00']);

    $props = $this->actingAs(makeSuperAdmin(createUser()))
        ->get('/hq/departments')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['last_synced_label'])->toBe('22 Okt 2026, 09:05')
        ->and($props['last_synced_at'])->toStartWith('2026-10-22T09:05:00');
});

it('does not let the month abbreviation be read as a date format specifier', function () {
    // "Okt" contains O (UTC offset), k (hour) and t (days in month); passing it
    // through format() would render "22 +0000k31 2026".
    initTenant('hq');
    $tenant = Tenant::where('code', 'hq')->first();
    $tenant->update(['last_department_synced_at' => '2026-10-22 09:05:00']);

    $label = $this->actingAs(makeSuperAdmin(createUser()))
        ->get('/hq/departments')
        ->viewData('page')['props']['last_synced_label'];

    expect($label)->not->toContain('+0000')
        ->and($label)->not->toMatch('/\d{4}k\d{2}/')
        ->and($label)->toMatch('/^\d{1,2} [A-Z][a-z]{2} \d{4}, \d{2}:\d{2}$/');
});

it('reports no sync timestamp when the branch has never synced', function () {
    initTenant('hq');
    Tenant::where('code', 'hq')->first()->update(['last_department_synced_at' => null]);

    $props = $this->actingAs(makeSuperAdmin(createUser()))
        ->get('/hq/departments')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['last_synced_label'])->toBeNull()
        ->and($props['last_synced_at'])->toBeNull();
});

it('keeps every department reachable when the list is longer than one page', function () {
    initTenant('hq');

    for ($i = 1; $i <= 51; $i++) {
        makeDepartmentRow(sprintf('DEP-%03d', $i), 'Department '.$i);
    }

    $admin = makeSuperAdmin(createUser());

    $first = $this->actingAs($admin)
        ->get('/hq/departments')
        ->assertOk()
        ->viewData('page')['props']['departments'];

    expect($first['data'])->toHaveCount(50)
        ->and($first['total'])->toBe(51)
        ->and($first['last_page'])->toBe(2)
        ->and($first['next_page_url'])->not->toBeNull();

    $second = $this->actingAs($admin)
        ->get('/hq/departments?page=2')
        ->assertOk()
        ->viewData('page')['props']['departments'];

    expect($second['data'])->toHaveCount(1)
        ->and($second['data'][0]['kode_department'])->toBe('DEP-051')
        ->and($second['prev_page_url'])->not->toBeNull();
});

it('shows a branch with more than ten departments on one page', function () {
    initTenant('hq');

    for ($i = 1; $i <= 11; $i++) {
        makeDepartmentRow(sprintf('DEP-%03d', $i), 'Department '.$i);
    }

    $props = $this->actingAs(makeSuperAdmin(createUser()))
        ->get('/hq/departments')
        ->assertOk()
        ->viewData('page')['props']['departments'];

    expect($props['data'])->toHaveCount(11)
        ->and($props['last_page'])->toBe(1);
});

it('renders the department detail page with its relations', function () {
    initTenant('hq');
    $department = makeDepartmentRow('DEP-IT', 'Information Technology');

    $props = $this->actingAs(makeSuperAdmin(createUser()))
        ->get('/hq/departments/'.$department->getKey())
        ->assertOk()
        ->viewData('page')['props']['department'];

    expect($props['kode_department'])->toBe('DEP-IT')
        ->and($props['hod_display'])->toBeNull()
        ->and($props['divisions'])->toBe([]);
});

it('reports the registry measurements the index band renders', function () {
    initTenant('hq');

    $alpha = makeDepartmentRow('DEP-A', 'Alpha');
    $beta = makeDepartmentRow('DEP-B', 'Beta');
    makeDepartmentRow('DEP-C', 'Gamma');

    $beta->update(['hod_user_id' => makeHod(createUser())->id]);

    Employee::factory()->count(3)->create(['department_id' => $alpha->id]);
    Employee::factory()->create(['department_id' => $beta->id]);

    $stats = $this->actingAs(makeSuperAdmin(createUser()))
        ->get('/hq/departments')
        ->assertOk()
        ->viewData('page')['props']['stats'];

    expect($stats)->toMatchArray([
        'departments' => 3,
        'employees' => 4,
        'without_hod' => 2,
        'max_employees' => 3,
    ]);
});

it('includes the division names the registry column renders', function () {
    initTenant('hq');
    $department = makeDepartmentRow('DEP-OPS', 'Operations');

    foreach (['Facility', 'Logistics'] as $index => $name) {
        Division::create([
            'kode_division' => 'DEP-OPS-D'.($index + 1),
            'nama_division' => $name,
            'department_id' => $department->id,
            'tenant_id' => Tenant::where('code', 'hq')->first()->id,
            'is_active' => true,
        ]);
    }

    $row = $this->actingAs(makeSuperAdmin(createUser()))
        ->get('/hq/departments')
        ->assertOk()
        ->viewData('page')['props']['departments']['data'][0];

    expect(array_column($row['divisions'], 'nama_division'))
        ->toBe(['Facility', 'Logistics'])
        ->and($row['divisions_count'])->toBe(2);
});

it('loads the positions and division labels the detail page lists', function () {
    initTenant('hq');
    $department = makeDepartmentRow('DEP-IT', 'Information Technology');

    $division = Division::create([
        'kode_division' => 'DMB-PLT',
        'nama_division' => 'Platform',
        'department_id' => $department->id,
        'tenant_id' => Tenant::where('code', 'hq')->first()->id,
        'is_active' => true,
    ]);

    Position::create([
        'nama_position' => 'Engineer',
        'department_id' => $department->id,
        'division_id' => $division->id,
    ]);

    $props = $this->actingAs(makeSuperAdmin(createUser()))
        ->get('/hq/departments/'.$department->getKey())
        ->assertOk()
        ->viewData('page')['props']['department'];

    expect($props['positions'])->toHaveCount(1)
        ->and($props['positions'][0]['nama_position'])->toBe('Engineer')
        ->and($props['positions'][0]['division']['nama_division'])->toBe('Platform');
});

it('renders the division detail page', function () {
    initTenant('hq');

    $division = Division::create([
        'kode_division' => 'DMB-ACC',
        'nama_division' => 'Accounting',
        'tenant_id' => Tenant::where('code', 'hq')->first()->id,
        'is_active' => true,
    ]);

    $this->actingAs(makeSuperAdmin(createUser()))
        ->get('/hq/divisions/'.$division->getKey())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('divisions/show')
            ->where('division.nama_division', 'Accounting')
        );
});
