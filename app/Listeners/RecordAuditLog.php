<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AuditableEvent;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class RecordAuditLog implements ShouldQueueAfterCommit
{
    public int $tries = 3;

    public int $backoff = 10;

    public function handle(AuditableEvent $event): void
    {
        if ($event->organizationId !== null) {
            $organization = Organization::withTrashed()->find($event->organizationId);

            if ($organization === null || $organization->trashed()) {
                return;
            }
        }

        AuditLog::create([
            'organization_id' => $event->organizationId,
            'actor_id' => $event->actorId !== null && User::query()->whereKey($event->actorId)->exists()
                ? $event->actorId
                : null,
            'event' => $event->auditEvent(),
            'subject_type' => $event->subjectType,
            'subject_id' => $event->subjectId,
            'organization_relationship_period_id' => $event->relationshipPeriodId,
            'changes' => $event->changes === [] ? null : $event->changes,
            'created_at' => $event->occurredAt,
        ]);
    }
}
