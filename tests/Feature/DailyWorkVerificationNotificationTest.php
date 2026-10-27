<?php

use App\Jobs\SendWaMessageJob;
use App\Models\DailyWork;
use App\Models\DailyWorkTemplate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantWaSession;
use App\Models\User;
use App\Models\WaMessage;
use App\Notifications\DailyWorkRejected;
use App\Notifications\DailyWorkSubmitted;
use App\Notifications\DailyWorkVerified;
use App\Services\DailyWorkService;
use App\TenantWaSessionStatus;
use App\WaMessageStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Task 4 — notifikasi verifikasi Daily Work (plan
 * .scratch/daily-work-verification/plan.md): submit → HOD/TL scope,
 * verified/rejected → akun karyawan; in-app (database) + WA outbox
 * (pola ADR-0009 via WahaWhatsAppChannel).
 */
beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    initTenant('hq');

    // Submit bukti menyentuh disk s3 — fake agar tidak menulis ke cloud.
    Storage::fake('s3');
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/**
 * Department + HOD + bawahan pemilik akun + item submitted. Tanpa TL —
 * pengirim WA/hanya HOD agar outbox test deterministik.
 *
 * @return array{0: DailyWork, 1: User, 2: Department, 3: User}
 */
function notifVerificationScenario(): array
{
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();

    $department = Department::factory()->create(['tenant_id' => $tenantId]);

    $hodEmployee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $hod = createUser(['tenant_id' => $tenantId, 'employee_id' => $hodEmployee->getKey(), 'phone' => '081234567890']);
    $hod->assignRole('hod');
    $department->update(['hod_user_id' => $hod->getKey()]);

    $subordinate = Employee::factory()->create([
        'tenant_id' => $tenantId,
        'department_id' => $department->getKey(),
    ]);
    $owner = createUser(['tenant_id' => $tenantId, 'employee_id' => $subordinate->getKey(), 'phone' => '089876543210']);
    $owner->givePermissionTo('daily-work.read');
    $owner->givePermissionTo('daily-work.update');

    $dailyWork = DailyWork::factory()->submitted()->create([
        'employee_id' => $subordinate->getKey(),
        'work_date' => '2100-01-01',
    ]);

    return [$dailyWork, $hod, $department, $owner];
}

/**
 * Sesi WA aktif untuk tenant hq — prasyarat outbox row `pending`.
 */
function notifWaSession(): TenantWaSession
{
    return TenantWaSession::withoutGlobalScope('tenant')->updateOrCreate(
        ['tenant_id' => Tenant::where('code', 'hq')->firstOrFail()->getKey()],
        [
            'session_name' => 'tw-hq',
            'phone_number' => '6281234567890',
            'status' => TenantWaSessionStatus::Working,
        ],
    );
}

it('notifies the HOD and the same department team leader on submit', function () {
    [$first, $hod, $department, $owner] = notifVerificationScenario();

    $teamLeaderEmployee = Employee::factory()->create([
        'tenant_id' => $first->tenant_id,
        'department_id' => $department->getKey(),
    ]);
    $teamLeader = createUser(['tenant_id' => $first->tenant_id, 'employee_id' => $teamLeaderEmployee->getKey()]);
    $teamLeader->assignRole('team_leader');

    // Item pending segar (bukan state submitted) agar bisa diserahkan.
    $dailyWork = DailyWork::factory()->create([
        'employee_id' => $first->employee_id,
        'work_date' => '2100-01-01',
    ]);

    // Bukti diserahkan PEMILIK item (karyawan), bukan HOD.
    $this->actingAs($owner)->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
        'proof_notes' => 'Unit dicek, aman',
        'proof_attachments' => [UploadedFile::fake()->image('bukti.jpg')],
    ])->assertRedirect();

    expect($hod->notifications()->where('type', DailyWorkSubmitted::class)->count())->toBe(1)
        ->and($teamLeader->notifications()->where('type', DailyWorkSubmitted::class)->count())->toBe(1);

    $payload = $hod->notifications()->where('type', DailyWorkSubmitted::class)->sole()->data;

    expect($payload['type'])->toBe('daily_work.submitted')
        ->and($payload['daily_work_id'])->toBe($dailyWork->getKey())
        ->and($payload['work_date'])->toBe('2100-01-01')
        ->and($payload['title'])->toBe($dailyWork->title)
        ->and($payload['message'])->toContain('menunggu verifikasi');
});

it('does not notify a HOD of another department on submit', function () {
    [$first, , , $owner] = notifVerificationScenario();

    $otherDepartment = Department::factory()->create([
        'tenant_id' => Tenant::where('code', 'hq')->first()->getKey(),
    ]);
    $foreignHodEmployee = Employee::factory()->create(['tenant_id' => $first->tenant_id]);
    $foreignHod = createUser(['tenant_id' => $first->tenant_id, 'employee_id' => $foreignHodEmployee->getKey()]);
    $foreignHod->assignRole('hod');
    $otherDepartment->update(['hod_user_id' => $foreignHod->getKey()]);

    $dailyWork = DailyWork::factory()->create([
        'employee_id' => $first->employee_id,
        'work_date' => '2100-01-01',
    ]);

    $this->actingAs($owner)->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
        'proof_notes' => 'Unit dicek, aman',
        'proof_attachments' => [UploadedFile::fake()->image('bukti.jpg')],
    ])->assertRedirect();

    expect($foreignHod->notifications()->count())->toBe(0);
});

