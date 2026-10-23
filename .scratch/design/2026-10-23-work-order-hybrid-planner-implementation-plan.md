# Work Order Hybrid Planner Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn OptiWorks' Work Order timeline into a role-aware Hybrid Planner with a week calendar, custom department/employee workload view, approval-safe schedule proposals, and a normalized `work_plannings` payload.

**Architecture:** Backend normalizes existing `work_orders` + `work_plannings` into one planner payload without widening Work Order visibility. The Inertia page renders an OptiWorks-shelled `react-big-schedule` calendar for date/time planning and a custom React workload view for grouped employee allocation. All date moves remain Extend Work Schedule approvals.

**Tech Stack:** Laravel 13, Pest, PHPStan, Inertia 3, React 19, TypeScript, Tailwind/shadcn tokens, `react-big-schedule@9.0.3`.

**Spec:** `.scratch/design/work-order-hybrid-planner-design.md`

## Global Constraints

- Do not use `git commit -a`, `git add .`, `git checkout --`, `git reset`, or force-push. This branch already contains unrelated pre-existing changes; add only the exact paths listed in each task.
- Do not change `WorkOrder::scopeVisibleFor()` behavior or add a DGM/GM planner-wide visibility exception.
- Do not directly mutate `work_orders.scheduled_date` or `work_plannings.tgl_jadwal` from planner drag/resize.
- Do not invent times when `jam_mulai` or `jam_selesai` is null.
- Keep route `/{tenant}/work-orders/timeline` and deep-linkable `?week=YYYY-MM-DD`.
- All rendered times are fixed WITA wall-clock, never browser-local.
- All user-facing copy is Bahasa Indonesia; no status/category/deadline signal may be color-only.
- `react-big-schedule` is approved at exact version `9.0.3`.
- Existing package manager evidence is npm: `package-lock.json` is tracked and `pnpm-lock.yaml` is untracked. Do not switch package managers.
- No JS unit/E2E framework is introduced in this plan. Frontend verification is `npm run types:check`, `npm run build`, and manual browser QA.
- After modifying PHP files, run Pint on only the task's exact PHP paths if `--dirty` would include unrelated branch changes.

## Review Focus

- [ ] **One-sided time:** A `WorkPlanning` with only `jam_mulai` or only `jam_selesai` must remain `all_day` and show the known boundary as text. Pinned by Task 1 test `it('renders one-sided planning time as all-day with a text label')`.
- [ ] **Multi-day overlap:** A WO that starts before the selected Monday but overlaps the week must appear. Pinned by Task 2 test `it('includes multi-day planning that starts before the selected week')`.
- [ ] **Browser timezone:** WITA times must not shift for QA/users outside WITA. Pinned by Task 4 adapter types and manual WITA-vs-browser QA in Task 8.
- [ ] **Pending proposal:** A pending extension must render as a separate labeled proposal while the current date remains active. Pinned by Task 2 test `it('separates pending schedule proposals from the current schedule')`.
- [ ] **Role defaults and filters:** Team Leader, Viewer, Admin Tenant, GM/DGM, and Guest must not silently land on an empty workload view or misleading summary. Pinned by Task 2 tests `it('uses workload only for headed or deputised departments')` and `it('applies whitelisted filters across planner events, days, and summary')`.

---

### Task 1: Create planner normalization service

**Files:**
- Create: `app/Services/WorkOrderPlannerService.php`
- Test: `tests/Feature/WorkOrderPlannerServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\WorkOrder`, `App\Models\WorkPlanning`, `App\Models\User`
- Produces:
  - `normalize(WorkOrder $workOrder): array`
  - `filterForWeek(array $events, CarbonInterface $weekStart, CarbonInterface $weekEnd): array`
  - `applyFilters(array $events, array $filters): array`
  - `summarize(array $events): array`
  - `employeeRows(array $events): array`
  - `capabilitiesFor(User $user): array`

The normalized event array must use exactly these keys: `id`, `nomor_wo`, `title`, `status`, `category`, `scheduled_date`, `planning`, `assignees`, `requester_name`, `department_name`, `deadline_date`, `deadline_state`, `deadline_label`, `planner_start`, `planner_end`, `schedule_kind`, `is_multi_day`, `is_unassigned`, `has_pending_extension`.

