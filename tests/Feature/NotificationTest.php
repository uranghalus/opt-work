<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    Tenant::query()->firstOrCreate(['id' => 'hq']);
});

afterEach(function () {
    Tenancy::end();
});

function createNotificationFor(User $user): string
{
    $id = Str::uuid()->toString();

    $user->notifications()->create([
        'id' => $id,
        'type' => 'work_order.created',
        'data' => [
            'type' => 'work_order.created',
            'message' => 'WO baru menunggu review Anda.',
        ],
    ]);

    return $id;
}

it('prevents marking another user notification as read', function () {
    $owner = createUser();
    $other = createUser();
    $notificationId = createNotificationFor($owner);

    $this
        ->actingAs($other)
        ->post('/notifications/'.$notificationId.'/read')
        ->assertNotFound();

    expect($owner->notifications()->whereKey($notificationId)->first()->read_at)->toBeNull();

    $this
        ->actingAs($owner)
        ->post('/notifications/'.$notificationId.'/read')
        ->assertRedirect();

    expect($owner->notifications()->whereKey($notificationId)->first()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    $user = createUser();
    createNotificationFor($user);
    createNotificationFor($user);

    expect($user->unreadNotifications()->count())->toBe(2);

    $this
        ->actingAs($user)
        ->post('/notifications/read-all')
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('shares the unread notifications count via inertia', function () {
    Notification::fake();
    $user = createUser();
    createNotificationFor($user);

    $this
        ->actingAs($user)
        ->get('/notifications')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('unreadNotificationsCount', 1)
                ->etc(),
        );
});

it('lists notifications newest first', function () {
    $user = createUser();
    $first = createNotificationFor($user);
    $second = createNotificationFor($user);

    DB::table('notifications')->where('id', $first)->update([
        'created_at' => now()->subSeconds(2),
    ]);
    DB::table('notifications')->where('id', $second)->update([
        'created_at' => now()->subSecond(),
    ]);

    $this
        ->actingAs($user)
        ->get('/notifications')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('notifications.data.0.id', $second)
                ->etc(),
        );
});
