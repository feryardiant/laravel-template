<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Events\Audit\ContactCreated;
use App\Listeners\RecordAuditLog;
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