Planning keys must be exactly: `id`, `tgl_jadwal`, `jam_mulai`, `jam_selesai`, `lama_pekerjaan_hari`, `jenis_pekerjaan`, `status_jadwal`, `proposed_tgl_jadwal`, `pending`, `extend_reason`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/WorkOrderPlannerServiceTest.php` with these Pest tests:

```php
it('renders a legacy work order without planning as date-only', function () {
    $workOrder = WorkOrder::factory()->create([
        'scheduled_date' => Carbon::today()->startOfWeek(Carbon::MONDAY),
    ]);

    $event = app(WorkOrderPlannerService::class)->normalize($workOrder);

    expect($event['planning'])->toBeNull()
        ->and($event['planner_start'])->toBe($workOrder->scheduled_date->toDateString())
        ->and($event['planner_end'])->toBe($workOrder->scheduled_date->toDateString())
        ->and($event['schedule_kind'])->toBe('all_day')
        ->and($event['is_multi_day'])->toBeFalse();
});

it('renders two-sided planning time as a timed event', function () {
    $workOrder = WorkOrder::factory()->create([
        'scheduled_date' => Carbon::today()->startOfWeek(Carbon::MONDAY),
    ]);
    app(WorkPlanningService::class)->createForWorkOrder($workOrder)->update([
        'jam_mulai' => '08:00',
        'jam_selesai' => '12:00',
    ]);

    $event = app(WorkOrderPlannerService::class)->normalize($workOrder);

    expect($event['schedule_kind'])->toBe('timed')
        ->and($event['planning']['jam_mulai'])->toBe('08:00')
        ->and($event['planning']['jam_selesai'])->toBe('12:00')
        ->and($event['is_multi_day'])->toBeFalse();
});

it('renders one-sided planning time as all-day with a text label', function () {
    $workOrder = WorkOrder::factory()->create([
        'scheduled_date' => Carbon::today()->startOfWeek(Carbon::MONDAY),
    ]);
    app(WorkPlanningService::class)->createForWorkOrder($workOrder)->update([
        'jam_mulai' => '08:00',
    ]);

    $event = app(WorkOrderPlannerService::class)->normalize($workOrder);

    expect($event['schedule_kind'])->toBe('all_day')
        ->and($event['planning']['jam_selesai'])->toBeNull()
        ->and($event['planning']['jam_mulai'])->toBe('08:00')
        ->and($event['time_label'] ?? null)->toBe('Mulai 08:00');
});

it('computes an inclusive multi-day range', function () {
    $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
    $workOrder = WorkOrder::factory()->create([
        'scheduled_date' => $weekStart,
    ]);
    app(WorkPlanningService::class)->createForWorkOrder($workOrder)->update([
        'lama_pekerjaan_hari' => 3,
    ]);

    $event = app(WorkOrderPlannerService::class)->normalize($workOrder);

    expect($event['planner_start'])->toBe($weekStart->toDateString())
        ->and($event['planner_end'])->toBe($weekStart->copy()->addDays(2)->toDateString())
        ->and($event['is_multi_day'])->toBeTrue();
});

it('keeps a pending proposal separate from the active schedule', function () {
    $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
    $workOrder = WorkOrder::factory()->create([
        'scheduled_date' => $weekStart,
        'status' => WorkOrderStatus::Scheduled,
    ]);
    $planning = app(WorkPlanningService::class)->createForWorkOrder($workOrder);
    $planning->update([
        'status_jadwal' => WorkPlanning::STATUS_PENDING,
        'proposed_tgl_jadwal' => $weekStart->copy()->addDays(4),
    ]);

    $event = app(WorkOrderPlannerService::class)->normalize($workOrder);

    expect($event['has_pending_extension'])->toBeTrue()
        ->and($event['planner_start'])->toBe($weekStart->toDateString())
        ->and($event['planning']['proposed_tgl_jadwal'])->toBe($weekStart->copy()->addDays(4)->toDateString());
});

