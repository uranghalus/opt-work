<?php

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkPlanning;
use App\Services\WorkPlanningService;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Carbon;

/**
 * FR-2.4: Gantt membaca `work_plannings` (punya durasi), bukan
 * `work_orders.scheduled_date`. Test ini menjaga kontrak data yang
 * dikonsumsi Syncfusion Gantt di sisi React.
 */
beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    initTenant('hq');
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/**
 * WO terjadwal lengkap dengan planning row di dalam minggu berjalan.
 *
 * `tgl_jadwal` sengaja dikunci ke hari ini supaya test tidak bergantung
 * pada batas minggu — kalau test butuh WO di luar minggu, ia mengubah
 * tanggalnya sendiri secara eksplisit.
 *
 * @return array{0: WorkOrder, 1: WorkPlanning, 2: User}
 */
function makeGanttWorkOrder(
    ?int $durationDays = 3,
    string $deadlineState = 'on_track',
    string $departmentName = 'MEP',
    ?Department $department = null,
): array {
    $tenant = Tenant::where('code', 'hq')->first();

    $hod = createUser(['tenant_id' => $tenant->id]);
    $hod->givePermissionTo('work-order.read');

    $department ??= Department::create([
        'kode_department' => 'DEP-GT-'.fake()->unique()->lexify('???'),
        'nama_department' => $departmentName,
        'hod_user_id' => $hod->getKey(),
    ]);

    $workOrder = WorkOrder::create([
        'nomor_wo' => 'WO-GT-'.fake()->unique()->numerify('####'),
        'requester_user_id' => createUser(['tenant_id' => $tenant->id])->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Gantt Target WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::Scheduled,
        'hod_action' => 'schedule',
        'scheduled_date' => Carbon::today(),
        'deadline_date' => match ($deadlineState) {
            'overdue' => Carbon::today()->subDays(2),
            'due_soon' => Carbon::today()->addDay(),
            'escalated' => Carbon::today()->subDays(5),
            default => Carbon::today()->addDays(10),
        },
        'is_escalated' => $deadlineState === 'escalated',
        'extend_count' => 0,
    ]);

    $planning = app(WorkPlanningService::class)->createForWorkOrder($workOrder);

    if ($durationDays !== null) {
        $planning->update(['lama_pekerjaan_hari' => $durationDays]);
        $planning->refresh();
    }

    return [$workOrder, $planning, $hod];
}

it('renders the gantt page with tasks grouped by department', function () {
    [$firstWo, $firstPlanning, $hod] = makeGanttWorkOrder(durationDays: 3);

    // WO kedua di departemen yang sama: satu baris induk, dua baris anak.
    $department = $firstWo->targetDepartment;
    [$secondWo, $secondPlanning] = makeGanttWorkOrder(
        durationDays: 1,
        department: $department,
    );

    $this->actingAs($hod)->get(route('work-orders.gantt', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-orders/gantt')
            ->where('totalScheduled', 2)
            ->where('tasks', fn ($tasks) => $tasks->count() === 3
                && $tasks->filter(fn ($t) => ! isset($t['ParentID']))->count() === 1
                && $tasks->filter(fn ($t) => isset($t['ParentID']))->count() === 2
            )
            // Baris induk: baris pertama, bernama departemen, ID berawalan dept-.
            ->where('tasks.0.TaskName', fn ($name) => str_contains($name, $department->nama_department))
            ->where('tasks.0.TaskID', fn ($id) => is_string($id) && str_starts_with($id, 'dept-'))
            ->where('tasks.1.NomorWo', $firstWo->nomor_wo)
            ->where('tasks.2.NomorWo', $secondWo->nomor_wo)
        );
});

it('maps work planning duration onto the gantt task fields', function () {
    [$workOrder, $planning, $hod] = makeGanttWorkOrder(durationDays: 4);

    $this->actingAs($hod)->get(route('work-orders.gantt', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tasks.1.StartDate', $planning->tgl_jadwal->toDateString())
            ->where('tasks.1.Duration', 4)
            ->where('tasks.1.EndDate', $planning->tgl_jadwal->copy()->addDays(4)->toDateString())
            ->where('tasks.1.NomorWo', $workOrder->nomor_wo)
        );
});

it('substitutes one day for a planning with no duration so the bar is visible', function () {
    // lama_pekerjaan_hari null = HOD belum mengisi durasi.
    [$workOrder, $planning, $hod] = makeGanttWorkOrder(durationDays: null);

    $this->actingAs($hod)->get(route('work-orders.gantt', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tasks.1.Duration', 1)
            ->where('tasks.1.EndDate', $planning->tgl_jadwal->copy()->addDay()->toDateString())
        );
});

it('ships a deadline label alongside the colour so colour is never the only signal', function (string $expectedState, string $deadlineState) {
    [, , $hod] = makeGanttWorkOrder(deadlineState: $deadlineState);

    $this->actingAs($hod)->get(route('work-orders.gantt', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tasks.1.DeadlineState', $expectedState)
            ->where('tasks.1.DeadlineLabel', fn ($label) => is_string($label) && $label !== '')
        );
})->with([
    ['on_track', 'on_track'],
    ['due_soon', 'due_soon'],
    ['overdue', 'overdue'],
    ['escalated', 'escalated'],
]);

it('only returns work orders the acting user may see', function () {
    makeGanttWorkOrder();

    $outsider = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $outsider->givePermissionTo('work-order.read');

    $this->actingAs($outsider)->get(route('work-orders.gantt', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totalScheduled', 0)
            ->where('tasks', [])
        );
});

it('excludes work orders whose planning falls outside the requested week', function () {
    [, $planning, $hod] = makeGanttWorkOrder();
    $planning->update(['tgl_jadwal' => Carbon::today()->addMonth()]);

    $this->actingAs($hod)->get(route('work-orders.gantt', ['tenant' => 'hq']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('totalScheduled', 0));
});

it('forbids users without the read permission', function () {
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    $this->actingAs($user)->get(route('work-orders.gantt', ['tenant' => 'hq']))
        ->assertForbidden();
});
