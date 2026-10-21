<?php

use App\Channels\WahaWhatsAppChannel;
use App\Jobs\SendWaMessageJob;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantWaSession;
use App\Models\User;
use App\Models\WaMessage;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderAssigned;
use App\Notifications\WorkOrderCreated;
use App\Notifications\WorkOrderReviewed;
use App\Services\DeadlineCalculator;
use App\Services\WahaClient;
use App\TenantWaSessionStatus;
use App\WaMessageStatus;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * Slice 1 outbound WhatsApp gateway (spec .scratch/wa-gateway/spec.md, ADR-0009).
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

/** Aktifkan gateway tiruan di test ini (kontrak G2: config-based). */
function wahaGatewayConfigure(): void
{
    config([
        'waha.url' => 'http://waha.test',
        'waha.api_key' => 'test-key',
        // Nonaktifkan rate limit per sesi agar multi-kirim dalam satu test
        // (mis. eskalasi H+3/5/6) tidak saling menahan. Test rate limit
        // mengeset batasnya sendiri.
        'waha.rate_per_minute' => 1000,
        'waha.min_interval_seconds' => 0,
    ]);
}

/** Penerima dengan nomor dari employees.number (portal), dan opsional phone akun. */
function wahaGatewayUser(?string $number = null, ?string $phone = null): User
{
    $user = createUser([
        'tenant_id' => Tenant::where('code', 'hq')->firstOrFail()->id,
        'phone' => $phone,
    ]);

    if ($number !== null) {
        $employee = Employee::factory()->create(['number' => $number]);
        $user->forceFill(['employee_id' => $employee->getKey()])->save();
    }

    return $user;
}

function wahaGatewayWorkOrder(array $overrides = [], ?int $hodUserId = null): WorkOrder
{
    $department = Department::create([
        'kode_department' => 'DEP-WA-'.fake()->unique()->numerify('####'),
        'nama_department' => 'IT',
        'hod_user_id' => $hodUserId,
    ]);

    $requester = createUser(['tenant_id' => Tenant::where('code', 'hq')->firstOrFail()->id]);

    return WorkOrder::create(array_merge([
        'nomor_wo' => 'WO-WA-'.fake()->unique()->numerify('####'),
        'requester_user_id' => $requester->getKey(),
        'target_department_id' => $department->getKey(),
        'category' => WorkOrderCategory::Normal,
        'title' => 'Gateway WO',
        'description' => 'Test',
        'status' => WorkOrderStatus::Assigned->value,
        'assigned_at' => now(),
        'deadline_date' => now()->addDays(6),
        'is_escalated' => false,
        'extend_count' => 0,
    ], $overrides));
}

/**
 * Sesi aktif sebuah tenant — prasyarat channel menulis `pending` + job
 * mengirim (slice 2: tanpa sesi aktif = `failed`, tanpa HTTP).
 */
function wahaGatewaySession(
    ?Tenant $tenant = null,
    string $sessionName = 'tw-hq',
    TenantWaSessionStatus $status = TenantWaSessionStatus::Working,
): TenantWaSession {
    $tenant ??= Tenant::where('code', 'hq')->firstOrFail();

    return TenantWaSession::withoutGlobalScope('tenant')->updateOrCreate(
        ['tenant_id' => $tenant->getKey()],
        [
            'session_name' => $sessionName,
            'phone_number' => '6281234567890',
            'status' => $status,
        ],
    );
}

it('does not touch WhatsApp or the outbox when the gateway is not configured', function () {
    Http::preventStrayRequests();

    $workOrder = wahaGatewayWorkOrder();
    $recipient = wahaGatewayUser(phone: '081234567890');

    $recipient->notify(new WorkOrderCreated($workOrder));

    expect(WaMessage::count())->toBe(0)
        ->and($recipient->notifications()->count())->toBe(1);

    Http::assertNothingSent();
});

it('sends a review request through the gateway and records a sent outbox row', function () {
    wahaGatewayConfigure();
    wahaGatewaySession();
    Http::preventStrayRequests();
    Http::fake(['http://waha.test/api/sendText' => fn () => ['status' => 'sent']]);

    $workOrder = wahaGatewayWorkOrder();
    $recipient = wahaGatewayUser('081234567890');

    $recipient->notify(new WorkOrderCreated($workOrder));

    $message = WaMessage::sole();

    expect($message->status)->toBe(WaMessageStatus::Sent)
        ->and($message->event)->toBe('work_order.created')
        ->and($message->phone)->toBe('081234567890')
        ->and($message->chat_id)->toBe('6281234567890@c.us')
        ->and($message->session_name)->toBe('tw-hq')
        ->and($message->recipient_user_id)->toBe($recipient->getKey())
        ->and($message->work_order_id)->toBe($workOrder->getKey())
        ->and($message->tenant_id)->toBe($workOrder->tenant_id)
        ->and($message->attempts)->toBe(1)
        ->and($message->sent_at)->not->toBeNull();

    Http::assertSent(function (Request $request) use ($workOrder) {
        return $request->url() === 'http://waha.test/api/sendText'
            && $request->hasHeader('X-Api-Key', 'test-key')
            && $request['session'] === 'tw-hq'
            && $request['chatId'] === '6281234567890@c.us'
            && str_contains((string) $request['text'], $workOrder->nomor_wo);
    });
});

