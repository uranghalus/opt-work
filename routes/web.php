<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\DailyWorkController;
use App\Http\Controllers\ExtendRequestController;
use App\Http\Controllers\MasterData\DepartmentController;
use App\Http\Controllers\MasterData\DivisionController;
use App\Http\Controllers\MasterData\EmployeeController;
use App\Http\Controllers\MasterData\PositionController;
use App\Http\Controllers\MasterData\PositionTreeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SamlController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantSwitchController;
use App\Http\Controllers\WorkDataController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkPlanningController;
use App\Http\Controllers\WorkTimeLogController;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        // SSO on: portal. Flag off (AUTH_SSO_ENABLED=false): local login screen.
        return redirect()->route(config('auth.sso.enabled') ? 'saml.redirect' : 'login');
    }

    $user = auth()->user();

    if ($user->is_super_admin) {
        return redirect()->route('admin.dashboard');
    }

    // The dashboard route param is the tenant code (URL slug), not the ULID.
    $tenant = $user->tenant_id ? Tenant::find($user->tenant_id) : null;

    if ($tenant) {
        return redirect()->route('dashboard', ['tenant' => $tenant->code]);
    }

    // Superadmin without home tenant
    return redirect()->route('admin.dashboard');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
    });

    // Tenant switching routes (signed URLs for security)
    Route::get('tenant/switch/{tenant}', [TenantSwitchController::class, 'switch'])
        ->name('tenant.switch')
        ->middleware('signed');

    // Same-page switching: the client sends the current route name + params,
    // the backend computes the equivalent page for the target tenant.
    Route::post('tenant/switching', [TenantSwitchController::class, 'switching'])
        ->name('tenant.switching');

    Route::get('tenant/switch-url/{tenant}', [TenantSwitchController::class, 'signedSwitchUrl'])
        ->name('tenant.switch-url');

    Route::post('tenant/stop-impersonating', [TenantSwitchController::class, 'stopImpersonating'])
        ->name('tenant.stop-impersonating');

    Route::get('tenant/impersonation-status', [TenantSwitchController::class, 'impersonationStatus'])
        ->name('tenant.impersonation-status');
});

