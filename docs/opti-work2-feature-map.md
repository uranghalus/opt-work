---
created: 2026-10-08
tags: [reference, migration, opti-work2, prd-mapping]
status: active
---

# opti-work2 → opti-works Feature Reference Map

> **Purpose**: Quick lookup for porting features from opti-work2 (reference implementation) to opti-works (clean rebuild with reduced MVP scope).
> **Source**: opti-work2 codebase as of 2026-10-08 | Target: opti-works PRD v1.0 Draft

---

## Scope Differences at a Glance

| Area | opti-work2 (full) | opti-works (MVP) | Action |
|------|-------------------|------------------|--------|
| **Tenant** | CRUD + multi-tenancy (spatie/laravel-multitenancy v4) | **NOT in MVP** (v2) | Skip `TenantController`, tenant sync commands, tenant routes |
| **Inventory** | Full CRUD (`InventoryController`, `KelompokBarangController`) | **NOT in MVP** | Skip entirely |
| **WhatsApp/WAHA** | `WorkOrderNotification`, `WahaController`, webhook | **NOT in MVP** (in-app only) | Skip WA channels; keep `AppNotificationService` + Reverb |
| **Surat Masuk/Keluar** | Not implemented | **NOT in product** | Ignore |
| **Multi-tenancy** | spatie/laravel-multitenancy v4 | stancl/tenancy (single-db, path) | Different package — adapt `TenantAware` trait |
| **Daily Work Template** | ❌ Not implemented | **MVP** (per-karyawan, HOD-managed) | **New implementation needed** |

---

## Model Mapping (opti-work2 → opti-works)

| opti-work2 Model | Table | opti-works Target | Notes |
|------------------|-------|-------------------|-------|
| `WorkOrder` | `tb_work_order` | `work_orders` | Core — port with FR-1.x, FR-2.x logic |
| `WorkPlanning` | `tb_work_planning` | `work_planning` | Scheduled WO — port FR-2.4 (extend schedule) |
| `WorkDaily` | `tb_work_daily` | `work_daily` | Daily Work — **add template support** (FR-5.1) |
| `WorkData` | `tb_work_data` | `work_data` | History — make append-only (FR-3.3) |
| `WorkDataPekerja` | `tb_work_data_pekerja` | `work_data_pekerja` | Assignment link |
| `ScheduleWorkData` | `tb_schedule_wd` | `schedule_wd` | Master schedule |
| `ExtendRequest` | `tb_extend_requests` | `extend_requests` | Separate table (SETTLED) — port FR-2.3 logic |
| `AppNotification` | `app_notifications` | `app_notifications` | In-app + Reverb broadcast |
| `Department` | `tb_department` | `departments` | Master data |
| `Division` | `tb_division` | `divisions` | Master data |
| `Employee` | `tb_employee` | `employees` | Master data — no FK to department (only division) |
| `Position` | `tb_position` | `positions` | Master data |
| `User` | `users` | `users` | Auth + Spatie roles |
| `Tenant` / `Tenants` | `tenants` | **SKIP (v2)** | Duplication (OQ 12) — opti-works uses stancl/tenancy |
| `Inventory` | `tb_inventory` | **SKIP** | Non-MVP |
| `KelompokBarang` | `tb_kelompok_barang` | **SKIP** | Non-MVP |

---

## Key Controllers — Porting Checklist

### Work Management (MVP Core)