it('includes a multi-day event that starts before the week', function () {
    $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
    $workOrder = WorkOrder::factory()->create([
        'scheduled_date' => $weekStart->copy()->subDays(2),
    ]);
    app(WorkPlanningService::class)->createForWorkOrder($workOrder)->update([
        'lama_pekerjaan_hari' => 4,
    ]);

    $event = app(WorkOrderPlannerService::class)->normalize($workOrder);
    $filtered = app(WorkOrderPlannerService::class)->filterForWeek(
        [$event],
        $weekStart,
        $weekStart->copy()->endOfWeek(Carbon::SUNDAY),
    );

    expect($filtered)->toHaveCount(1);
});
```

Also add at least one test that `summarize()` reports `workOrderCount`, `assignmentCount`, `pendingScheduleRequests`, `overlapCount`, and `unassignedCount` with exact counts for a fixture containing one pending WO, one assigned WO, and one unassigned WO.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/WorkOrderPlannerServiceTest.php`
Expected: FAIL because `App\Services\WorkOrderPlannerService` does not exist.

- [ ] **Step 3: Implement `WorkOrderPlannerService`**

Create `app/Services/WorkOrderPlannerService.php` using constructor property promotion where needed. Implement:

- Legacy normalization when `planning === null`.
- Inclusive duration: `planner_end = planning->tgl_jadwal + max(1, lama_pekerjaan_hari) - 1 day`.
- `schedule_kind = 'timed'` only when both `jam_mulai` and `jam_selesai` are present.
- `time_label`: `Mulai HH:mm` when only start exists, `Sampai HH:mm` when only end exists, `Jam mulai–jam selesai` when both exist, and no label when neither exists.
- `filterForWeek()`: include events where `planner_start <= weekEnd && planner_end >= weekStart`.
- `applyFilters()`: support only the whitelisted keys `department`, `status`, `category`, `employee`, `schedule_state`.
- `employeeRows()`: employees grouped under departments; `department` header counts unique WOs, employee rows count assignments; add an explicit `Belum ditugaskan` row for unassigned scheduled WOs.
- `capabilitiesFor()`: return `defaultView`, `canViewWorkload`, `canRequestScheduleExtension`, and `canUpdatePlanningDetails` based on existing Gates and departments headed/deputised by the user.

Use the same `Asia/Makassar` app timezone and never construct browser-dependent timestamps.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/WorkOrderPlannerServiceTest.php`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --format agent app/Services/WorkOrderPlannerService.php tests/Feature/WorkOrderPlannerServiceTest.php
git add app/Services/WorkOrderPlannerService.php tests/Feature/WorkOrderPlannerServiceTest.php
git commit -m "feat: normalize planner work order scheduling data"
```

Expected: only these two paths are staged.

---

### Task 2: Wire planner payload, filters, role defaults, and Indonesian deadline labels

**Files:**
- Modify: `app/Http/Controllers/WorkOrderController.php`
- Test: `tests/Feature/WorkOrderTimelinePlannerTest.php`

**Interfaces:**
- Consumes: `WorkOrderPlannerService` from Task 1
- Produces: Inertia prop `planner` with `defaultView`, `canViewWorkload`, `canRequestScheduleExtension`, `canUpdatePlanningDetails`, `summary`, `employees`, and `events`
- Preserves existing `weekStart`, `weekEnd`, `weekLabel`, `today`, `isCurrentWeek`, `prevWeek`, `nextWeek`, `totalScheduled`, and `days` props for compatibility

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/WorkOrderTimelinePlannerTest.php` with these Pest tests:

```php
it('sends normalized planning data for scheduled work orders')
it('includes multi-day planning that starts before the selected week')
it('separates pending schedule proposals from the current schedule')
it('applies whitelisted filters across planner events, days, and summary')
it('uses workload only for headed or deputised departments')
```

Assertions must include:

- `planner.events.0.planning.jam_mulai`, `planner.events.0.planner_end`, and `planner.events.0.schedule_kind`.
- Pending payload keeps `planner_start` at the active date and exposes `planning.proposed_tgl_jadwal`.
- `?status=closed&category=normal&schedule_state=pending` returns only matching planner events and updates `planner.summary`.
- A user who does not head/deputise a department receives `planner.defaultView === 'calendar'` and `canViewWorkload === false`.
- A user with `Department.hod_user_id` set receives `planner.defaultView === 'workload'` and `canViewWorkload === true`.

Add a test that `On track` is replaced by `Sesuai jadwal` in the existing `deadlineState()` result while old `days.0.workOrders.0.deadline_label` still exists.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/WorkOrderTimelinePlannerTest.php`
Expected: FAIL because `planner` prop is absent and `On track` is still returned.