it('marks the outbox row skipped when the recipient has no usable phone number', function () {
    wahaGatewayConfigure();
    Http::preventStrayRequests();
    Http::fake(['http://waha.test/api/sendText' => fn () => ['status' => 'sent']]);

    $workOrder = wahaGatewayWorkOrder();
    $recipient = wahaGatewayUser();

    $recipient->notify(new WorkOrderCreated($workOrder));

    $message = WaMessage::sole();

    expect($message->status)->toBe(WaMessageStatus::Skipped)
        ->and($message->phone)->toBeNull()
        ->and($message->chat_id)->toBeNull()
        ->and($message->last_error)->not->toBeNull()
        ->and($message->sent_at)->toBeNull();

    Http::assertNothingSent();
});

it('prefers the linked employee number over the account phone', function () {
    wahaGatewayConfigure();
    wahaGatewaySession();
    Http::fake(['http://waha.test/api/sendText' => fn () => ['status' => 'sent']]);

    $workOrder = wahaGatewayWorkOrder();
    $recipient = wahaGatewayUser('089999999999', phone: '081111111111');

    $recipient->notify(new WorkOrderCreated($workOrder));

    expect(WaMessage::sole()->chat_id)->toBe('6289999999999@c.us');
});

it('resolves the recipient number when no tenant is current', function () {
    wahaGatewayConfigure();
    wahaGatewaySession();
    Http::fake(['http://waha.test/api/sendText' => fn () => ['status' => 'sent']]);

    $workOrder = wahaGatewayWorkOrder();
    $recipient = wahaGatewayUser('087777777777');

    Tenant::forgetCurrent();

    // Channel dipanggil langsung: notify() penuh tanpa tenant current masih
    // tersandung cacat lama di channel broadcast (job tenant-aware tanpa
    // tenantId — di luar scope slice ini, dicatat untuk user).
    (new WahaWhatsAppChannel)->send($recipient, new WorkOrderCreated($workOrder));

    $message = WaMessage::sole();

    expect($message->status)->toBe(WaMessageStatus::Sent)
        ->and($message->chat_id)->toBe('6287777777777@c.us')
        ->and($message->tenant_id)->toBe($workOrder->tenant_id);
});

it('delivers the outbox job when no tenant is current', function () {
    wahaGatewayConfigure();
    wahaGatewaySession();
    Http::preventStrayRequests();
    Http::fake(['http://waha.test/api/sendText' => fn () => ['status' => 'sent']]);

    $message = WaMessage::create([
        'tenant_id' => Tenant::where('code', 'hq')->firstOrFail()->getKey(),
        'event' => 'work_order.escalated',
        'phone' => '081234567890',
        'payload' => 'test payload',
        'status' => WaMessageStatus::Pending,
    ]);

    Tenant::forgetCurrent();
    SendWaMessageJob::dispatch($message->getKey());

    $message->refresh();

    expect($message->status)->toBe(WaMessageStatus::Sent)
        ->and($message->chat_id)->toBe('6281234567890@c.us');
});

it('notifies the requester with the review decision', function () {
    wahaGatewayConfigure();
    wahaGatewaySession();
    Http::fake(['http://waha.test/api/sendText' => fn () => ['status' => 'sent']]);

    $requester = wahaGatewayUser('081234567890');
    $workOrder = wahaGatewayWorkOrder(['requester_user_id' => $requester->getKey()]);

    $requester->notify(new WorkOrderReviewed($workOrder));

    $message = WaMessage::sole();

    expect($message->event)->toBe('work_order.reviewed')
        ->and($message->status)->toBe(WaMessageStatus::Sent)
        ->and($message->payload)->toContain($workOrder->nomor_wo)
        ->and($message->payload)->toContain('Disetujui')
        ->and($message->payload)->toContain('/hq/work-orders/');
});

it('notifies each assigned worker with the deadline', function () {
    wahaGatewayConfigure();
    wahaGatewaySession();
    Http::fake(['http://waha.test/api/sendText' => fn () => ['status' => 'sent']]);

    $worker = wahaGatewayUser('081234567890');
    $workOrder = wahaGatewayWorkOrder();

    $worker->notify(new WorkOrderAssigned($workOrder));

    $message = WaMessage::sole();

    expect($message->event)->toBe('work_order.assigned')
        ->and($message->status)->toBe(WaMessageStatus::Sent)
        ->and($message->payload)->toContain($workOrder->nomor_wo)
        ->and($message->payload)->toContain('Deadline')
        ->and($message->recipient_user_id)->toBe($worker->getKey());
});

