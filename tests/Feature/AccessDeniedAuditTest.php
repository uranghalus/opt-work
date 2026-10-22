<?php

use App\Models\AuditEvent;
use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Schema;

/**
 * FR-6.3: setiap akses HTTP yang ditolak (403) tercatat di audit_events —
 * render hook di bootstrap/app.php, bukan event gate (probe can() tidak
 * boleh membanjiri audit).
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

it('records an access.denied audit row when the gate blocks a request', function () {
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    $this->actingAs($user)
        ->get(route('employees.index', ['tenant' => 'hq']))
        ->assertForbidden();

    $event = app(AuditEvent::class)
        ->newQuery()
        ->where('action', 'access.denied')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($user->getKey())
        ->and($event->actor_email)->toBe($user->email)
        ->and($event->subject_type)->toBe('access')
        ->and($event->subject_id)->toBe('employees.index')
        ->and($event->ip_address)->not->toBeNull()
        ->and($event->context['method'])->toBe('GET')
        ->and($event->context['route'])->toBe('employees.index')
        ->and($event->context['exception'])->toBe('AccessDeniedHttpException')
        ->and($event->context['denial'])->toBe('AuthorizationException');
});

it('does not audit requests that the gate allows', function () {
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);
    $user->givePermissionTo('employee.read');

    $this->actingAs($user)
        ->get(route('employees.index', ['tenant' => 'hq']))
        ->assertOk();

    $this->assertDatabaseMissing('audit_events', ['action' => 'access.denied']);
});

it('keeps serving the 403 response even when audit writing fails', function () {
    $user = createUser(['tenant_id' => Tenant::where('code', 'hq')->first()->id]);

    // Simulasikan kegagalan tulis audit (tabel hilang / disk penuh) —
    // response 403 tetap harus dikirim, baris audit boleh hilang.
    Schema::dropIfExists('audit_events');

    $this->actingAs($user)
        ->get(route('employees.index', ['tenant' => 'hq']))
        ->assertForbidden();
});
