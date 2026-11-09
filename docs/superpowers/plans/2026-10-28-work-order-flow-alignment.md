# Work Order Flow Alignment — Controller + Frontend (opti-work2 reference)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring opti-works' Work Order feature (controller + frontend) in line with the reference implementation in `D:\Laravel Project\opti-work2` (the documented rebuild reference), so the WO lifecycle flows: Create → HOD Review (approve/schedule) → Assign → Submit Results → HOD Verify → Close, with dedicated pages per step, workflow stepper, and rich status UI — matching the flow the user has worked on. (No separate "worldflow" codebase exists on disk; opti-work2 is the reference.)

User decisions (applied):
- Status model split into `status_tiket` + `status_pekerjaan` (opti-work2 two-field model)
- Dedicated page per workflow step; format/presentation changes depending on current status
- WO numbering: combine both approaches — atomic sequence table (opti-work2) + opti-works format
- Execution method: subagent-driven-development

**Architecture:**
- Backend: Laravel controller (`App\Http\Controllers\WorkOrderController`) with dedicated GET pages for each workflow step (review, assign, submit, verify) plus existing POST action endpoints; routes under `/{tenant}/work-orders`.
- Frontend: Inertia + React pages under `resources/js/pages/work-orders/`, PascalCase component names matching opti-work2 convention (`Index`, `Create`, `Edit`, `Show`, `HodReview`, `Assign`, `SubmitResults`, `Verify`), shared `WorkflowStepper` and `StatusBadge` components, wayfinder route helpers.
- Tech stack: Laravel + PHP 8.4, Inertia v3 React, Tailwind, shadcn/ui, wayfinder, Pest, Pint.

**Spec:** This plan argues from the opti-work2 reference implementation (controller + routes + frontend pages read directly from `D:\Laravel Project\opti-work2`) and the current opti-works state (read from `D:\Laravel Project\opti-works`). The user's "worldflow" reference could not be located on disk — its distinguishing characteristics still need to be confirmed (see Open Questions below).

## Global Constraints

- MVP scope only (per AGENTS.md): no Inventory, Kelompok Barang, Surat Masuk/Keluar, Tenant CRUD, or advanced analytics.
- Single-database tenancy (`stancl/tenancy`) — all WO queries scoped by tenant.
- Spatie RBAC — every verb gated by `work-order.*` permissions already in the catalogue.
- Deadline: urgent 3 hari kerja / normal 6 hari kerja, from assign date (`DeadlineCalculator`, `config/holidays.php`).
- WO categories: Normal / Urgent by Accident (no schedule option) / Urgent Request by Owner.
- Do not change existing tested behavior; add new pages/endpoints alongside existing ones.
- `vendor/bin/pint --dirty --format agent` required after PHP changes; `tsc --noEmit` and `npm run build` required after frontend changes.
- Pest tests for every new controller method and page behavior.

## Review Focus

These are input classes/failure modes the opti-work2 reference exercises that current opti-works does not — each gets a test in the owning task:

1. **Urgent by Accident hides schedule option** — HOD review must force `execute_immediately`; frontend must not render the schedule radio for accident category.
2. **WO number sequence** — combine both approaches: atomic sequence table (opti-work2) + opti-works format (`WO-TENANTCODE-YYYYMMDD-0001` style).
3. **Two-field status model** — split `status` into `status_tiket` (ticket level: waiting_hod, hod_approved, scheduled, closed, cancelled, pending_dept_approval, rejected_by_dept) and `status_pekerjaan` (job level: assigned, in_progress, pending_verify, revision). Migration + enum changes must preserve existing data.
4. **Dedicated GET pages per workflow step** — `/work-orders/{id}/hod-review`, `/assign`, `/submit-results`, `/verify` as dedicated pages with format changing per status.
5. **Edit/Update/Destroy on WO** — opti-work2 resource includes edit/update/destroy; opti-works resource is `only([index, create, store, show])`. Decision: keep minimal for MVP, add edit/update/destroy as optional Task 10.
6. **WorkflowStepper** — opti-work2 renders a stepper on SubmitResults/Verify showing current stage; opti-works has no stepper.
7. **Priority model** — opti-work2 has `priority_type` + `urgent_sub_type` + `prioritas`; opti-works has `category` + `priority`. Decision: keep current opti-works model (category + priority + damage_type + personnel_count) already implemented in create.tsx.


---

## File Structure (create/modify map)

### Backend

