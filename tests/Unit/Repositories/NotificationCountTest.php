<?php

declare(strict_types=1);

use App\Models\User;
use App\Repositories\NotificationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('counts unread notifications inside the range', function () {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => [],
        'read_at' => null,
    ]);

    $range = [
        'start' => today()->toDateString(),
        'end' => today()->toDateString(),
    ];

    $notifications = app(NotificationRepository::class);

    expect($notifications->countUnread($user, $range, 'UTC'))->toBe(1)
        ->and($notifications->countUnread($user))->toBe(1);
});
