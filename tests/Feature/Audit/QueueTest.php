<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Events\Contacts\ContactCreated;
use App\Listeners\RecordAuditLog;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('audit events are queued for the audit listener', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Owner);

    event(new ContactCreated($organization, $actor, $organization, [
        'type' => 'customer',
    ]));

    Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === RecordAuditLog::class);
});

test('late audit events are skipped after the organization is deleted', function () {
    $organization = Organization::factory()->create();

    $event = new ContactCreated($organization, null, $organization, [
        'type' => 'customer',
    ]);

    $organization->forceDelete();

    app(RecordAuditLog::class)->handle($event);

    expect(AuditLog::query()->where('organization_id', $organization->getKey())->count())->toBe(0);
});

test('audit rows drop an actor that was deleted before the listener runs', function () {
    $organization = Organization::factory()->create();
    $actor = User::factory()->withProfile()->create();

    $event = new ContactCreated($organization, $actor, $organization, [
        'type' => 'customer',
    ]);

    $actor->delete();

    app(RecordAuditLog::class)->handle($event);

    $log = AuditLog::query()->where('organization_id', $organization->getKey())->sole();

    expect($log->actor_id)->toBeNull();
});

test('audit rows keep the event occurrence time instead of the queue time', function () {
    /** @var TestCase $this */
    $organization = Organization::factory()->create();

    $this->travelTo('2026-11-15 23:59:00');

    $event = new ContactCreated($organization, null, $organization, [
        'type' => 'customer',
    ]);

    $this->travelTo('2026-11-16 00:05:00');

    app(RecordAuditLog::class)->handle($event);

    expect(AuditLog::query()
        ->where('organization_id', $organization->getKey())
        ->sole()
        ->created_at
        ->toDateTimeString())->toBe('2026-11-15 23:59:00');
});