- [ ] **Step 3: Implement controller wiring**

Modify `WorkOrderController`:

- Add `WorkOrderPlannerService` to the existing constructor.
- Keep `timeline()` route name and `week` resolution behavior.
- Load `requester`, `targetDepartment`, and `planning` on the visible WorkOrder query.
- Validate filter query params using existing enum/case values and `Rule::in(['all', 'pending', 'normal'])` for `schedule_state`.
- Delegate event normalization, week filtering, filters, employee rows, summary, and capabilities to `WorkOrderPlannerService`.
- Build `days` from normalized planner events while preserving the current `TimelineDay` shape so the current page does not break before frontend replacement.
- Replace the two `On track` strings in `deadlineState()` with `Sesuai jadwal`.
- Do not alter `scopeVisibleFor()` behavior.

- [ ] **Step 4: Run narrower backend tests**

Run: `php artisan test --compact tests/Feature/WorkOrderTimelinePlannerTest.php tests/Feature/WorkOrderPlannerServiceTest.php tests/Feature/WorkOrderVisibilityTest.php tests/Feature/WorkPlanningTest.php`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --format agent app/Http/Controllers/WorkOrderController.php tests/Feature/WorkOrderTimelinePlannerTest.php
git add app/Http/Controllers/WorkOrderController.php tests/Feature/WorkOrderTimelinePlannerTest.php
git commit -m "feat: add work order planner payload and filters"
```

Expected: only controller and new test are staged.

---

### Task 3: Create shared planner types and OptiWorks event anatomy

**Files:**
- Create: `resources/js/types/work-order-planner.ts`
- Create: `resources/js/components/work-order/planner/planner-event.tsx`

**Interfaces:**
- Consumes: `WoStatus` from `@/components/status-badge`, `WoCategory` from `@/components/category-badge`, `DeadlineState` from `@/components/deadline-rail`
- Produces:
  - `PlannerWorkOrder` type
  - `PlannerPlanning` type
  - `PlannerEmployeeRow` type
  - `PlannerSummary` type
  - `PlannerEvent` component props
  - `plannerTimeLabel(event: PlannerWorkOrder): string`

The TS types must match the PHP payload keys from Tasks 1 and 2 exactly.

- [ ] **Step 1: Define the payload types**

Create `resources/js/types/work-order-planner.ts` with the backend payload keys and TypeScript unions for `schedule_kind`, `schedule_state`, and `defaultView`.

- [ ] **Step 2: Create the shared event component**

Create `planner-event.tsx`:

- Render WO number in mono.
- Render title with line clamping.
- Render `StatusBadge` compact and `CategoryBadge`.
- Render deadline rail plus `deadline_label`.
- Render `plannerTimeLabel(event)` so one-sided times show `Mulai 08:00` or `Sampai 16:00` and never fake a missing boundary.
- Render pending-extension and overlap indicators as text/icon/shape signals, not color-only.
- Use existing `bg-card`, `border-border`, focus-ring, and motion-reduce tokens.

- [ ] **Step 3: Verify types**

Run: `npm run types:check`
Expected: PASS with no new TypeScript errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/types/work-order-planner.ts resources/js/components/work-order/planner/planner-event.tsx
git commit -m "feat: add shared work order planner event anatomy"
```

---

### Task 4: Add approved scheduler dependency and calendar adapter

**Files:**
- Modify: `package.json`
- Modify: `package-lock.json`
- Create: `resources/js/components/work-order/planner/planner-adapter.ts`
- Create: `resources/js/components/work-order/planner/planner-calendar.tsx`
- Modify: `resources/js/pages/work-orders/timeline.tsx`