| Controller | opti-work2 Path | Port to opti-works? | Key Methods to Adapt |
|------------|-----------------|---------------------|----------------------|
| `WorkOrderController` | `WorkManagament/WorkOrderController.php` | ✅ **YES** | `store`, `hodReview`, `hodApprove`, `assign`, `assignEmployees`, `submitResults`, `verify`, `show` |
| `WorkPlanningController` | `WorkManagament/WorkPlanningController.php` | ✅ **YES** | `store` (schedule WO), `requestExtend`/`approveExtend`/`rejectExtend` (DGM/GM approval) |
| `WorkDailyController` | `WorkManagament/WorkDailyController.php` | ✅ **YES** | `index`, `create`, `store`, `updateStatus` — **add template CRUD** |
| `WorkDataController` | `WorkManagament/WorkDataController.php` | ✅ **YES** | Make append-only after WO closed; `processFromWorkOrder` |
| `ExtendRequestController` | `WorkManagament/ExtendRequestController.php` | ✅ **YES** | Add **pending duplicate check** (gap fix from PRD) |
| `WorkDataPekerjaController` | `WorkManagament/WorkDataPekerjaController.php` | ✅ **YES** | Worker allocation |
| `ScheduleWorkDataController` | `WorkManagament/ScheduleWorkDataController.php` | ✅ **YES** | Schedule management |

### Master Data (Partial)

| Controller | opti-work2 Path | Port? | Notes |
|------------|-----------------|-------|-------|
| `DepartmentController` | `MasterData/DepartmentController.php` | ✅ **YES** | Include `sync` (from Optigate) |
| `DivisionController` | `MasterData/DivisionController.php` | ✅ **YES** | Include `sync` |
| `EmployeeController` | `MasterData/EmployeeController.php` | ✅ **YES** | Include `sync` |
| `TenantController` | `MasterData/TenantController.php` | ❌ **NO** | v2 — tenant CRUD not in MVP |
| `InventoryController` | `MasterData/InventoryController.php` | ❌ **NO** | Non-MVP |
| `KelompokBarangController` | `MasterData/KelompokBarangController.php` | ❌ **NO** | Non-MVP |

### Notifications & Settings

| Controller | opti-work2 Path | Port? | Notes |
|------------|-----------------|-------|-------|
| `NotificationController` | `NotificationController.php` | ✅ **YES** | In-app only (bell + toast + history) |
| `SamlController` | `SamlController.php` | ✅ **YES** | Auth integration |
| `Settings/*` | `Settings/*.php` | ✅ **YES** | Profile, Role, Security — adapt for Spatie |

### Skipped Controllers

| Controller | Reason |
|------------|--------|
| `WhatsAppWebhookController` | WA not in MVP |
| `WahaController` | WA not in MVP |
| `EvolutionWhatsAppChannel`, `WahaWhatsAppChannel` | WA channels |

---

## Critical Services to Port

| Service | opti-work2 Path | Purpose | opti-works Notes |
|---------|-----------------|---------|------------------|
| `BusinessDayCalculator` | `Services/BusinessDayCalculator.php` | Deadline calc (skip weekends/holidays) | ✅ Port — uses `config/holidays.php` |
| `EscalationRecipientResolver` | `Services/EscalationRecipientResolver.php` | H+3/H+5/H+6 recipient chain | ✅ Port — fallback logic SETTLED |
| `TenantService` | `Services/TenantService.php` | Custom tenancy logic | ⚠️ **Replace** with stancl/tenancy |
| `AppNotificationService` | `Notifications/AppNotificationService.php` | In-app notification creation | ✅ Port — Reverb broadcast |

---

## Console Commands

| Command | opti-work2 Path | Port? | Notes |
|---------|-----------------|-------|-------|
| `deadlines:check` | `CheckWorkOrderDeadlines.php` | ✅ **YES** | Core escalation scheduler |
| `sync:departments` | `SyncDepartments.php` | ✅ **YES** | Master data sync |
| `sync:divisions` | `SyncDivisions.php` | ✅ **YES** | Master data sync |
| `sync:employees` | `SyncEmployees.php` | ✅ **YES** | Master data sync |

---

## Events & Notifications

