<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Events\Audit\ContactCreated;
use App\Listeners\RecordAuditLog;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('audit events are queued for the audit listener', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Owner);

    ContactCreated::dispatch($organization, $actor, $organization, [
        'type' => 'customer',
    ]);

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