**Interfaces:**
- Consumes: `PlannerWorkOrder` from Task 3
- Produces:
  - `PlannerSchedulerEvent` type with `id`, `resourceId`, `title`, `start`, `end`, and `plannerEvent`
  - `toPlannerSchedulerEvents(events: PlannerWorkOrder[]): PlannerSchedulerEvent[]`
  - `fromWitaDate(date: string, time: string): string`
  - `PlannerCalendar` component props including `events`, `weekStart`, `today`, empty state/loading state callbacks, and drag proposal callback
- Library: `react-big-schedule` is imported only inside planner components; no global third-party CSS is applied outside the planner wrapper.

- [ ] **Step 1: Add dependency**

Run: `npm install react-big-schedule@9.0.3`
Expected: `package.json` and tracked `package-lock.json` update; no package-manager switching.

- [ ] **Step 2: Write the calendar adapter**

Create `planner-adapter.ts`:

- Convert timed WOs to `YYYY-MM-DD HH:mm:ss` WITA strings using `fromWitaDate()`.
- Convert all-day WOs to `YYYY-MM-DD` strings.
- Compute inclusive `end` for multi-day events.
- Attach the original `PlannerWorkOrder` to `plannerEvent` so the custom renderer never needs to re-derive OptiWorks state.
- Do not use naive `new Date()` conversion; fixed WITA wall-clock must be preserved.

- [ ] **Step 3: Create the OptiWorks calendar shell**

Create `planner-calendar.tsx`:

- Configure a seven-day Monday-first view.
- Use `eventItemTemplateResolver` so every event renders `PlannerEvent`.
- Pass drag/resize callbacks to the parent as intents; do not mutate local event state with a new schedule.
- Provide accessible loading and empty states.
- Keep library styles scoped and token-mapped for light/dark themes.

- [ ] **Step 4: Integrate the calendar into the existing page**

Modify `resources/js/pages/work-orders/timeline.tsx` to render `PlannerCalendar` from `planner.events` while still consuming `planner.defaultView` as `calendar` until the workload view lands.

- [ ] **Step 5: Verify frontend**

Run: `npm run types:check`
Expected: PASS

Run: `npm run build`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add package.json package-lock.json resources/js/components/work-order/planner/planner-adapter.ts resources/js/components/work-order/planner/planner-calendar.tsx resources/js/pages/work-orders/timeline.tsx
git commit -m "feat: add scheduler-backed work order calendar"
```

---

### Task 5: Add planner toolbar, filters, week navigation, and server-driven empty states

**Files:**
- Create: `resources/js/components/work-order/planner/planner-toolbar.tsx`
- Modify: `resources/js/pages/work-orders/timeline.tsx`

**Interfaces:**
- Consumes: planner page props from Task 2
- Produces:
  - `PlannerFilters` type
  - `PlannerToolbar` props: `weekStart`, `weekLabel`, `isCurrentWeek`, `defaultView`, `filters`, `summary`, `onWeekChange`, `onFiltersChange`, and `onViewChange`

- [ ] **Step 1: Create the toolbar component**

Create `planner-toolbar.tsx` with:

- Previous/next/today buttons.
- Date input that updates `?week=YYYY-MM-DD`.
- View switch `Kalender · Beban Kerja`.
- Filters for department, status, category, assigned person, and schedule state.
- Compact summary strip using existing text/icon patterns.
- Accessible names and Inertia `router` navigation with visible loading state.

- [ ] **Step 2: Integrate toolbar and deep-linked filters**

Modify the timeline page so:

- Week and filter changes call `router.get(timeline({ tenant }), query)`.
- The query includes only the whitelisted filter keys accepted by Task 2.
- Empty states distinguish "no WO matches filters" from "no visible WO this week" and "no visible WO in this branch/department scope".
- `On track` is not rendered because the server now sends `Sesuai jadwal`.

- [ ] **Step 3: Verify frontend**

Run: `npm run types:check`
Expected: PASS

Run: `npm run build`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/work-order/planner/planner-toolbar.tsx resources/js/pages/work-orders/timeline.tsx
git commit -m "feat: add work order planner navigation and filters"
```

