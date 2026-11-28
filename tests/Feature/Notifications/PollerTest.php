<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function pollerNotification(User $user, Carbon $createdAt, bool $read = false): string
{
    $id = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'test',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'data' => json_encode(['title' => 'Poller title', 'description' => 'Poller description']),
        'read_at' => $read ? $createdAt : null,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    return $id;
}

it('does not toast notifications that existed on the first poll', function () {
    $user = User::factory()->create();
    actingAs($user);

    pollerNotification($user, Carbon::parse('2026-01-01 10:00:00'));
    pollerNotification($user, Carbon::parse('2026-01-01 10:00:01'));

    getJson(route('notifications.latest'))
        ->assertOk()
        ->assertJsonPath('notification', null)
        ->assertJsonPath('pending', [])
        ->assertJsonPath('count', 2);

    getJson(route('notifications.latest'))
        ->assertOk()
        ->assertJsonPath('notification', null)
        ->assertJsonPath('pending', []);
});

it('keeps only the notifications at the watermark second in the session', function () {
    $user = User::factory()->create();
    actingAs($user);

    foreach (range(1, 30) as $second) {
        pollerNotification($user, Carbon::parse('2026-01-01 10:00:00')->addSeconds($second));
    }

    getJson(route('notifications.latest'))->assertOk();

    $seenIds = session("notifications.seen_ids.{$user->id}");

    expect($seenIds)->toHaveCount(1);
});

it('toasts a notification created after the first poll', function () {
    $user = User::factory()->create();
    actingAs($user);

    pollerNotification($user, Carbon::parse('2026-01-01 10:00:00'));

    getJson(route('notifications.latest'))->assertOk();

    $id = pollerNotification($user, Carbon::parse('2026-01-01 10:00:05'));

    getJson(route('notifications.latest'))
        ->assertOk()
        ->assertJsonPath('notification.id', $id)
        ->assertJsonPath('pending', [$id])
        ->assertJsonPath('text', 'Poller description')
        ->assertJsonPath('count', 2);
});

it('does not repeat a notification after it is acked', function () {
    $user = User::factory()->create();
    actingAs($user);

    pollerNotification($user, Carbon::parse('2026-01-01 10:00:00'));

    getJson(route('notifications.latest'))->assertOk();

    $id = pollerNotification($user, Carbon::parse('2026-01-01 10:00:05'));

    getJson(route('notifications.latest'))->assertJsonPath('notification.id', $id);

    postJson(route('notifications.latest.ack'), ['ids' => [$id]])->assertOk();

    getJson(route('notifications.latest'))
        ->assertOk()
        ->assertJsonPath('notification', null)
        ->assertJsonPath('pending', []);
});

it('still toasts a new notification created in the acked second', function () {
    $user = User::factory()->create();
    actingAs($user);

    pollerNotification($user, Carbon::parse('2026-01-01 10:00:00'));

    getJson(route('notifications.latest'))->assertOk();

    $first = pollerNotification($user, Carbon::parse('2026-01-01 10:00:05'));

    getJson(route('notifications.latest'))->assertJsonPath('notification.id', $first);

    postJson(route('notifications.latest.ack'), ['ids' => [$first]])->assertOk();

    $second = pollerNotification($user, Carbon::parse('2026-01-01 10:00:05'));

    getJson(route('notifications.latest'))
        ->assertOk()
        ->assertJsonPath('notification.id', $second)
        ->assertJsonPath('pending', [$second]);
});

it('bounds the pending list and reports the full unseen count', function () {
    $user = User::factory()->create();
    actingAs($user);

    pollerNotification($user, Carbon::parse('2026-01-01 10:00:00'));

    getJson(route('notifications.latest'))->assertOk();

    foreach (range(1, 25) as $second) {
        pollerNotification($user, Carbon::parse('2026-01-01 10:00:00')->addSeconds($second));
    }

    $response = getJson(route('notifications.latest'))->assertOk();

    expect($response->json('pending'))->toHaveCount(20)
        ->and($response->json('text'))->toBe('Anda memiliki 25 notifikasi baru.');
});