it('records the gateway error on the outbox row when a send fails', function () {
    wahaGatewayConfigure();
    wahaGatewaySession();
    Http::preventStrayRequests();
    Http::fake(['http://waha.test/api/sendText' => fn () => Http::response(['error' => 'boom'], 500)]);

    $message = WaMessage::create([
        'tenant_id' => Tenant::where('code', 'hq')->firstOrFail()->getKey(),
        'event' => 'work_order.created',
        'phone' => '081234567890',
        'payload' => 'test payload',
        'status' => WaMessageStatus::Pending,
    ]);

    try {
        (new SendWaMessageJob($message->getKey()))->handle(app(WahaClient::class));
        $this->fail('Expected the gateway send to throw.');
    } catch (RequestException) {
        // Queue mengatur retry berdasarkan exception ini.
    }

    $message->refresh();

    expect($message->status)->toBe(WaMessageStatus::Pending)
        ->and($message->attempts)->toBe(1)
        ->and($message->last_error)->not->toBeNull();
});

it('marks the outbox row failed when retries are exhausted', function () {
    $message = WaMessage::create([
        'event' => 'work_order.created',
        'phone' => '081234567890',
        'payload' => 'test payload',
        'status' => WaMessageStatus::Pending,
    ]);

    (new SendWaMessageJob($message->getKey()))->failed(new RuntimeException('gateway down'));

    $message->refresh();

    expect($message->status)->toBe(WaMessageStatus::Failed)
        ->and($message->last_error)->toBe('gateway down');
});

it('writes escalation outbox rows for the notified department head', function () {
    (new RoleAndPermissionSeeder)->run();
    wahaGatewayConfigure();
    wahaGatewaySession();
    Http::preventStrayRequests();
    Http::fake(['http://waha.test/api/sendText' => fn () => ['status' => 'sent']]);

    $hod = wahaGatewayUser('081234567890');
    makeHod($hod);

    $assignedAt = Carbon::parse('2026-10-01 09:00:00');
    $workOrder = wahaGatewayWorkOrder([
        'assigned_at' => $assignedAt,
        'deadline_date' => app(DeadlineCalculator::class)->calculate(WorkOrderCategory::Normal, $assignedAt),
    ], hodUserId: $hod->getKey());

    Artisan::call('app:check-deadline-escalation');

    $messages = WaMessage::query()->where('event', 'work_order.escalated')->get();

    expect($messages)->not->toBeEmpty()
        ->and($messages->pluck('payload')->implode("\n"))->toContain('H+3');

    $messages->each(function (WaMessage $message) use ($hod, $workOrder) {
        expect($message->status)->toBe(WaMessageStatus::Sent)
            ->and($message->recipient_user_id)->toBe($hod->getKey())
            ->and($message->chat_id)->toBe('6281234567890@c.us')
            ->and($message->work_order_id)->toBe($workOrder->getKey());
    });
});

it('fails the ping command when the gateway is not configured', function () {
    $exitCode = Artisan::call('app:waha-ping');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('belum dikonfigurasi');
});

it('reports the instance and sends a test message via the tenant session', function () {
    wahaGatewayConfigure();
    wahaGatewaySession();
    Http::preventStrayRequests();
    Http::fake([
        'http://waha.test/api/sessions' => fn () => [
            ['name' => 'tw-hq', 'status' => 'WORKING', 'me' => ['tel' => '6281234567890']],
        ],
        'http://waha.test/api/sessions/tw-hq' => fn () => ['name' => 'tw-hq', 'status' => 'WORKING'],
        'http://waha.test/api/sendText' => fn () => ['status' => 'sent'],
    ]);

    $exitCode = Artisan::call('app:waha-ping', ['--tenant' => 'hq', 'phone' => '081234567890']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('WORKING');

    Http::assertSent(fn (Request $request) => $request->url() === 'http://waha.test/api/sendText'
        && $request['session'] === 'tw-hq'
        && $request['chatId'] === '6281234567890@c.us');
});

it('checks only the instance when no tenant option is given (no send path)', function () {
    wahaGatewayConfigure();
    Http::preventStrayRequests();
    Http::fake(['http://waha.test/api/sessions' => fn () => Http::response([], 200)]);

    $exitCode = Artisan::call('app:waha-ping');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Instance OK');

    // Tanpa --tenant: hanya satu panggilan daftar sesi, nol pesan keluar.
    Http::assertSentCount(1);
});

it('reports an unreachable gateway from the ping command', function () {
    wahaGatewayConfigure();
    Http::preventStrayRequests();

    $exitCode = Artisan::call('app:waha-ping');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('tidak terjangkau');
});
