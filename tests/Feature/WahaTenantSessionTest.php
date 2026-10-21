<?php

use App\Channels\WahaWhatsAppChannel;
use App\Jobs\SendWaMessageJob;
use App\Models\Tenant;
use App\Models\TenantWaSession;
use App\Models\User;
use App\Models\WaMessage;
use App\Services\WahaClient;
use App\TenantWaSessionStatus;
use App\WaMessageStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * Slice 2 gateway WhatsApp multi-tenant (ADR-0009 amendmen): isolasi sesi
 * antar tenant, sinkron status via polling, rate limit per sesi, dan halaman
 * Pengaturan → WhatsApp (Gate whatsapp.read/update).
 *
 * Helper memakai prefix `wt` — nama unik antar file test (semua file Pest
 * berbagi satu proses PHP).
 */
beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();

    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/** Gateway tiruan aktif + rate limit longgar (test rate limit menyetel sendiri). */
function wtConfigure(): void
{
    config([
        'waha.url' => 'http://waha.test',
        'waha.api_key' => 'test-key',
        'waha.rate_per_minute' => 1000,
        'waha.min_interval_seconds' => 0,
    ]);
}

function wtTenant(string $code = 'hq'): Tenant
{
    return Tenant::where('code', $code)->firstOrFail();
}

/** Buat/update baris sesi tanpa mengandungkannya pada konteks tenant current. */
function wtSession(
    Tenant $tenant,
    string $sessionName = 'tw-hq',
    TenantWaSessionStatus $status = TenantWaSessionStatus::Working,
    ?string $phone = '6281234567890',
): TenantWaSession {
    return TenantWaSession::withoutGlobalScope('tenant')->updateOrCreate(
        ['tenant_id' => $tenant->getKey()],
        [
            'session_name' => $sessionName,
            'phone_number' => $phone,
            'status' => $status,
        ],
    );
}

function wtUser(?Tenant $tenant = null, ?string $phone = '081234567890'): User
{
    return createUser([
        'tenant_id' => ($tenant ?? wtTenant())->getKey(),
        'phone' => $phone,
    ]);
}

/** User pemegang izin tertentu (katalog sudah di-seed di beforeEach). */
function wtUserWith(array $permissions, ?Tenant $tenant = null): User
{
    $user = wtUser($tenant);
    $user->givePermissionTo($permissions);

    return $user;
}

/** Notifikasi kontrak channel (toWhatsApp + waEvent) tanpa dependensi WO. */
function wtNotification(string $text = 'Pesan test tenant'): Notification
{
    return new class($text) extends Notification
    {
        public function __construct(private readonly string $text = 'Pesan test tenant') {}

        public function toWhatsApp(object $notifiable): string
        {
            return $this->text;
        }

        public function waEvent(): string
        {
            return 'work_order.created';
        }
    };
}

function wtMessage(Tenant $tenant, string $phone, WaMessageStatus $status = WaMessageStatus::Pending): WaMessage
{
    return WaMessage::create([
        'tenant_id' => $tenant->getKey(),
        'event' => 'work_order.created',
        'phone' => $phone,
        'payload' => 'Pesan test tenant',
        'status' => $status,
    ]);
}

function wtRunJob(WaMessage $message): void
{
    (new SendWaMessageJob($message->getKey()))->handle(app(WahaClient::class));
}

// ---------------------------------------------------------------------------
// Isolasi sesi antar tenant (inti slice 2)
// ---------------------------------------------------------------------------

it('routes each tenant outbox row through its own session number', function () {
    wtConfigure();

    $tenantA = wtTenant();
    $tenantB = Tenant::create([
        'optigate_company_id' => 2,
        'code' => 'branch',
        'name' => 'Branch Office',
        'is_active' => true,
    ]);

    wtSession($tenantA, 'tw-hq');
    wtSession($tenantB, 'tw-branch');

    $sends = [];

    Http::preventStrayRequests();
    Http::fake([
        'http://waha.test/api/sendText' => function (Request $request) use (&$sends) {
            $sends[(string) $request['chatId']] = (string) $request['session'];

            return ['status' => 'sent'];
        },
    ]);

    $messageA = wtMessage($tenantA, '081111111111');
    $messageB = wtMessage($tenantB, '082222222222');

    wtRunJob($messageA);
    wtRunJob($messageB);

    expect($sends)->toBe([
        '6281111111111@c.us' => 'tw-hq',
        '6282222222222@c.us' => 'tw-branch',
    ]);

    $messageA->refresh();
    $messageB->refresh();

    expect($messageA->status)->toBe(WaMessageStatus::Sent)
        ->and($messageA->session_name)->toBe('tw-hq')
        ->and($messageB->status)->toBe(WaMessageStatus::Sent)
        ->and($messageB->session_name)->toBe('tw-branch');
});