| Class | opti-work2 Path | Port? | Notes |
|-------|-----------------|-------|-------|
| `WorkOrderCreated` | `Events/WorkOrderCreated.php` | ✅ **YES** | Trigger HOD notification |
| `WorkOrderStatusChanged` | `Events/WorkOrderStatusChanged.php` | ✅ **YES** | Broadcast status changes |
| `NotificationCreated` | `Events/NotificationCreated.php` | ✅ **YES** | Reverb broadcast |
| `WorkOrderNotification` | `Notifications/WorkOrderNotification.php` | ❌ **NO** | WA channel — skip |
| `WorkOrderProgressNotification` | `Notifications/WorkOrderProgressNotification.php` | ⚠️ **ADAPT** | Use for in-app only |

---

## Middleware

| Middleware | opti-work2 Path | Port? | Notes |
|------------|-----------------|-------|-------|
| `EnsureTenant` | `Middleware/EnsureTenant.php` | ⚠️ **ADAPT** | Replace with stancl/tenancy middleware |
| `HandleInertiaRequests` | `Middleware/HandleInertiaRequests.php` | ✅ **YES** | Standard Inertia |
| `HandleAppearance` | `Middleware/HandleAppearance.php` | ✅ **YES** | Theme handling |

---

## Key Routes (web.php) — MVP Subset

```php
// Work Orders
Route::resource('work-orders', WorkOrderController::class)->only(['index','create','store','show','edit','update','destroy']);
Route::get('/work-orders/{work_order}/hod-review', ...)->name('hod-review');
Route::post('/work-orders/{work_order}/hod-approve', ...)->name('hod-approve');
Route::get('/work-orders/{work_order}/assign', ...)->name('assign');
Route::post('/work-orders/{work_order}/assign', ...)->name('assign.store');
Route::get('/work-orders/{work_order}/submit-results', ...)->name('submit-results');
Route::post('/work-orders/{work_order}/submit-results', ...)->name('submit-results.store');
Route::get('/work-orders/{work_order}/verify', ...)->name('verify');
Route::post('/work-orders/{work_order}/verify', ...)->name('verify.store');

// Extend Requests (WO deadline)
Route::get('/work-orders/{work_order}/extend', [ExtendRequestController::class, 'create'])->name('work-orders.extend');
Route::post('/work-orders/{work_order}/extend', [ExtendRequestController::class, 'store'])->name('work-orders.extend.store');
Route::post('/extend-requests/{extend_request}/approve-tl', ...)->name('extend-requests.approve-tl');
Route::post('/extend-requests/{extend_request}/reject-tl', ...)->name('extend-requests.reject-tl');
Route::post('/extend-requests/{extend_request}/approve-hod', ...)->name('extend-requests.approve-hod');
Route::post('/extend-requests/{extend_request}/reject-hod', ...)->name('extend-requests.reject-hod');
Route::get('/extend-requests/pending', ...)->name('extend-requests.pending');

// Work Planning (Scheduled WO)
Route::resource('work-planning', WorkPlanningController::class)->only(['index','create','store','show','edit','update','destroy']);
Route::post('/work-planning/{work_planning}/extend', ...)->name('extend');
Route::post('/work-planning/{work_planning}/extend/approve', ...)->name('extend.approve');
Route::post('/work-planning/{work_planning}/extend/reject', ...)->name('extend.reject');

// Work Data
Route::resource('work-data', WorkDataController::class)->only(['index','create','store','show','edit','update','destroy']);
Route::post('/work-data/{work_order}/process-to-work-data', ...)->name('work-data.process-from-work-order');
Route::prefix('work-data/{workData}/pekerja')->group(...); // WorkDataPekerja
Route::prefix('work-data/{workData}/schedule')->group(...); // ScheduleWorkData

// Work Daily
Route::resource('work-daily', WorkDailyController::class)->only(['index','create','store','update-status','destroy']);
Route::patch('/work-daily/{work_daily}/status', ...)->name('update-status');

// Master Data (MVP only)
Route::prefix('departments')->group(...);
Route::prefix('divisions')->group(...);
Route::prefix('employees')->group(...);

// Notifications
Route::get('notifications', ...)->name('notifications.index');
Route::post('notifications/{notification}/read', ...)->name('notifications.read');
Route::post('notifications/read-all', ...)->name('notifications.read-all');

// SAML
Route::prefix('saml')->group(...);
```

