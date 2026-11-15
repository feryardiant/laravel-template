<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class RecordAudit
{
    /**
     * Append one audit entry.
     *
     * Call this inside the transaction that performs the change so the entry
     * and the change commit or roll back together.
     *
     * @param  array<string, mixed>  $changes  Scalars only; never PII values.
     */
    public function handle(
        ?Organization $organization,
        ?User $actor,
        AuditEvent $event,
        ?Model $subject = null,
        array $changes = [],
        ?OrganizationRelationshipPeriod $period = null,
    ): AuditLog {
        return AuditLog::create([
            'organization_id' => $organization?->getKey(),
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'organization_relationship_period_id' => $period?->getKey(),
            'changes' => $changes === [] ? null : $changes,
        ]);
    }
}