---

### Task 6: Add custom workload view

**Files:**
- Create: `resources/js/components/work-order/planner/workload-view.tsx`
- Modify: `resources/js/pages/work-orders/timeline.tsx`

**Interfaces:**
- Consumes: `planner.employees`, `planner.events`, `planner.summary`, and `onSelectEvent`
- Produces:
  - `WorkloadView` component props: `departments`, `employees`, `events`, `summary`, and `onSelectEvent`
- Renders from existing `planner.employees` payload types; it does not query Laravel directly.

- [ ] **Step 1: Create workload grouping**

Create `workload-view.tsx`:

- Group employee rows under department headers.
- Render one WO bar under each assigned employee row.
- Render unassigned WOs under the target department in an explicit `Belum ditugaskan` section.
- Use department unique WOs and employee assignment counts exactly as `WorkOrderPlannerService::employeeRows()` provides.
- Render pending extension bars separately from active schedule bars.
- Render overlap state as `Bentrok` for true timed interval overlap and `Potensi bentrok` when at least one WO is date-only/unspecific.
- Do not allow drag or direct schedule mutation in this view.

- [ ] **Step 2: Integrate view switching**

Modify timeline page so `planner.defaultView` controls the initial view and toolbar view changes switch between `PlannerCalendar` and `WorkloadView`.

- [ ] **Step 3: Verify frontend**

Run: `npm run types:check`
Expected: PASS

Run: `npm run build`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/work-order/planner/workload-view.tsx resources/js/pages/work-orders/timeline.tsx
git commit -m "feat: add department and employee workload planner"
```

---

### Task 7: Add event drawer and approval-safe schedule dialogs

**Files:**
- Create: `resources/js/components/work-order/planner/planner-event-drawer.tsx`
- Create: `resources/js/components/work-order/planner/schedule-request-dialog.tsx`
- Modify: `resources/js/components/work-order/planner/planner-calendar.tsx`
- Modify: `resources/js/pages/work-orders/timeline.tsx`

**Interfaces:**
- Consumes: `useForm` from `@inertiajs/react`, existing `Dialog`, `Textarea`, `Button`, and Wayfinder routes under `@/routes/work-planning`
- Produces:
  - `PlannerEventDrawer` props: `event`, `open`, `onOpenChange`, `tenant`, `canRequestScheduleExtension`, `canUpdatePlanningDetails`
  - `ScheduleRequestDialog` props: `open`, `onOpenChange`, `mode`, `event`, `proposedDate`, and `tenant`
- Existing endpoints must be reused:
  - `POST work-planning.extend`
  - `PATCH work-planning.update`

- [ ] **Step 1: Create the event drawer**

Create `planner-event-drawer.tsx`:

- Show WO number, title, status/category, department, assignees, deadline, planning details, budget, notes, and pending extension fields.
- Provide an action to open the existing WO detail.
- Provide `Ajukan Extend Jadwal` only when the event is planned, not pending, and the active user may request it per server payload.
- Do not render a direct editable `tgl_jadwal` field.

- [ ] **Step 2: Create approval-safe dialogs**

Create `schedule-request-dialog.tsx`:

- Date-move drag opens an extend request dialog.
- Time/duration resize opens the existing planning-details dialog.
- The extend dialog shows the current date, proposed date, and a required reason.
- The extend dialog states that existing times and duration remain unchanged.
- The detail dialog can update only allowed planning details, never `tgl_jadwal`.
- Guard browsers without JS permission state by relying on server response errors.

- [ ] **Step 3: Wire calendar drag callbacks to dialogs**

Modify `planner-calendar.tsx` and timeline page so:

- Event click opens the drawer.
- Drag/resize callbacks pass a normalized intent to the parent.
- Parent opens the appropriate dialog only; no planner event writes directly to WorkOrder/WorkPlanning.
- Pending events never open an extend dialog for a second request.

- [ ] **Step 4: Verify frontend**

Run: `npm run types:check`
Expected: PASS

Run: `npm run build`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/work-order/planner/planner-event-drawer.tsx resources/js/components/work-order/planner/schedule-request-dialog.tsx resources/js/components/work-order/planner/planner-calendar.tsx resources/js/pages/work-orders/timeline.tsx
git commit -m "feat: add approval-safe planner schedule actions"
```