**REMOVED from MVP**: `tenants`, `inventory`, `kelompok-barang` resource routes.

---

## Database Migrations — MVP Subset

| Migration | opti-work2 Filename | Port? | Notes |
|-----------|---------------------|-------|-------|
| Users, cache, jobs | `0001_01_01_*` | ✅ **YES** | Laravel defaults |
| Work Order | `2026_06_24_110000_add_workorder_table.php` | ✅ **YES** | Rename to `create_work_orders_table` |
| Workflow fields | `2026_06_24_150000_add_workflow_fields_to_work_orders.php` | ✅ **YES** | |
| Work Data | `2026_06_25_065733_add_workdata_table.php` | ✅ **YES** | |
| Department | `2026_06_26_012154_add_department_table.php` | ✅ **YES** | |
| Daily Work | `2026_06_26_152009_add_daily_work_table.php` | ✅ **YES** | **Add template columns** |
| Employees | `2026_06_28_010615_create_employees_table.php` | ✅ **YES** | |
| Positions | `2026_06_28_013657_create_positions_table.php` | ✅ **YES** | |
| Divisions | `2026_06_30_063746_create_divisions_table.php` | ✅ **YES** | |
| Division links | `2026_06_30_063828_add_division_to_employees_and_positions_tables.php` | ✅ **YES** | |
| HOD/Manager | `2026_06_30_082503_add_hod_manager_to_tb_department_table.php` | ✅ **YES** | Fallback chain needs both |
| Work Order Sequences | `2026_06_30_121730_create_work_order_sequences_table.php` | ✅ **YES** | WO number generation |
| Settings | `2026_07_07_063538_create_settings_table.php` | ✅ **YES** | |
| Department FK on WO | `2026_07_08_233849_add_id_department_to_tb_work_order_table.php` | ✅ **YES** | |
| **App Notifications** | `2026_07_08_233849_create_app_notifications_table.php` | ✅ **YES** | Critical for FR-7 |
| Permissions | `2026_08_28_000000_create_permission_tables.php` | ✅ **YES** | Spatie |
| Tenant ID on tables | `2026_08_28_000001_add_tenant_id_to_tables.php` | ⚠️ **ADAPT** | stancl/tenancy handles this |
| **Deadline & Escalation** | `2026_08_28_010000_add_deadline_and_escalation_to_tb_work_order.php` | ✅ **YES** | Critical for FR-2 |
| Work Data Pekerja | `2026_08_28_060341_create_tb_work_data_pekerja_table.php` | ✅ **YES** | |
| Schedule WD | `2026_08_28_060352_create_tb_schedule_wd_table.php` | ✅ **YES** | |
| **Extend Requests** | `2026_08_28_100000_create_extend_requests_table.php` | ✅ **YES** | Separate table (SETTLED) |
| Work Planning | `2026_09_22_041626_create_work_planning_table.php` | ✅ **YES** | |
| Extend Work Planning | `2026_09_22_013130_extend_tb_work_planning_table.php` | ✅ **YES** | Schedule extend fields |
| Extend Work Daily | `2026_09_22_021735_extend_tb_work_daily_table.php` | ✅ **YES** | **Add template fields** |
| Assigned At | `2026_10_07_000001_add_assigned_at_to_tb_work_order.php` | ✅ **YES** | Deadline start date |

**SKIP**: Inventory migrations (`2026_09_22_050000_create_inventory_tables.php`), Tenant CRUD migrations.

---

## Frontend Pages — MVP Mapping