// Central Admin Panel — outside {tenant} prefix, for superadmins/executives only
Route::middleware(['auth', 'ensure.platform.tenant.access'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('root');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Unit Bisnis (cabang) — platform level, super admin only.
// Tenants are synced from the Optigate API; there is no manual CRUD.
// Lives at its own /tenants root, not under the user's settings section.
Route::middleware(['auth', 'ensure.platform.tenant.access'])->prefix('tenants')->name('tenants.')->group(function () {
    Route::get('/', [TenantController::class, 'index'])->name('index');
    Route::post('sync', [TenantController::class, 'sync'])->name('sync');
    Route::get('{tenant}', [TenantController::class, 'show'])->name('show');
});

Route::prefix('saml')->group(function () {
    // SP-initiated SSO: send the user to the identity provider.
    Route::get('redirect', [SamlController::class, 'redirect'])->name('saml.redirect');

    // Assertion consumer service: receives SAML responses over both HTTP-POST
    // and HTTP-Redirect bindings. CSRF validation is skipped on POST because
    // signed SAML messages cannot carry our CSRF token; SP-initiated
    // responses are protected by the relay state check, and IdP-initiated
    // ones by the signature/issuer/timestamp validation of the SAML provider.
    Route::match(['get', 'post'], 'acs', [SamlController::class, 'acs'])->name('saml.acs');

    // Post-login landing when the assertion's company cannot be matched to
    // an active unit bisnis. The reason key is flashed by the ACS handler
    // and mapped to fixed copy here; internals stay in the log.
    Route::get('denied', function () {
        $messages = [
            'no_company_data' => 'Login berhasil, tetapi akun Anda tidak membawa data perusahaan dari portal. Hubungi administrator.',
            'no_match' => 'Perusahaan pada akun Anda belum terdaftar sebagai unit bisnis. Minta administrator menjalankan sinkronisasi.',
            'multiple_matches' => 'Data perusahaan Anda terdeteksi ganda pada sistem. Hubungi administrator untuk perbaikan.',
            'inactive' => 'Unit bisnis Anda sedang tidak aktif. Hubungi administrator untuk mengaktifkannya kembali.',
            'no_role' => 'Akun Anda belum memiliki peran aktif. Minta administrator menetapkan peran Anda terlebih dahulu.',
            'no_tenant' => 'Akun Anda belum terhubung ke unit bisnis mana pun. Hubungi administrator.',
            'banned' => 'Akun Anda terkait dengan karyawan yang sedang diblokir. Hubungi administrator.',
        ];

        $reason = session('saml.denial_reason');

        return inertia('saml/denied', [
            'message' => $messages[$reason] ?? 'Anda tidak dapat mengakses sistem saat ini. Hubungi administrator.',
            'ssoEnabled' => (bool) config('auth.sso.enabled', true),
        ]);
    })->name('saml.denied');

    // Single logout service: receives unsolicited logout requests from the
    // identity provider. The portal registers this as 'saml/logout' so we
    // expose that path as an alias alongside the canonical 'saml/sls'.
    //
    // Both bindings are accepted: OptiGate answers an SP-initiated logout with
    // an auto-POST form carrying the LogoutResponse, while unsolicited
    // LogoutRequests may arrive over HTTP-Redirect. Accepting both verbs also
    // makes the generated SP metadata advertise both bindings for this route.
    Route::match(['get', 'post'], 'sls', [SamlController::class, 'sls'])->name('saml.sls');
    Route::match(['get', 'post'], 'logout', [SamlController::class, 'sls'])->name('saml.logout');

    // SP-initiated single logout: ends the local session, then forwards a
    // LogoutRequest to the identity provider's SLO endpoint.
    Route::post('slo', [SamlController::class, 'initiateLogout'])->name('saml.slo');

    // Publishes this app's SAML metadata for identity provider configuration.
    Route::get('metadata', [SamlController::class, 'metadata'])->name('saml.metadata');
});

Route::middleware(['auth', 'tenant.access'])->prefix('{tenant}')->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Registered before the resource: the {workOrder} wildcard would otherwise
    // swallow the literal "timeline" segment.
    Route::get('work-orders/timeline', [WorkOrderController::class, 'timeline'])
        ->name('work-orders.timeline');

    Route::get('work-orders/approvals', [WorkOrderController::class, 'approvals'])
        ->name('work-orders.approvals');

    Route::get('work-orders/my', [WorkOrderController::class, 'my'])
        ->name('work-orders.my');

    Route::get('work-orders/extend-approvals', [ExtendRequestController::class, 'approvals'])
        ->name('work-orders.extend-approvals');

    Route::resource('work-orders', WorkOrderController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->names('work-orders')
        ->parameters(['work-orders' => 'workOrder']);
    Route::post('work-orders/{workOrder}/review', [WorkOrderController::class, 'review'])
        ->name('work-orders.review');
    Route::post('work-orders/{workOrder}/assign', [WorkOrderController::class, 'assign'])
        ->name('work-orders.assign');
    Route::post('work-orders/{workOrder}/submit', [WorkOrderController::class, 'submit'])
        ->name('work-orders.submit');
    Route::post('work-orders/{workOrder}/verify', [WorkOrderController::class, 'verify'])
        ->name('work-orders.verify');
    Route::post('work-orders/{workOrder}/reject', [WorkOrderController::class, 'reject'])
        ->name('work-orders.reject');

    // Gerbang approval lintas-department (R1–R12).
    Route::post('work-orders/{workOrder}/request/approve', [WorkOrderController::class, 'approveRequest'])
        ->name('work-orders.request.approve');
    Route::post('work-orders/{workOrder}/request/reject', [WorkOrderController::class, 'rejectRequest'])
        ->name('work-orders.request.reject');
    Route::patch('work-orders/{workOrder}/request', [WorkOrderController::class, 'updateRequest'])
        ->name('work-orders.request.update');
    Route::post('work-orders/{workOrder}/request/resubmit', [WorkOrderController::class, 'resubmitRequest'])
        ->name('work-orders.request.resubmit');
    Route::post('work-orders/{workOrder}/request/cancel', [WorkOrderController::class, 'cancelRequest'])
        ->name('work-orders.request.cancel');

    // Timer durasi kerja pelaksana (R15–R18) — selesai otomatis via submit.
    Route::post('work-orders/{workOrder}/work-time/accept', [WorkTimeLogController::class, 'accept'])
        ->name('work-orders.work-time.accept');
    Route::post('work-orders/{workOrder}/work-time/start', [WorkTimeLogController::class, 'start'])
        ->name('work-orders.work-time.start');
    Route::post('work-orders/{workOrder}/work-time/pause', [WorkTimeLogController::class, 'pause'])
        ->name('work-orders.work-time.pause');
    Route::post('work-orders/{workOrder}/work-time/resume', [WorkTimeLogController::class, 'resume'])
        ->name('work-orders.work-time.resume');
    Route::get('work-orders/{workOrder}/attachments/{index}/{type?}', [WorkOrderController::class, 'attachment'])->name('work-orders.attachments.show');

    Route::post('work-orders/{workOrder}/extend-requests', [ExtendRequestController::class, 'store'])
        ->name('work-orders.extend-requests.store');
    Route::post('work-orders/{workOrder}/extend-requests/{extendRequest}/approve', [ExtendRequestController::class, 'approve'])
        ->name('work-orders.extend-requests.approve');
    Route::post('work-orders/{workOrder}/extend-requests/{extendRequest}/reject', [ExtendRequestController::class, 'reject'])
        ->name('work-orders.extend-requests.reject');

    // FR-3.x Work Data — read-only + finalisasi analitik sekali (append-only).
    Route::get('work-data', [WorkDataController::class, 'index'])->name('work-data.index');
    Route::get('work-data/{workData}', [WorkDataController::class, 'show'])->name('work-data.show');
    Route::post('work-data/{workData}/finalize', [WorkDataController::class, 'finalize'])->name('work-data.finalize');

    // FR-2.4 Work Planning — detail jadwal + Extend Work Schedule (DGM/GM).
    Route::patch('work-planning/{workPlanning}', [WorkPlanningController::class, 'update'])
        ->name('work-planning.update');
    Route::post('work-planning/{workPlanning}/extend', [WorkPlanningController::class, 'extend'])
        ->name('work-planning.extend');
    Route::post('work-planning/{workPlanning}/approve', [WorkPlanningController::class, 'approve'])
        ->name('work-planning.approve');
    Route::post('work-planning/{workPlanning}/reject', [WorkPlanningController::class, 'reject'])
        ->name('work-planning.reject');

    // FR-5.x Daily Work - daftar harian karyawan (materialize-on-view)
    // + update status/lokasi item milik sendiri.
    Route::get('daily-work', [DailyWorkController::class, 'index'])->name('daily-work.index');
    Route::patch('daily-work/{dailyWork}', [DailyWorkController::class, 'update'])
        ->name('daily-work.update');
    Route::post('daily-work/{dailyWork}/submit', [DailyWorkController::class, 'submit'])
        ->name('daily-work.submit');
    Route::post('daily-work/{dailyWork}/verify', [DailyWorkController::class, 'verify'])
        ->name('daily-work.verify');
    Route::post('daily-work/{dailyWork}/reject', [DailyWorkController::class, 'reject'])
        ->name('daily-work.reject');
    Route::get('daily-work/{dailyWork}/attachments/{index}', [DailyWorkController::class, 'attachment'])
        ->name('daily-work.attachment');

    // FR-5.x Daily Work Task 3 - template rutin per karyawan (HOD) +
    // tugas tambahan adhoc (FR-5.2) + pratinjau harian bawahan (team).
    Route::get('daily-work/templates', [DailyWorkController::class, 'templatesIndex'])
        ->name('daily-work.templates');
    Route::post('daily-work/templates', [DailyWorkController::class, 'templateStore'])
        ->name('daily-work.templates.store');
    Route::patch('daily-work/templates/{template}', [DailyWorkController::class, 'templateUpdate'])
        ->name('daily-work.templates.update');
    Route::delete('daily-work/templates/{template}', [DailyWorkController::class, 'templateDestroy'])
        ->name('daily-work.templates.destroy');
    Route::post('daily-work/adhoc', [DailyWorkController::class, 'adhocStore'])
        ->name('daily-work.adhoc.store');
    Route::get('daily-work/team', [DailyWorkController::class, 'teamIndex'])
        ->name('daily-work.team');
    Route::get('daily-work/verification', [DailyWorkController::class, 'verificationIndex'])
        ->name('daily-work.verification');

    Route::get('divisions', [DivisionController::class, 'index'])->name('divisions.index');
    Route::get('divisions/{division}', [DivisionController::class, 'show'])->name('divisions.show');
    Route::post('divisions/sync', [DivisionController::class, 'sync'])->name('divisions.sync');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::post('employees/sync', [EmployeeController::class, 'sync'])->name('employees.sync');
    // Role assignment on the linked akun (ADR-0005): route ungated, the
    // controller authorizes via Gate::authorize('akun.update').
    Route::post('employees/{employee}/akun/roles', [EmployeeController::class, 'syncAkunRoles'])
        ->name('employees.akun.roles.sync');
    Route::get('employees/{employee}/akun/search', [EmployeeController::class, 'akunSearch'])
        ->name('employees.akun.search');

    Route::post('employees/{employee}/akun', [EmployeeController::class, 'linkAkun'])
        ->name('employees.akun.link');
    Route::delete('employees/{employee}/akun', [EmployeeController::class, 'unlinkAkun'])
        ->name('employees.akun.unlink');
    // Record-level block on a karyawan (superadmin only); survives portal syncs.
    Route::post('employees/{employee}/ban', [EmployeeController::class, 'ban'])->name('employees.ban');
    Route::post('employees/{employee}/unban', [EmployeeController::class, 'unban'])->name('employees.unban');

    Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::get('departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
    Route::post('departments/sync', [DepartmentController::class, 'sync'])->name('departments.sync');

    Route::get('positions', [PositionController::class, 'index'])->name('positions.index');
    Route::get('positions/tree', [PositionTreeController::class, 'index'])->name('positions.tree');
    Route::get('positions/tree/{position}', [PositionTreeController::class, 'show'])->name('positions.tree.root');
    Route::get('positions/{position}', [PositionController::class, 'show'])->name('positions.show');
    Route::post('positions/sync', [PositionController::class, 'sync'])->name('positions.sync');
});

require __DIR__.'/hak-akses.php';
require __DIR__.'/settings.php';
