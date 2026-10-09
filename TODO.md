# TODO - Spatie Multitenancy v4 Migration Progress

## Completed
- ✅ Replaced manual tenant CRUD with Optigate API sync approach
- ✅ Tenant model uses ULID primary key, `optigate_company_id` (unique), `code` (URL slug)
- ✅ Migration: Restructured tenants table with ULID id, optigate_company_id, code
- ✅ Updated TenantFinder to find by `code` from route parameter
- ✅ Updated TenantAccess service for ULID compatibility
- ✅ Updated TenantSwitchController to use code for routing
- ✅ Updated EnsureTenantAccess middleware for code-based tenant access
- ✅ Fixed all models using BelongsToTenant trait for ULID compatibility
- ✅ Updated controller route redirects to use `tenant()->code` instead of UUID
- ✅ Fixed WorkOrderService to use tenant code for nomor_wo generation
- ✅ Added explicit model binding for workOrder parameter in routes
- ✅ Fixed WorkOrder show/attachment endpoints (removed implicit model binding conflicts)
- ✅ Updated TenantFactory to match new structure (optigate_company_id, code as slug)
- ✅ Updated tests to use new tenant structure (DashboardTest, MultitenancyTest)
- ✅ Fixed QueueTenancyBootstrapper tests for Spatie v4 behavior

## Active/In Progress
- 🔄 **Queue Tenant Awareness** - Spatie v4's MakeQueueTenantAwareAction restores tenant at JobProcessing time but tenant scope not filtering work orders correctly
  - Job sees correct tenant (seenTenantKey = HQ ULID) 
  - But WorkOrder query returns empty array even with tenant scope
  - Need to debug BelongsToTenant global scope application in queue worker context

## Blocked/Issues
- WorkOrder global scope `tenant` uses `Tenant::current()` which returns correct tenant in job
- But query returns 0 results - likely tenant_id mismatch in database (ULID vs string)
- Need to verify work orders were created with correct tenant_id in tests

## Next Steps (Tomorrow)
1. Debug why WorkOrder query returns empty in queue job despite correct tenant context
2. Check if work orders created in tests have proper tenant_id (ULID format)
3. Verify Spatie's Context facade integration with queue worker
4. Ensure Multitenancy::start() is called in console/queue worker context
5. Run full test suite to ensure no regressions

## Files Modified
- app/Models/Tenant.php
- database/migrations/2026_10_09_121502_restructure_tenants_table.php
- app/TenantFinder.php
- app/Services/TenantAccess.php
- app/Http/Controllers/TenantSwitchController.php
- app/Http/Middleware/EnsureTenantAccess.php
- app/Http/Controllers/WorkOrderController.php
- app/Services/WorkOrderService.php
- routes/web.php
- database/factories/TenantFactory.php
- config/multitenancy.php
- tests/Feature/DashboardTest.php
- tests/Feature/MultitenancyTest.php
- tests/Feature/QueueTenancyBootstrapperTest.php
- tests/Support/ProbeTenantContext.php
- tests/Pest.php (createTestTenant, initTenant helpers)