| opti-work2 Page | Path | Port? | Notes |
|-----------------|------|-------|-------|
| WorkOrder/Index | `WorkOrder/Index.tsx` | ✅ **YES** | List with filters |
| WorkOrder/Create | `WorkOrder/Create.tsx` | ✅ **YES** | Category selection (Normal/Urgent Accident/Urgent Owner) |
| WorkOrder/Edit | `WorkOrder/Edit.tsx` | ✅ **YES** | |
| WorkOrder/Show | `WorkOrder/Show.tsx` | ✅ **YES** | Detail + timeline |
| WorkOrder/HodReview | `WorkOrder/HodReview.tsx` | ✅ **YES** | HOD decide execute vs schedule |
| WorkOrder/Assign | `WorkOrder/Assign.tsx` | ✅ **YES** | Employee assignment |
| WorkOrder/SubmitResults | `WorkOrder/SubmitResults.tsx` | ✅ **YES** | **Add photo upload** (gap in opti-work2) |
| WorkOrder/Verify | `WorkOrder/Verify.tsx` | ✅ **YES** | Approve/reject/revision |
| WorkOrder/ExtendRequest | `WorkOrder/ExtendRequest.tsx` | ✅ **YES** | Max 3 days |
| WorkOrder/ExtendApproval | `WorkOrder/ExtendApproval.tsx` | ✅ **YES** | TL → HOD approval queue |
| WorkPlanning/Index | `WorkPlanning/Index.tsx` | ✅ **YES** | Scheduled WO monitoring |
| WorkPlanning/Create | `WorkPlanning/Create.tsx` | ✅ **YES** | Schedule Normal WO |
| WorkPlanning/Show | `WorkPlanning/Show.tsx` | ✅ **YES** | |
| WorkPlanning/Edit | `WorkPlanning/Edit.tsx` | ✅ **YES** | |
| WorkDaily/Index | `WorkDaily/Index.tsx` | ✅ **YES** | **Add template view** |
| WorkDaily/Create | `WorkDaily/Create.tsx` | ✅ **YES** | HOD assigns daily tasks |
| WorkData/Index | `WorkData/Index.tsx` | ✅ **YES** | History/audit trail |
| WorkData/Create | `WorkData/Create.tsx` | ✅ **YES** | Manual create (rare) |
| WorkData/Show | `WorkData/Show.tsx` | ✅ **YES** | Read-only after close |
| Dashboard | `dashboard.tsx` | ✅ **YES** | Role-based widgets |
| Notifications | (hooks/components) | ✅ **YES** | Bell, toast, history — `use-notifications.ts` |

**NEW PAGES NEEDED** (not in opti-work2):
- Daily Work Template CRUD (per employee, HOD-managed)
- Work Data "Process from WO" flow (auto-create on WO close)

---

## Key Implementation Gaps from opti-work2 (per PRD Audit)

| Gap ID | PRD Ref | opti-work2 Status | Fix Required in opti-works |
|--------|---------|-------------------|----------------------------|
| G1 | FR-1.1 | `personnel_count` only at assign, not create | Add to create validation |
| G2 | FR-1.6 | `assignEmployees()` doesn't notify workers | Add notification on assign |
| G3 | FR-1.7 | Submit results: text only, no photo upload | Add photo upload to submit |
| G4 | FR-1.9 | No status history table | Create `work_order_histories` or use `app_notifications` |
| G5 | FR-2.3 | No duplicate pending extend check | **Add check** in `ExtendRequestController::store` |
| G6 | FR-2.4 | Schedule extend routes lack permission middleware | Add `permission:work-planning.extend` |
| G7 | FR-3.1/3.3 | WorkData manually created, not append-only | Auto-create on WO verify=pass; disable update/destroy after close |
| G8 | FR-3.2 | Missing date range filter on WorkData | Add date range filter |
| G9 | FR-5.1/5.3 | **No template system** | **New: template per employee, virtual merge** |
| G10 | FR-5.4 | `tb_work_daily` missing location column | Add `lokasi` to migration |
| G11 | FR-7.2 | WA implemented | **Remove WA, in-app only** |

---

## Enums to Port

| Enum | opti-work2 Path | Port? |
|------|-----------------|-------|
| `ExtendRequestStatus` | `Enums/ExtendRequestStatus.php` | ✅ **YES** |
| `WorkDailyStatus` | `Enums/WorkDailyStatus.php` | ✅ **YES** |

