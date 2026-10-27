<?php

use App\Models\DailyWork;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Task 2 — submit bukti kerja (plan .scratch/daily-work-verification/plan.md):
 * karyawan pemilik menyerahkan catatan + 1–3 foto; status naik ke
 * pending_verify; jalur PATCH `done` ditutup (test kontrak
 * di DailyWorkUpdateTest). Mirror pola WorkOrderSubmitTest (Storage::fake s3).
 */
beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    createTestTenant([
        'optigate_company_id' => 1,
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    initTenant('hq');

    Storage::fake('s3');
});

afterEach(function () {
    Tenant::forgetCurrent();
});

/**
 * Pemilik item + item pending dengan tanggal masa depan (bebas geser kalender).
 *
 * @return array{0: DailyWork, 1: User, 2: Employee}
 */
function makeDailyWorkOwnerWithPermissions(): array
{
    $tenantId = Tenant::where('code', 'hq')->first()->getKey();
    $employee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $user = createUser(['tenant_id' => $tenantId, 'employee_id' => $employee->getKey()]);
    $user->givePermissionTo('daily-work.read');
    $user->givePermissionTo('daily-work.update');

    $dailyWork = DailyWork::factory()->create([
        'employee_id' => $employee->getKey(),
        'work_date' => '2100-01-01',
    ]);

    return [$dailyWork, $user, $employee];
}

it('submits proof from the owner and raises the item to pending verification', function () {
    [$dailyWork, $user] = makeDailyWorkOwnerWithPermissions();

    $response = $this->actingAs($user)
        ->from('/hq/daily-work')
        ->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
            'proof_notes' => 'Cek unit AC sudah, suhu normal',
            'proof_attachments' => [
                UploadedFile::fake()->image('bukti-1.jpg'),
                UploadedFile::fake()->image('bukti-2.png'),
            ],
        ]);

    $response->assertRedirect('/hq/daily-work?date=2100-01-01');

    $dailyWork->refresh();

    expect($dailyWork->status)->toBe('pending_verify')
        ->and($dailyWork->proof_notes)->toBe('Cek unit AC sudah, suhu normal')
        ->and($dailyWork->proof_attachments)->toHaveCount(2)
        ->and($dailyWork->submitted_at)->not->toBeNull()
        ->and($dailyWork->done_at)->not->toBeNull()
        ->and($dailyWork->rejection_note)->toBeNull();

    foreach ($dailyWork->proof_attachments as $path) {
        expect(Storage::disk('s3')->exists($path))->toBeTrue("bukti {$path} tidak tersimpan");
    }
});

it('forbids submitting proof for another employee item with 403', function () {
    [$dailyWork] = makeDailyWorkOwnerWithPermissions();

    $tenantId = Tenant::where('code', 'hq')->first()->getKey();
    $otherEmployee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $otherUser = createUser(['tenant_id' => $tenantId, 'employee_id' => $otherEmployee->getKey()]);
    $otherUser->givePermissionTo('daily-work.update');

    $this->actingAs($otherUser)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
            'proof_notes' => 'Catatan curi',
            'proof_attachments' => [UploadedFile::fake()->image('bukti.jpg')],
        ])
        ->assertForbidden();
});

it('requires at least one and at most three proof photos', function () {
    [$dailyWork, $user] = makeDailyWorkOwnerWithPermissions();

    $this->actingAs($user)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
            'proof_notes' => 'Tanpa foto',
        ])
        ->assertSessionHasErrors(['proof_attachments']);

    $response = $this->actingAs($user)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
            'proof_notes' => 'Empat foto',
            'proof_attachments' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
                UploadedFile::fake()->image('d.jpg'),
            ],
        ]);

    $response->assertSessionHasErrors(['proof_attachments']);
});

it('rejects disallowed mime and oversized proof photos', function () {
    [$dailyWork, $user] = makeDailyWorkOwnerWithPermissions();

    $this->actingAs($user)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
            'proof_notes' => 'Bukti gif',
            'proof_attachments' => [UploadedFile::fake()->image('bukti.gif')],
        ])
        ->assertSessionHasErrors(['proof_attachments.0']);

    $this->actingAs($user)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
            'proof_notes' => 'Bukti kebesaran',
            // Kedua argumen fake image = lebar piksel, bukan KB — gunakan
            // create() agar ukuran file 3000 KB dengan mime jpeg.
            'proof_attachments' => [UploadedFile::fake()->create('bukti.jpg', 3000, 'image/jpeg')],
        ])
        ->assertSessionHasErrors(['proof_attachments.0']);
});