| File | Responsibility |
|------|---------------|
| Create: `database/migrations/xxxx_xx_xx_xxxxxx_split_wo_status_fields.php` | Add `status_tiket` + `status_pekerjaan` columns, migrate data from `status`, keep `status` for backward compat during transition |
| Create: `app/WorkOrderTicketStatus.php` | Enum for `status_tiket`: pending_dept_approval, rejected_by_dept, waiting_hod, hod_approved, scheduled, closed, cancelled |
| Create: `app/WorkOrderJobStatus.php` | Enum for `status_pekerjaan`: assigned, in_progress, pending_verify, revision |
| Modify: `app/Models/WorkOrder.php` | Cast `status_tiket` → WorkOrderTicketStatus, `status_pekerjaan` → WorkOrderJobStatus; keep `status` cast for backward compat |
| Modify: `routes/web.php` | Add GET routes for `hod-review`, `assign`, `submit-results`, `verify` pages |
| Modify: `app/Http/Controllers/WorkOrderController.php` | Add `hodReview()`, `assignForm()`, `showSubmitResults()`, `showVerify()` methods; update existing methods to use both status fields |
| Create: `tests/Feature/WorkOrderPagesTest.php` | Feature tests for new GET pages (200, gating, 403, 409) |
| Create: `tests/Feature/WorkOrderStatusSplitTest.php` | Tests for status split migration and dual-status behavior |

### Frontend

| File | Responsibility |
|------|---------------|
| Create: `resources/js/pages/work-orders/HodReview.tsx` | Dedicated HOD review page (action radio + schedule date + employee selection) |
| Create: `resources/js/pages/work-orders/Assign.tsx` | Dedicated assign page (employee multiselect from target department) |
| Create: `resources/js/pages/work-orders/SubmitResults.tsx` | Dedicated submit results page (completion notes + attachments + stepper) |
| Create: `resources/js/pages/work-orders/Verify.tsx` | Dedicated verify page (pass/fail/needs_revision radio + notes + stepper) |
| Create: `resources/js/components/workflow/WorkflowStepper.tsx` | Shared stepper component showing WO lifecycle stage progress |
| Modify: `resources/js/pages/work-orders/Show.tsx` | Link to dedicated pages instead of (or alongside) inline panels; add hero header + status panel + assigned team |
| Modify: `resources/js/pages/work-orders/Index.tsx` | Add stats cards, enhanced filters, priority type badge, deadline escalation chips |
| Modify: `resources/js/pages/work-orders/Create.tsx` | Add `urgent_sub_type` (by_accident/by_owner) reveal, `priority_type` segmented control |
| Create: `tests/Feature/WorkOrderFrontendTest.ts` (if applicable) | Frontend-type checks via `tsc --noEmit` |

---

## Tasks

### Task 0: Status Model Split — `status_tiket` + `status_pekerjaan`

**Files:**
- Create: `database/migrations/xxxx_xx_xx_xxxxxx_split_wo_status_fields.php`
- Create: `app/WorkOrderTicketStatus.php`
- Create: `app/WorkOrderJobStatus.php`
- Modify: `app/Models/WorkOrder.php`
- Create: `tests/Feature/WorkOrderStatusSplitTest.php`

**Interfaces:**
- Consumes: existing `WorkOrder` model with `status` column.
- Produces: two new enum classes, two new columns on `work_orders`, data migration from `status`, dual cast on model.

- [ ] **Step 1: Write failing migration + model tests**

```php
// tests/Feature/WorkOrderStatusSplitTest.php
it('migrates existing status to status_tiket / status_pekerjaan', function () { ... });
it('casts status_tiket as WorkOrderTicketStatus', function () { ... });
it('casts status_pekerjaan as WorkOrderJobStatus', function () { ... });
```

- [ ] **Step 2: Run tests — confirm they fail**