---

## Config Files

| File | opti-work2 | opti-works |
|------|------------|------------|
| `holidays.php` | ✅ Exists | ✅ **Copy** — used by `BusinessDayCalculator` |
| `tenancy.php` | spatie/multitenancy config | **Replace** with stancl/tenancy config |
| `reverb.php` | ✅ Exists | ✅ **Copy** — broadcasting |
| `broadcasting.php` | ✅ Exists | ✅ **Copy** — Reverb pusher driver |

---

## Spatie Permission Seeder

**opti-work2**: `RoleAndPermissionSeeder.php` defines 9 roles with permissions.

**opti-works**: Same baseline roles (FR-6.1). Copy seeder, adjust permissions for removed features (no inventory, tenant CRUD, WA).

```php
// Baseline roles (from opti-work2):
// super_admin, admin_tenant, general_manager, deputy_general_manager,
// hod, team_leader, karyawan, karyawan_pelaksana, viewer_auditor
```

---

## Quick Start for opti-works Development

1. **Copy these migrations** (adapt table names to Laravel convention `snake_case`):
   - Users, Departments, Divisions, Positions, Employees
   - Work Orders (with deadline/escalation fields)
   - Work Planning, Work Daily, Work Data, Work Data Pekerja, Schedule WD
   - Extend Requests, App Notifications, Work Order Sequences
   - Permissions (Spatie)

2. **Copy these models** (update namespace, use `BelongsToTenant` from stancl/tenancy):
   - WorkOrder, WorkPlanning, WorkDaily, WorkData, WorkDataPekerja, ScheduleWorkData, ExtendRequest, AppNotification

3. **Copy these services**:
   - BusinessDayCalculator, EscalationRecipientResolver, AppNotificationService

4. **Copy these controllers** (strip WA, tenant CRUD, inventory):
   - WorkOrderController, WorkPlanningController, WorkDailyController, WorkDataController, ExtendRequestController, WorkDataPekerjaController, ScheduleWorkDataController
   - DepartmentController, DivisionController, EmployeeController
   - NotificationController

5. **Add NEW for opti-works**:
   - Daily Work Template model/migration/controller (per employee, HOD-managed)
   - Work Order History (auto-log on status change)
   - Append-only enforcement on WorkData after WO close
   - stancl/tenancy setup (single-db, path identification)

6. **Run command scheduler**:
   - `deadlines:check` (daily/hourly)
   - Sync commands (as needed)

---

## File Path Reference (opti-work2)