it('requires proof notes', function () {
    [$dailyWork, $user] = makeDailyWorkOwnerWithPermissions();

    $this->actingAs($user)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
            'proof_attachments' => [UploadedFile::fake()->image('bukti.jpg')],
        ])
        ->assertSessionHasErrors(['proof_notes']);
});

it('forbids a second submit while awaiting verification or after done', function () {
    [$dailyWork, $user] = makeDailyWorkOwnerWithPermissions();

    $this->actingAs($user)->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
        'proof_notes' => 'Pertama',
        'proof_attachments' => [UploadedFile::fake()->image('bukti.jpg')],
    ]);

    $dailyWork->refresh();
    expect($dailyWork->status)->toBe('pending_verify');

    $this->actingAs($user)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
            'proof_notes' => 'Dobel',
            'proof_attachments' => [UploadedFile::fake()->image('bukti2.jpg')],
        ])
        ->assertStatus(409);

    // done (verifikasi lolos) juga terminal dari sisi karyawan.
    $dailyWork->update(['status' => 'done']);
    $this->actingAs($user)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
            'proof_notes' => 'Sudah selesai',
            'proof_attachments' => [UploadedFile::fake()->image('bukti3.jpg')],
        ])
        ->assertStatus(409);
});

it('allows resubmitting after being returned and clears the rejection', function () {
    [$dailyWork, $user] = makeDailyWorkOwnerWithPermissions();

    $rejection = createUser(['tenant_id' => $dailyWork->tenant_id]);
    $dailyWork->forceFill([
        'status' => 'in_progress',
        'proof_notes' => 'Bukti lama',
        'proof_attachments' => ['daily-work/lama.jpg'],
        'submitted_at' => now(),
        'done_at' => now(),
        'rejected_by' => $rejection->getKey(),
        'rejected_at' => now(),
        'rejection_note' => 'Foto kurang jelas, ulangi',
    ])->save();

    $dailyWork->refresh();
    expect($dailyWork->status)->toBe('in_progress')
        ->and($dailyWork->rejection_note)->not->toBeNull();

    $this->actingAs($user)->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
        'proof_notes' => 'Bukti diganti',
        'proof_attachments' => [UploadedFile::fake()->image('bukti-baru.jpg')],
    ]);

    $dailyWork->refresh();

    expect($dailyWork->status)->toBe('pending_verify')
        ->and($dailyWork->proof_notes)->toBe('Bukti diganti')
        ->and($dailyWork->rejected_by)->toBeNull()
        ->and($dailyWork->rejected_at)->toBeNull()
        ->and($dailyWork->rejection_note)->toBeNull();
});

it('forbids submitting without the update permission with 403', function () {
    [$dailyWork] = makeDailyWorkOwnerWithPermissions();

    $tenantId = Tenant::where('code', 'hq')->first()->getKey();
    $guestEmployee = Employee::factory()->create(['tenant_id' => $tenantId]);
    $guest = createUser(['tenant_id' => $tenantId, 'employee_id' => $guestEmployee->getKey()]);

    $this->actingAs($guest)
        ->post("/hq/daily-work/{$dailyWork->getKey()}/submit", [
            'proof_notes' => 'Catatan',
            'proof_attachments' => [UploadedFile::fake()->image('bukti.jpg')],
        ])
        ->assertForbidden();
});

it('blocks submitting an item of another tenant with 403', function () {
    [$dailyWork, $user] = makeDailyWorkOwnerWithPermissions();

    createTestTenant([
        'optigate_company_id' => 2,
        'code' => 'cabang',
        'name' => 'Cabang Tes',
    ]);
    initTenant('cabang');

    $this->actingAs($user)
        ->post("/cabang/daily-work/{$dailyWork->getKey()}/submit", [
            'proof_notes' => 'Catatan',
            'proof_attachments' => [UploadedFile::fake()->image('bukti.jpg')],
        ])
        // tenant.access memblok URL lintas tenant SEBELUM lookup item
        // (pola WorkOrderSubmitTest "cannot submit a work order from
        // another tenant") → 403, bukan 404.
        ->assertForbidden();
});
