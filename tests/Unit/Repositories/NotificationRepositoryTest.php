<?php

declare(strict_types=1);

use App\Actions\Notifications\MarkNotificationsAsRead;
use App\Models\User;
use App\Repositories\NotificationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('lists, finds, and marks notifications', function () {
    $user = User::factory()->create();

    $old = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => [],
        'created_at' => now()->subDay(),
    ]);
    $new = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => [],
        'created_at' => now(),
    ]);

    $notifications = app(NotificationRepository::class);

    $page = $notifications->get($user, ascending: false);

    expect($page->total())->toBe(2)
        ->and($page->first()?->is($new))->toBeTrue()
        ->and($notifications->getIdsOnPage($user, false, 1))->toBe([$new->getKey(), $old->getKey()])
        ->and($notifications->find($user, $new->getKey())?->is($new))->toBeTrue();

    expect(app(MarkNotificationsAsRead::class)->handle($user, [$new->getKey()], true))->toBe(1)
        ->and($new->fresh()->read_at)->not->toBeNull();
});

it('keeps an existing read timestamp when opening', function () {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => [],
        'read_at' => now()->subHour(),
    ]);
    $before = $notification->read_at?->toISOString();

    app(MarkNotificationsAsRead::class)->markOne($user, $notification->getKey());

    expect($notification->fresh()->read_at?->toISOString())->toBe($before);
});