it('notifies the employee when verified or rejected', function () {
    [$first, $hod, , $owner] = notifVerificationScenario();

    // Item kedua untuk KARYAWAN YANG SAMA (department sama → HOD sama).
    $second = DailyWork::factory()->submitted()->create([
        'employee_id' => $first->employee_id,
        'work_date' => '2100-01-01',
    ]);

    $this->actingAs($hod)->post("/hq/daily-work/{$first->getKey()}/verify")->assertRedirect();

    $this->actingAs($hod)
        ->post("/hq/daily-work/{$second->getKey()}/reject", [
            'rejection_note' => 'Foto gelap, ambil ulang',
        ])
        ->assertRedirect();

    $verified = $owner->notifications()->where('type', DailyWorkVerified::class)->sole();
    $rejected = $owner->notifications()->where('type', DailyWorkRejected::class)->sole();

    expect($verified->data['type'])->toBe('daily_work.verified')
        ->and($verified->data['daily_work_id'])->toBe($first->getKey())
        ->and($verified->data['verified_by_name'])->toBe($hod->name)
        ->and($verified->data['message'])->toContain('telah selesai');

    expect($rejected->data['type'])->toBe('daily_work.rejected')
        ->and($rejected->data['daily_work_id'])->toBe($second->getKey())
        ->and($rejected->data['message'])->toContain('Foto gelap, ambil ulang');
});

it('writes three pending WhatsApp outbox rows when the gateway is configured', function () {
    [$first, $hod, , $owner] = notifVerificationScenario();

    // Item kedua + ketiga untuk karyawan yang sama (HOD yang sama).
    $second = DailyWork::factory()->submitted()->create([
        'employee_id' => $first->employee_id,
        'work_date' => '2100-01-01',
    ]);
    $third = DailyWork::factory()->create([
        'employee_id' => $first->employee_id,
        'work_date' => '2100-01-01',
    ]);

    config([
        'waha.url' => 'http://waha.test',
        'waha.api_key' => 'test-key',
        'waha.rate_per_minute' => 1000,
        'waha.min_interval_seconds' => 0,
    ]);
    notifWaSession();

    // Job dikirim after-commit — dengan queue fake baris outbox tetap
    // pending (tidak ada HTTP keluar sama sekali).
    Queue::fake();
    Http::preventStrayRequests();

    $this->actingAs($hod)->post("/hq/daily-work/{$first->getKey()}/verify")->assertRedirect();
    $this->actingAs($hod)
        ->post("/hq/daily-work/{$second->getKey()}/reject", ['rejection_note' => 'Foto kurang jelas'])
        ->assertRedirect();
    $this->actingAs($owner)->post("/hq/daily-work/{$third->getKey()}/submit", [
        'proof_notes' => 'Unit dicek ulang',
        'proof_attachments' => [UploadedFile::fake()->image('bukti.jpg')],
    ])->assertRedirect();

    expect(WaMessage::count())->toBe(3)
        ->and(WaMessage::query()->where('status', WaMessageStatus::Pending)->count())->toBe(3)
        ->and(WaMessage::query()->pluck('event')->sort()->values()->all())->toBe([
            'daily_work.rejected', 'daily_work.submitted', 'daily_work.verified',
        ])
        ->and(WaMessage::query()->where('event', 'daily_work.submitted')->sole()->recipient_user_id)
        ->toBe($hod->getKey())
        ->and(WaMessage::query()->where('event', 'daily_work.verified')->sole()->recipient_user_id)
        ->toBe($owner->getKey())
        ->and(WaMessage::query()->whereNotNull('chat_id')->count())->toBe(3);

    Queue::assertPushed(SendWaMessageJob::class, 3);
    Http::assertNothingSent();
});

it('writes no WhatsApp rows when the gateway is not configured', function () {
    [$first, $hod, , $owner] = notifVerificationScenario();

    $second = DailyWork::factory()->submitted()->create([
        'employee_id' => $first->employee_id,
        'work_date' => '2100-01-01',
    ]);

    Http::preventStrayRequests();

    $this->actingAs($hod)->post("/hq/daily-work/{$first->getKey()}/verify")->assertRedirect();
    $this->actingAs($hod)
        ->post("/hq/daily-work/{$second->getKey()}/reject", ['rejection_note' => 'Kurang detail'])
        ->assertRedirect();

    expect(WaMessage::count())->toBe(0)
        ->and($owner->notifications()->count())->toBe(2);

    Http::assertNothingSent();
});

it('never notifies on template materialization', function () {
    [, , , $owner] = notifVerificationScenario();

    $service = app(DailyWorkService::class);
    $employee = $owner->employee;

    // 2100-01-01 = Jumat (weekday 5).
    DailyWorkTemplate::factory()->create([
        'employee_id' => $employee->getKey(),
        'title' => 'Patroli pagi',
        'weekdays' => [5],
    ]);

    $service->materializeFor($employee, CarbonImmutable::parse('2100-01-01'));

    expect($owner->notifications()->count())->toBe(0)
        ->and(WaMessage::count())->toBe(0);
});