---

### Task 8: Responsive agenda pass, accessibility/theme cleanup, and final QA

**Files:**
- Create: `resources/js/components/work-order/planner/planner-agenda.tsx`
- Modify: `resources/js/pages/work-orders/timeline.tsx`
- Modify: `resources/js/components/work-order/planner/planner-calendar.tsx`
- Modify: `resources/js/components/work-order/planner/workload-view.tsx`
- Create: `.scratch/design/work-order-hybrid-planner-qa.md`

**Interfaces:**
- Consumes: all planner components and payload types from Tasks 3–7
- Produces:
  - `PlannerAgenda` component props: `events`, `selectedDate`, and `onSelectEvent`
  - Manual QA report at `.scratch/design/work-order-hybrid-planner-qa.md`

- [ ] **Step 1: Add mobile agenda surface**

Create `planner-agenda.tsx`:

- Render a day-selected list/agenda for small viewports.
- Show WO number, title, time/date-only label, status/category, deadline, and pending state.
- Provide 44px minimum tap targets.
- Do not render seven long empty calendar sections.

- [ ] **Step 2: Fix contrast, copy, focus, and motion cleanup**

Modify planner components to:

- Replace weak `ink-subtle` on `surface-sunken` empty-state text with readable ink tokens.
- Preserve text+icon+shape signals for status/category/deadline/pending/overlap.
- Ensure every interactive event is keyboard-operable and opens the drawer.
- Keep light/dark themes token-authored.
- Respect `prefers-reduced-motion`.
- Retain deep-linkable week navigation.

- [ ] **Step 3: Run all affected automated checks**

Run: `php artisan test --compact tests/Feature/WorkOrderPlannerServiceTest.php tests/Feature/WorkOrderTimelinePlannerTest.php tests/Feature/WorkOrderVisibilityTest.php tests/Feature/WorkPlanningTest.php`
Expected: PASS

Run: `vendor/bin/pint --format agent app/Services/WorkOrderPlannerService.php app/Http/Controllers/WorkOrderController.php tests/Feature/WorkOrderPlannerServiceTest.php tests/Feature/WorkOrderTimelinePlannerTest.php`
Expected: PASS

Run: `npm run types:check`
Expected: PASS

Run: `npm run build`
Expected: PASS

- [ ] **Step 4: Perform and record manual browser QA**

Record results in `.scratch/design/work-order-hybrid-planner-qa.md`:

- Desktop light and dark: calendar fits the content area, all-day lane, timed events, multi-day bars, and workload grouping are visible.
- Tablet: week/day behavior does not force horizontal overflow.
- Mobile: agenda/day list is readable and empty states are not repeated seven times.
- Keyboard: toolbar, filters, events, drawer, and dialogs are usable without drag.
- WITA check: browser timezone is changed from WITA and WITA timed events do not shift.
- Drag safety: date drag opens an extend request and does not change `tgl_jadwal` before approval.
- Dark/light focus rings, status labels, pending extension shape, overlap shape, and empty states remain legible.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/work-order/planner/planner-agenda.tsx resources/js/pages/work-orders/timeline.tsx resources/js/components/work-order/planner/planner-calendar.tsx resources/js/components/work-order/planner/workload-view.tsx .scratch/design/work-order-hybrid-planner-qa.md
git commit -m "feat: polish responsive work order planner experience"
```

Expected: only the planner and QA files are staged.

---

## Plan Self-Review Notes

- All backend normalization and visibility constraints from the approved spec map to Tasks 1–2.
- All schedule-shape cases map to Task 1 tests.
- Approval-safe drag/resize maps to Task 7 and Task 8 manual QA.
- Workload grouping and warning semantics map to Task 1 `employeeRows()` and Task 6 rendering.
- Dependency approval is used only in Task 4 at exact version `9.0.3`.
- No task adds a new frontend test framework or backend schema migration, matching the approved design non-goals.
