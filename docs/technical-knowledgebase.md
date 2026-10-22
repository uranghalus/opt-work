# Technical Knowledge Base - OptiWorks

## Project Overview

**Name:** OptiWorks - Work Management System (WMS)
**Status:** MVP (in development)
**Stack:** Laravel 13 + PHP 8.3, Inertia React 19, MySQL, Spatie Permission 8.3

## Architecture & Key Components

### 1. Multitenancy System (Spatie v4)

**Identification Method:** Path-based
- Routes: `/{tenant}/...` where tenant is a URL slug (NOT UUID)
- Example: `/hq/work-orders`, `/plant-1/dashboard`

**Key Classes:**
- `App\TenantFinder` - Resolves tenant from route parameter or session
- `App\Models\Tenant` - Tenant model with ULID primary key and `code` slug
- `App\Models\Concerns\BelongsToTenant` - Trait applying `tenant_id` global scope

**Critical Pattern:**
```php
// Tenant model uses BelongsToTenant trait
use BelongsToTenant;
// Global User model stores tenant_id as home branch
// User model is NOT tenant-scoped (no global scope)
```

### 2. Role-Based Access Control (Spatie Permission)

**System Roles (seeded, cannot be deleted):**
- `super_admin` - Full access, bypass gates
- `admin_tenant` - Full tenant management
- `general_manager` - Read-only master data, work order review/verify
- `deputy_general_manager` - Read-only master data, work order review/verify
- `hod` - Head of Department, can review, assign, verify WO
- `team_leader` - Team leader, escalation recipient
- `karyawan` - Employee, can create/read/submit work orders
- `field_staff` - Field worker (superset of karyawan)
- `viewer` - Read-only access
- `guest` - Pre-provisioning state (0 permissions)

**Key Files:**
- `database/seeders/RoleAndPermissionSeeder.php` - Role/permission seeding
- `app/Models/User.php` - `isSuperAdmin()` method for bypass

### 3. Work Order Domain

**States (WorkOrderStatus enum):**
1. `draft` - Draf
2. `waiting_hod` - Menunggu HOD
3. `hod_approved` - Disetujui HOD
4. `scheduled` - Terjadwal
5. `assigned` - Ditugaskan
6. `in_progress` - Dikerjakan
7. `pending_verify` - Menunggu Verifikasi
8. `revision` - Revisi
9. `closed` - Selesai

**Categories (WorkOrderCategory enum):**
- `normal` - 6 business day SLA, can be scheduled
- `accident` - 3 business day SLA, MUST execute immediately
- `owner` - 3 business day SLA, HOD chooses scheduling

**Key Model Fields:**
```php
// work_orders table
deadline_date           // Calculated deadline
escalation_h3_sent_at   // H+3 escalation timestamp
escalation_h5_sent_at   // H+5 escalation timestamp
escalation_h6_sent_at   // H+6 escalation timestamp
is_escalated           // Boolean flag
extend_count           // Number of extension requests
extend_reason          // Reason for extension
extended_at            // When last extended
```

### 4. Deadline Calculation

**Service:** `App\Services\DeadlineCalculator`

**Rules:**
- Urgent (Accident/Owner): 3 business days
- Normal: 6 business days
- Calculated from `assigned_at`, NOT submission date
- Skips weekends (Saturday, Sunday)
- Skips holidays from `config/holidays.php`

**Holiday Config Format:**
```php
return [
    '01-01', // Tahun Baru (recurring)
    '12-25', // Natal (recurring)
    '2026-12-31', // Fixed date (year-specific)
];
```

### 5. Escalation Chain

**Command:** `php artisan app:check-deadline-escalation`

**Escalation Timeline:**
| Days After Assign | Recipient |
|-------------------|-----------|
| H+3 | Team Leader (if exists in department) |
| H+5 | HOD (direct or fallback chain) |
| H+6 | DGM/GM (or HOD fallback) |

**Fallback Chain for HOD:**
1. `hod_user_id` on department
2. `manager_user_id` on department
3. All users with `hod` role in department
4. Super Admin (with tenant_id match) + log critical

### 6. Notification System

**API:** Laravel Broadcasting + Reverb

**Routes:**
- `/notifications` - In-app notification index
- `POST /notifications/{id}/read` - Mark as read
- `POST /notifications/read-all` - Mark all as read

**Real-time Channel:**
- Private channel: `App.Models.User.{user_id}`
- Uses Laravel Echo with Reverb broadcaster

**Key File:** `resources/js/components/realtime-notifications.tsx`

**Notification Types:**
- `WorkOrderCreated` - New WO submitted
- `WorkOrderReviewed` - WO sent to review
- `WorkOrderAssigned` - WO assigned to employee
- `WorkOrderSubmitted` - Field worker submitted results
- `WorkOrderVerified` - WO verified (closed)
- `WorkOrderRejected` - WO rejected, needs revision
- `WorkOrderDeadlineEscalated` - SLA breach notification

### 7. WhatsApp Integration

**ADR:** `docs/adr/0009-whatsapp-gateway.md`