```
app/
├── Console/Commands/
│   ├── CheckWorkOrderDeadlines.php      ← PORT
│   ├── SyncDepartments.php              ← PORT
│   ├── SyncDivisions.php                ← PORT
│   └── SyncEmployees.php                ← PORT
├── Enums/
│   ├── ExtendRequestStatus.php          ← PORT
│   └── WorkDailyStatus.php              ← PORT
├── Events/
│   ├── NotificationCreated.php          ← PORT
│   ├── WorkOrderCreated.php             ← PORT
│   └── WorkOrderStatusChanged.php       ← PORT
├── Http/Controllers/
│   ├── MasterData/
│   │   ├── DepartmentController.php     ← PORT
│   │   ├── DivisionController.php       ← PORT
│   │   ├── EmployeeController.php       ← PORT
│   │   ├── InventoryController.php      ← SKIP
│   │   ├── KelompokBarangController.php ← SKIP
│   │   └── TenantController.php         ← SKIP (v2)
│   ├── Settings/                        ← PORT (adapt)
│   ├── WorkManagament/
│   │   ├── DashboardController.php      ← PORT
│   │   ├── ExtendRequestController.php  ← PORT + gap fix G5
│   │   ├── ScheduleWorkDataController.php ← PORT
│   │   ├── WorkDailyController.php      ← PORT + template
│   │   ├── WorkDataController.php       ← PORT + append-only
│   │   ├── WorkDataPekerjaController.php ← PORT
│   │   ├── WorkOrderController.php      ← PORT + gaps G1-G4
│   │   └── WorkPlanningController.php   ← PORT + gap G6
│   ├── NotificationController.py       ← PORT
│   ├── SamlController.py               ← PORT
│   ├── WhatsAppWebhookController.php    ← SKIP
│   └── WahaController.php               ← SKIP
├── Models/
│   ├── AppNotification.py              ← PORT
│   ├── Department.py                   ← PORT
│   ├── Division.py                     ← PORT
│   ├── Employee.py                     ← PORT
│   ├── ExtendRequest.py                ← PORT
│   ├── Inventory.py                    ← SKIP
│   ├── InventoryExpandData.py          ← SKIP
│   ├── KelompokBarang.py               ← SKIP
│   ├── Position.py                     ← PORT
│   ├── ScheduleWorkData.py             ← PORT
│   ├── Tenant.py / Tenants.py          ← SKIP (v2, OQ 12)
│   ├── User.py                         ← PORT (Spatie)
│   ├── WorkDaily.py                    ← PORT + template
│   ├── WorkData.py                     ← PORT + append-only
│   ├── WorkDataPekerja.py              ← PORT
│   ├── WorkOrder.py                    ← PORT
│   └── WorkPlanning.py                 ← PORT
├── Notifications/
│   ├── AppNotificationService.php       ← PORT
│   ├── WorkOrderNotification.php        ← SKIP (WA)
│   └── WorkOrderProgressNotification.py ← ADAPT (in-app only)
├── Services/
│   ├── BusinessDayCalculator.py        ← PORT
│   ├── EscalationRecipientResolver.py  ← PORT
│   └── TenantService.py                ← REPLACE (stancl/tenancy)
└── Traits/
    └── TenantAware.py                  ← REPLACE (stancl/tenancy trait)
```

---

## Open Questions from PRD (Already Settled — Do Not Re-Open)

| OQ # | Topic | Decision | Source |
|------|-------|----------|--------|
| 1 | Requester role | Not exclusive — any authenticated user | Round 1 |
| 2 | Deadline values | Urgent=3 days, Normal=6 days | Round 2 |
| 3 | Extend limits | Max 3 days/request, max 3 approved/WO, block pending duplicates | Round 2 |
| 4 | Daily Work template | Per employee, HOD-managed, virtual merge | Round 2 (Option A) |
| 5 | Team Leader | Spatie role `team_leader` | Round 1 |
| 6 | HOD fallback | hod_user_id → manager_user_id → all role hod → Admin Tenant + critical log | Round 2 |
| 7 | Notification channels | In-app only (bell+toast+history+Reverb); WA/email later | Round 2 |
| 8 | Definition of done | UAT critical + test suite green + pilot 1 dept no critical incidents | Round 2 |
| 9 | Extend Request table | Separate table `extend_requests` | Round 1 |
| 10 | ERD field verification | Done — call_sign, business_plan, no FK department on karyawan | Round 2 |
| 11 | Super Admin vs Direksi | Super Admin = config/RBAC/master data; Direksi deferred | Round 1 |
| 12 | Tenant/Tenants duplication | Consolidate to single `Tenant` model | Round 4 |
| 13 | Tenancy package | **opti-works uses stancl/tenancy** (not spatie) | Round 4 |

---

## Related Notes

- [[Decisions/tenant-strategy]] — stancl/tenancy single-db vs spatie/multitenancy
- [[Patterns/laravel-model-conventions]] — naming, traits, scopes
- [[Patterns/notification-flow]] — AppNotificationService + Reverb
- [[Mistakes/extend-duplicate-pending]] — gap G5 fix reference
- [[Sessions/2026-10/08/...]] — this analysis session

---

*Generated from opti-work2 codebase analysis. Update as opti-works implementation progresses.*