it('marks the row failed without http when the tenant has no session', function () {
    wtConfigure();
    Http::preventStrayRequests();

    (new WahaWhatsAppChannel)->send(wtUser(), wtNotification());

    $message = WaMessage::sole();

    expect($message->status)->toBe(WaMessageStatus::Failed)
        ->and($message->session_name)->toBeNull()
        ->and($message->last_error)->toContain('belum mendaftarkan nomor')
        ->and($message->sent_at)->toBeNull();

    Http::assertNothingSent();
});

it('fails with the session status reason when the tenant session is not active', function () {
    wtConfigure();
    Http::preventStrayRequests();

    wtSession(wtTenant(), 'tw-hq', TenantWaSessionStatus::Stopped);

    (new WahaWhatsAppChannel)->send(wtUser(), wtNotification());

    $message = WaMessage::sole();

    expect($message->status)->toBe(WaMessageStatus::Failed)
        ->and($message->last_error)->toContain('tidak aktif')
        ->and($message->last_error)->toContain('Terputus');

    Http::assertNothingSent();
});

it('fails without http when the session died between dispatch and execution', function () {
    wtConfigure();
    Http::preventStrayRequests();

    $session = wtSession(wtTenant(), 'tw-hq');
    $message = wtMessage(wtTenant(), '081234567890');

    $session->forceFill(['status' => TenantWaSessionStatus::Stopped])->save();

    wtRunJob($message);

    $message->refresh();

    expect($message->status)->toBe(WaMessageStatus::Failed)
        ->and($message->last_error)->toContain('tidak aktif saat job dieksekusi')
        ->and($message->attempts)->toBe(0);

    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Rate limit per sesi (rethrow → backoff, isolasi kunci antar nomor)
// ---------------------------------------------------------------------------

it('rate-limits one session without holding another tenant', function () {
    wtConfigure();
    config(['waha.rate_per_minute' => 1, 'waha.min_interval_seconds' => 0]);

    $tenantA = wtTenant();
    $tenantB = Tenant::create([
        'optigate_company_id' => 2,
        'code' => 'branch',
        'name' => 'Branch Office',
        'is_active' => true,
    ]);

    wtSession($tenantA, 'tw-hq');
    wtSession($tenantB, 'tw-branch');

    $sends = 0;

    Http::preventStrayRequests();
    Http::fake([
        'http://waha.test/api/sendText' => function () use (&$sends) {
            $sends++;

            return ['status' => 'sent'];
        },
    ]);

    $first = wtMessage($tenantA, '081111111111');
    $second = wtMessage($tenantA, '083333333333');
    $otherTenant = wtMessage($tenantB, '082222222222');

    wtRunJob($first);

    expect($first->refresh()->status)->toBe(WaMessageStatus::Sent)
        ->and($sends)->toBe(1);

    try {
        wtRunJob($second);
        $this->fail('Expected the session rate limit to throw.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('Rate limit sesi tw-hq');
    }

    $second->refresh();

    // Rethrow → antrean yang mengatur backoff; kuota attempts HTTP tidak
    // terpakai oleh penolakan rate limit.
    expect($second->status)->toBe(WaMessageStatus::Pending)
        ->and($second->last_error)->toContain('Rate limit')
        ->and($second->attempts)->toBe(0);

    // Sesi tenant lain punya kunci sendiri: tetap terkirim.
    wtRunJob($otherTenant);

    expect($otherTenant->refresh()->status)->toBe(WaMessageStatus::Sent)
        ->and($sends)->toBe(2);
});

it('enforces the minimum interval between sends to one session', function () {
    wtConfigure();
    config(['waha.rate_per_minute' => 1000, 'waha.min_interval_seconds' => 60]);

    wtSession(wtTenant(), 'tw-hq');

    $sends = 0;

    Http::fake([
        'http://waha.test/api/sendText' => function () use (&$sends) {
            $sends++;

            return ['status' => 'sent'];
        },
    ]);

    $first = wtMessage(wtTenant(), '081111111111');
    $second = wtMessage(wtTenant(), '083333333333');

    wtRunJob($first);

    try {
        wtRunJob($second);
        $this->fail('Expected the minimum interval to throw.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('Jeda minimal');
    }

    expect($second->refresh()->status)->toBe(WaMessageStatus::Pending)
        ->and($second->last_error)->toContain('Jeda minimal')
        ->and($sends)->toBe(1);
});

// ---------------------------------------------------------------------------
// Sinkron status (app:waha-sync-sessions — polling, tanpa webhook)
// ---------------------------------------------------------------------------

it('syncs session status and phone from the instance', function () {
    wtConfigure();

    $session = wtSession(wtTenant(), 'tw-hq', TenantWaSessionStatus::Stopped);

    Http::fake(['http://waha.test/api/sessions' => fn () => [
        ['name' => 'tw-hq', 'status' => 'WORKING', 'me' => ['tel' => '628999999999']],
    ]]);

    Carbon::setTestNow('2026-10-21 10:00:00');

    expect(Artisan::call('app:waha-sync-sessions'))->toBe(0);

    $session->refresh();

    expect($session->status)->toBe(TenantWaSessionStatus::Working)
        ->and($session->phone_number)->toBe('628999999999')
        ->and($session->last_connected_at?->toDateTimeString())->toBe('2026-10-21 10:00:00')
        ->and($session->last_synced_at?->toDateTimeString())->toBe('2026-10-21 10:00:00');

    // Poll ulang: last_connected_at TIDAK di-reset (hanya saat transisi
    // MASUK working), last_synced_at maju.
    Carbon::setTestNow('2026-10-21 10:05:00');

    Artisan::call('app:waha-sync-sessions');

    $session->refresh();

    expect($session->last_connected_at?->toDateTimeString())->toBe('2026-10-21 10:00:00')
        ->and($session->last_synced_at?->toDateTimeString())->toBe('2026-10-21 10:05:00');
});

it('marks the session missing when the instance no longer lists it', function () {
    wtConfigure();

    $session = wtSession(wtTenant(), 'tw-hq', TenantWaSessionStatus::Working);

    Http::fake(['http://waha.test/api/sessions' => fn () => Http::response([], 200)]);

    expect(Artisan::call('app:waha-sync-sessions'))->toBe(0);

    expect($session->refresh()->status)->toBe(TenantWaSessionStatus::Missing);
});

it('keeps session status when the instance is unreachable', function () {
    wtConfigure();

    $session = wtSession(wtTenant(), 'tw-hq', TenantWaSessionStatus::Working);

    Http::preventStrayRequests();

    $exitCode = Artisan::call('app:waha-sync-sessions');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('tidak ada status yang diubah')
        ->and($session->refresh()->status)->toBe(TenantWaSessionStatus::Working);
});

it('skips the sync when the gateway is not configured', function () {
    config(['waha.url' => null]);

    $session = wtSession(wtTenant(), 'tw-hq', TenantWaSessionStatus::Working);

    $exitCode = Artisan::call('app:waha-sync-sessions');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('belum dikonfigurasi')
        ->and($session->refresh()->status)->toBe(TenantWaSessionStatus::Working);
});

// ---------------------------------------------------------------------------
// Ping diagnostik (--tenant)
// ---------------------------------------------------------------------------

it('fails the ping for an unknown tenant code', function () {
    wtConfigure();
    Http::fake(['http://waha.test/api/sessions' => fn () => Http::response([], 200)]);

    $exitCode = Artisan::call('app:waha-ping', ['--tenant' => 'nope']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('tidak ditemukan');
});

it('fails the ping when the tenant has no registered session', function () {
    wtConfigure();
    Http::fake(['http://waha.test/api/sessions' => fn () => Http::response([], 200)]);

    $exitCode = Artisan::call('app:waha-ping', ['--tenant' => 'hq']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('belum mendaftarkan');
});

// ---------------------------------------------------------------------------
// Halaman Pengaturan → WhatsApp (Gate whatsapp.read / whatsapp.update)
// ---------------------------------------------------------------------------

it('redirects guests away from the whatsapp settings', function () {
    $this->get('/settings/whatsapp')->assertRedirect(route('saml.redirect'));
});

it('denies the whatsapp settings without whatsapp.read', function () {
    $this->actingAs(createUser())
        ->get('/settings/whatsapp')
        ->assertForbidden();
});

it('renders the whatsapp settings with the tenant session', function () {
    wtConfigure();
    wtSession(wtTenant(), 'tw-hq');

    $this->actingAs(wtUserWith(['whatsapp.read']))
        ->get('/settings/whatsapp')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/whatsapp')
            ->where('configured', true)
            ->where('session.session_name', 'tw-hq')
            ->where('session.status', 'working'));
});

it('renders the whatsapp settings for a super admin holding zero permissions', function () {
    $this->actingAs(makeSuperAdmin(wtUser()))
        ->get('/settings/whatsapp')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/whatsapp')
            ->where('session', null));
});

it('denies registering a number without whatsapp.update', function () {
    $this->actingAs(wtUserWith(['whatsapp.read']))
        ->post('/settings/whatsapp', ['phone_number' => '081234567890'])
        ->assertForbidden();
});

it('registers the tenant number under the fixed session name', function () {
    $this->actingAs(wtUserWith(['whatsapp.update']))
        ->post('/settings/whatsapp', ['phone_number' => '0812-3456-7890'])
        ->assertRedirect();

    $session = TenantWaSession::withoutGlobalScope('tenant')
        ->where('tenant_id', wtTenant()->getKey())
        ->sole();

    expect($session->session_name)->toBe('tw-hq')
        ->and($session->phone_number)->toBe('081234567890')
        ->and($session->status)->toBe(TenantWaSessionStatus::Stopped);
});

it('validates the phone number when registering', function () {
    $this->actingAs(wtUserWith(['whatsapp.update']))
        ->post('/settings/whatsapp', ['phone_number' => 'bukan-nomor'])
        ->assertSessionHasErrors('phone_number');

    expect(TenantWaSession::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('starts the session through the instance', function () {
    wtConfigure();

    $session = wtSession(wtTenant(), 'tw-hq', TenantWaSessionStatus::Stopped);

    Http::preventStrayRequests();
    Http::fake(['http://waha.test/api/sessions/tw-hq/start' => fn () => ['name' => 'tw-hq']]);

    $this->actingAs(wtUserWith(['whatsapp.update']))
        ->post('/settings/whatsapp/start')
        ->assertRedirect();

    // Optimistik: cron sinkronisasi membetulkan ke scan_qr/working.
    expect($session->refresh()->status)->toBe(TenantWaSessionStatus::Starting);
});

it('stops the session through the instance', function () {
    wtConfigure();

    $session = wtSession(wtTenant(), 'tw-hq', TenantWaSessionStatus::Working);

    Http::preventStrayRequests();
    Http::fake(['http://waha.test/api/sessions/tw-hq/stop' => fn () => ['name' => 'tw-hq']]);

    $this->actingAs(wtUserWith(['whatsapp.update']))
        ->post('/settings/whatsapp/stop')
        ->assertRedirect();

    expect($session->refresh()->status)->toBe(TenantWaSessionStatus::Stopped);
});

it('returns the pairing qr as a data uri', function () {
    wtConfigure();

    wtSession(wtTenant(), 'tw-hq', TenantWaSessionStatus::ScanQr);

    Http::preventStrayRequests();
    Http::fake(['http://waha.test/api/tw-hq/auth/qr*' => fn () => Http::response('PNG', 200)]);

    $this->actingAs(wtUserWith(['whatsapp.update']))
        ->get('/settings/whatsapp/qr')
        ->assertOk()
        ->assertJson(['qr' => 'data:image/png;base64,'.base64_encode('PNG')]);
});

it('denies the pairing qr without whatsapp.update', function () {
    $this->actingAs(wtUserWith(['whatsapp.read']))
        ->get('/settings/whatsapp/qr')
        ->assertForbidden();
});