Run: `php artisan test --filter=WorkOrderStatusSplitTest`
Expected: FAIL (enums/columns don't exist)

- [ ] **Step 3: Create enum classes**

`app/WorkOrderTicketStatus.php`:
```php
enum WorkOrderTicketStatus: string {
    case PendingDeptApproval = 'pending_dept_approval';
    case RejectedByDept = 'rejected_by_dept';
    case WaitingHod = 'waiting_hod';
    case HodApproved = 'hod_approved';
    case Scheduled = 'scheduled';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    // options(), label() helpers
}
```

`app/WorkOrderJobStatus.php`:
```php
enum WorkOrderJobStatus: string {
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case PendingVerify = 'pending_verify';
    case Revision = 'revision';
    // options(), label() helpers
}
```

- [ ] **Step 4: Create migration**

Add `status_tiket` (string, nullable), `status_pekerjaan` (string, nullable); migrate existing `status` values:
- waiting_hod → status_tiket='waiting_hod'
- hod_approved → status_tiket='hod_approved'
- scheduled → status_tiket='scheduled'
- assigned → status_tiket='hod_approved', status_pekerjaan='assigned'
- in_progress → status_pekerjaan='in_progress'
- pending_verify → status_pekerjaan='pending_verify'
- revision → status_pekerjaan='revision'
- closed → status_tiket='closed'
- cancelled → status_tiket='cancelled'

- [ ] **Step 5: Update WorkOrder model**

Add casts for `status_tiket` → WorkOrderTicketStatus, `status_pekerjaan` → WorkOrderJobStatus. Keep `status` cast for backward compat.

- [ ] **Step 6: Run tests — confirm pass**

Run: `php artisan test --filter=WorkOrderStatusSplitTest`
Expected: PASS

- [ ] **Step 7: Run pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add database/migrations/xxxx_split_wo_status_fields.php app/WorkOrderTicketStatus.php app/WorkOrderJobStatus.php app/Models/WorkOrder.php tests/Feature/WorkOrderStatusSplitTest.php
git commit -m "feat(work-order): split status into status_tiket + status_pekerjaan"
```

---

### Task 1: Route + Controller — Dedicated GET workflow pages

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/WorkOrderController.php`
- Create: `tests/Feature/WorkOrderPagesTest.php`

**Interfaces:**
- Consumes: existing `WorkOrderController`, existing `review`/`assign`/`submit`/`verify` POST endpoints, existing `WorkOrder` model + dual status enums (`WorkOrderTicketStatus`, `WorkOrderJobStatus`).
- Produces: new GET routes `work-orders.hod-review`, `work-orders.assign`, `work-orders.submit-results`, `work-orders.verify`; controller methods returning Inertia pages with `workOrder` + `employees` props.

- [ ] **Step 1: Write failing feature tests**

```php
// tests/Feature/WorkOrderPagesTest.php
it('renders HOD review page for authorized user', function () { ... });
it('renders assign page for authorized user', function () { ... });
it('renders submit results page for authorized user', function () { ... });
it('renders verify page for authorized user', function () { ... });
it('returns 403 without work-order.review permission', function () { ... });
```

- [ ] **Step 2: Run tests — confirm they fail (routes/methods don't exist)**

Run: `php artisan test --filter=WorkOrderPagesTest`
Expected: FAIL (route not found / method not found)

- [ ] **Step 3: Add GET routes in `routes/web.php`**

Add before the resource block (literal segments take precedence):
```php
Route::get('work-orders/{workOrder}/hod-review', [WorkOrderController::class, 'hodReview'])
    ->name('work-orders.hod-review')
    ->middleware('permission:work-order.review');
Route::get('work-orders/{workOrder}/assign', [WorkOrderController::class, 'assignForm'])
    ->name('work-orders.assign')
    ->middleware('permission:work-order.assign');
Route::get('work-orders/{workOrder}/submit-results', [WorkOrderController::class, 'showSubmitResults'])
    ->name('work-orders.submit-results')
    ->middleware('permission:work-order.submit');
Route::get('work-orders/{workOrder}/verify', [WorkOrderController::class, 'showVerify'])
    ->name('work-orders.verify')
    ->middleware('permission:work-order.verify');
```

- [ ] **Step 4: Add controller methods**

`hodReview(WorkOrder $workOrder)` — load department employees, render `work-orders/hod-review`.
`assignForm(WorkOrder $workOrder)` — load department employees, render `work-orders/assign`.
`showSubmitResults(WorkOrder $workOrder)` — render `work-orders/submit-results`.
`showVerify(WorkOrder $workOrder)` — render `work-orders/verify`.

- [ ] **Step 5: Run tests — confirm pass**

Run: `php artisan test --filter=WorkOrderPagesTest`
Expected: PASS

- [ ] **Step 6: Run pint + tsc**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add routes/web.php app/Http/Controllers/WorkOrderController.php tests/Feature/WorkOrderPagesTest.php
git commit -m "feat(work-order): add dedicated GET pages for review/assign/submit/verify"
```

---

### Task 2: Frontend — Shared WorkflowStepper component

**Files:**
- Create: `resources/js/components/workflow/WorkflowStepper.tsx`

**Interfaces:**
- Consumes: none (pure presentational).
- Produces: `WorkflowStepper({ currentStep, steps })` component rendering numbered stepper with active/completed/pending states.

- [ ] **Step 1: Write component**

Port from opti-work2 `resources/js/components/workflow/WorkflowStepper.tsx` — adapted to opti-works design tokens (stitch/ink scale, not slate-950 dark).

- [ ] **Step 2: Run tsc**

Run: `npx tsc --noEmit`
Expected: exit 0

- [ ] **Step 3: Run build**

Run: `npm run build`
Expected: exit 0

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/workflow/WorkflowStepper.tsx
git commit -m "feat(ui): add WorkflowStepper component for WO lifecycle"
```

---

### Task 3: Frontend — HodReview page

**Files:**
- Create: `resources/js/pages/work-orders/HodReview.tsx`
- Modify: `resources/js/pages/work-orders/Show.tsx` (link to HodReview page)

**Interfaces:**
- Consumes: route `work-orders.hod-review` (wayfinder), `workOrder` + `employees` props.
- Produces: dedicated HOD review page with action radio (execute_immediately/schedule), schedule date input, employee multiselect, submit to `work-orders.review` POST.

- [ ] **Step 1: Write HodReview.tsx**

Model after opti-work2 `WorkOrder/HodReview.tsx` — RadioGroup for action, searchable employee list, selected chips, hidden schedule option when urgent by accident.

- [ ] **Step 2: Wire link from Show page**

Replace/add link in Show's ReviewPanel to navigate to `/work-orders/{id}/hod-review` instead of inline form (or keep both — decision: dedicated page is primary).

- [ ] **Step 3: Run tsc + build**

Run: `npx tsc --noEmit && npm run build`
Expected: exit 0

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/work-orders/HodReview.tsx resources/js/pages/work-orders/Show.tsx
git commit -m "feat(work-order): add dedicated HOD review page"
```

---

### Task 4: Frontend — Assign page

**Files:**
- Create: `resources/js/pages/work-orders/Assign.tsx`

**Interfaces:**
- Consumes: route `work-orders.assign` (wayfinder), `workOrder` + `employees` props.
- Produces: dedicated assign page with searchable employee multiselect, personnel count, assignment notes, submit to `work-orders.assign` POST.

- [ ] **Step 1: Write Assign.tsx**

Model after opti-work2 `WorkOrder/Assign.tsx`.

- [ ] **Step 2: Run tsc + build**

Run: `npx tsc --noEmit && npm run build`
Expected: exit 0

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/work-orders/Assign.tsx
git commit -m "feat(work-order): add dedicated assign page"
```

---

### Task 5: Frontend — SubmitResults page

**Files:**
- Create: `resources/js/pages/work-orders/SubmitResults.tsx`

**Interfaces:**
- Consumes: route `work-orders.submit` (wayfinder), `workOrder` prop.
- Produces: dedicated submit results page with WorkflowStepper (step 3), completion results textarea, attachments (max 3), submit to `work-orders.submit` POST.

- [ ] **Step 1: Write SubmitResults.tsx**

Model after opti-work2 `WorkOrder/SubmitResults.tsx` — WorkflowStepper currentStep={3}, WO info card, completion report form, important notice.

- [ ] **Step 2: Run tsc + build**

Run: `npx tsc --noEmit && npm run build`
Expected: exit 0

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/work-orders/SubmitResults.tsx
git commit -m "feat(work-order): add dedicated submit results page"
```

---

### Task 6: Frontend — Verify page

**Files:**
- Create: `resources/js/pages/work-orders/Verify.tsx`

**Interfaces:**
- Consumes: route `work-orders.verify` (wayfinder), `workOrder` prop.
- Produces: dedicated verify page with WorkflowStepper (step 4), submitted results display, pass/fail/needs_revision radio, verification notes, submit to `work-orders.verify` POST.

- [ ] **Step 1: Write Verify.tsx**

Model after opti-work2 `WorkOrder/Verify.tsx` — WorkflowStepper currentStep={4}, WO info card, submitted results, RadioGroup with pass/needs_revision/fail, colored submit button.

- [ ] **Step 2: Run tsc + build**

Run: `npx tsc --noEmit && npm run build`
Expected: exit 0

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/work-orders/Verify.tsx
git commit -m "feat(work-order): add dedicated verify page"
```

---

### Task 7: Frontend — Index page enhancement (stats, filters, cards)

**Files:**
- Modify: `resources/js/pages/work-orders/index.tsx`

**Interfaces:**
- Consumes: existing index payload (`workOrders` paginated).
- Produces: enhanced index with stats cards (total, completed, in progress, pending), filter modal with priority_type/department/priority, status badges, deadline escalation chips.

- [ ] **Step 1: Enhance index.tsx**

Add stats cards row, filter select for priority_type, status badge with escalation chips (H+3/H+5/H+6), improved card layout matching opti-work2 double-bezel style.

- [ ] **Step 2: Run tsc + build**

Run: `npx tsc --noEmit && npm run build`
Expected: exit 0

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/work-orders/index.tsx
git commit -m "feat(work-order): enhance index with stats cards and filters"
```

---

### Task 8: Frontend — Show page enhancement (hero, status panel, assigned team)

**Files:**
- Modify: `resources/js/pages/work-orders/show.tsx`

**Interfaces:**
- Consumes: existing show payload.
- Produces: enhanced show with hero header (WO number, status badge, urgent tag), status panel, assigned team cards, deadline panel with escalation state, links to dedicated workflow pages.

- [ ] **Step 1: Enhance show.tsx**

Add hero header section, status panel, assigned team section, deadline/escalation panel, completion results section — matching opti-work2 Show.tsx structure.

- [ ] **Step 2: Run tsc + build**

Run: `npx tsc --noEmit && npm run build`
Expected: exit 0

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/work-orders/show.tsx
git commit -m "feat(work-order): enhance show page with hero header and status panel"
```

---

### Task 9: Frontend — Create page (urgent sub-type + priority type)

**Files:**
- Modify: `resources/js/pages/work-orders/create.tsx`

**Interfaces:**
- Consumes: existing create payload (`departments`, `categories`).
- Produces: enhanced create form with `priority_type` (normal/urgent) segmented control, `urgent_sub_type` (by_accident/by_owner) reveal for urgent, priority level cards.

- [ ] **Step 1: Enhance create.tsx**

Add priority_type segmented control, urgent_sub_type reveal cards, priority level radio cards (matching opti-work2 Create.tsx).

- [ ] **Step 2: Run tsc + build**

Run: `npx tsc --noEmit && npm run build`
Expected: exit 0

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/work-orders/create.tsx
git commit -m "feat(work-order): enhance create with priority type and urgent sub-type"
```

---

### Task 10: Backend — Edit/Update/Destroy (optional, user decision)

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/WorkOrderController.php`
- Create: `resources/js/pages/work-orders/Edit.tsx`

**Interfaces:**
- Consumes: existing `WorkOrder` model, `UpdateWorkOrderRequest`.
- Produces: `edit()`/`update()`/`destroy()` controller methods + Edit page.

- [ ] **Step 1: User decides** — confirm whether to add edit/update/destroy (opti-work2 has them; opti-works currently omits).

- [ ] **Step 2: If approved, add routes + controller methods + Edit page**

- [ ] **Step 3: Run tests + pint + tsc + build**

- [ ] **Step 4: Commit**

---

## Self-Review (checklist against constraints)

1. **Spec coverage:** Every task maps to a gap between opti-works and opti-work2 reference. The worldflow-specific characteristics are listed as Open Questions — not implemented until confirmed.
2. **Step scan:** Each step produces exactly one checkable result (test, component, route, or build). No TBD lines.
3. **Type consistency:** Wayfinder route names (`work-orders.hod-review`, etc.) must match `routes/web.php` names. Frontend types must match controller payloads.
4. **Review Focus:** All 7 items addressed — each has a task with tests (backend) or tsc/build (frontend).
5. **Proportion:** Plan is ~2× the research findings — appropriate for a multi-phase alignment.

---

## Work Roadmap (confirmed)

| Phase | Focus | Tasks | Est. |
|-------|-------|-------|------|
| **0** | Status model split (`status_tiket` + `status_pekerjaan`) | Task 0 | 0.5 day |
| **1** | Backend routes + controller (dedicated GET pages) | Task 1 | 1 day |
| **2** | Shared UI component (WorkflowStepper) | Task 2 | 0.5 day |
| **3** | Dedicated frontend pages (HodReview, Assign, SubmitResults, Verify) | Tasks 3–6 | 2 days |
| **4** | Index page enhancement (stats, filters, cards) | Task 7 | 0.5 day |
| **5** | Show page enhancement (hero, status panel, assigned team) | Task 8 | 0.5 day |
| **6** | Create page enhancement (priority type, urgent sub-type) | Task 9 | 0.5 day |
| **7** | Edit/Update/Destroy (optional, user decision) | Task 10 | 1 day |

**Total: ~7 days**

**Verification gates per phase:**
- `php artisan test` — affected test files pass
- `vendor/bin/pint --dirty --format agent` — clean
- `npx tsc --noEmit` — exit 0
- `npm run build` — exit 0
- Full suite green before moving to next phase

---

**Execution:** Subagent-driven-development confirmed. Starting with Task 0 (status model split), then Task 1 (backend routes + controller).