**Key Components:**
- `App\Models\WaMessage` - Outbox table (NOT tenant-scoped)
- `App\Notifications\WorkOrderCreated::toWhatsApp()` - Template
- `App\Channels\WahaWhatsAppChannel` - Laravel notification channel
- `App\Jobs\SendWaMessageJob` - Queued job with retries

**Supported Events:**
- `work_order.created`
- `work_order.reviewed`
- `work_order.assigned`
- `work_order.escalated`

**Config:** `config/waha.php`

### 8. Frontend Architecture

**Framework:** Vite + React 19 + Inertia.js v3

**Key Components:**
- `resources/js/app.tsx` - App entry point
- `resources/js/components/` - UI components
- `resources/js/pages/` - Route pages
- `resources/js/layouts/` - Layout wrappers
- `resources/js/hooks/` - React hooks

**Design System:**
- Colors: Defined in `DESIGN.md`
- Components: Radix UI (dropdown-menu, dialog, select, etc.)
- Icons: Lucide React

**State Management:**
- Inertia's shared props via `HandleInertiaRequests` middleware
- Auth state in `usePage().props.auth`

### 9. Tenant Data Models

**Key Models:**
- `Tenant` - Main tenant with ULID (`id`), `code` (slug), `optigate_company_id`
- `Employee` - Karyawan with `banned_at`, `department_id`, `position_id`, `division_id`
- `User` - Platform user (NOT tenant-scoped), stores `tenant_id` as home branch
- `Department` - Has `hod_user_id`, `manager_user_id`, `hod_employee_id`
- `Position`, `Division` - Organization hierarchy

**Relationships:**
```php
// Employee belongs to Department, Position, Division
// Department has many Employees, Divisions, Positions
// WorkOrder belongs to Department (target), User (requester)
```

### 10. Key Routes Structure

**Tenant Routes (prefixed with `{tenant}`):**
```
GET    /{tenant}/dashboard
GET    /{tenant}/work-orders
POST   /{tenant}/work-orders
GET    /{tenant}/work-orders/{wo}
POST   /{tenant}/work-orders/{wo}/review
POST   /{tenant}/work-orders/{wo}/assign
POST   /{tenant}/work-orders/{wo}/submit
POST   /{tenant}/work-orders/{wo}/verify
GET    /{tenant}/employees
GET    /{tenant}/departments
GET    /{tenant}/positions
```

**Global Routes (no tenant prefix):**
```
GET    /hak-akses (role management)
GET    /settings/profile
GET    /admin/dashboard (super admin)
```

### 11. Configuration Files

**Key Configs:**
- `config/multitenancy.php` - Spatie multitenancy settings
- `config/permission.php` - RBAC settings
- `config/holidays.php` - Holiday calendar
- `config/waha.php` - WhatsApp gateway settings
- `config/broadcasting.php` - Reverb connection

**Environment Variables (.env):**
```
REVERB_APP_KEY, REVERB_APP_SECRET, REVERB_HOST
WAHA_URL, WAHA_API_KEY, WAHA_SESSION
SUPER_ADMIN_EMAIL
```

### 12. Testing

**Test Structure:**
- `tests/Feature/` - Feature tests
- `tests/Unit/` - Unit tests
- `tests/Pest.php` - Test helpers and configuration

**Key Test Helpers:**
- `createTestTenant()` - Create isolated tenant
- `initTenant($code)` - Set current tenant
- `createUser()` - Create test user
- `makeSuperAdmin()` - Grant super admin
- `makeHod()` - Grant HOD role

### 13. Development Commands

```bash
# Development
composer setup          # Setup fresh
php artisan dev         # Start dev server
php artisan test        # Run tests

# Build
npm run build           # Production build
npm run dev             # Dev mode (Vite)

# Lint & Check
composer lint           # Run Pint
composer types:check    # Run PHPStan
npm run types:check     # Run TypeScript check
```

## Critical Gotchas

1. **Tenant Scope:** Always use `withoutGlobalScope('tenant')` when querying across tenants
2. **Wayfinder Routes:** Use generated functions from `@/routes/` for type safety
3. **User Model:** Global, not tenant-scoped - stores `tenant_id` as home branch
4. **Employee Model:** Tenant-scoped, has `banned_at` for record-level blocks
5. **Work Order Number:** Generated as `WO-{TENANT_CODE}-{DATE}-{SEQUENCE}`
6. **Database Seeder:** Run in correct order: Framework → Tenant → Organization → Roles → Data

## Migration Notes

Based on git history, major changes include:
- Migration from stancl/tenancy to Spatie laravel-multitenancy v4
- Addition of deadline_date and escalation fields
- WhatsApp integration via WAHA
- Guest role for pre-provisioned accounts
- Audit events table for tracking actions

## Next Actions

Based on TODO.md analysis, current focus areas:
1. Work Order create/review/assign flow (completed)
2. Timeline view for scheduled work (completed)
3. Department page review and fixes (completed)
4. WhatsApp tenant sessions (completed)
5. Employee search filters (in progress)