<?php

declare(strict_types=1);

namespace App\Events;

use App\Actions\AuditLogs\AuditPayload;
use App\Enums\AuditEvent;
use App\Models\Organization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Queue-safe base event for an audited action.
 *
 * Constructor arguments may be models for call-site convenience, but only
 * scalar identifiers and the typed payload are retained so the queued
 * listener can serialize safely even when a subject is deleted before the
 * worker runs.
 */
abstract class AuditableEvent implements Auditable
{
    use Dispatchable;

    public ?int $organizationId;

    public ?int $actorId;

    public ?string $subjectType;

    public ?int $subjectId;

    public AuditPayload $payload;

    public ?int $relationshipPeriodId;

    public CarbonImmutable $occurredAt;

    public function __construct(
        ?Organization $organization,
        ?User $actor,
        ?Model $subject,
        AuditPayload $payload,
        ?OrganizationRelationshipPeriod $period = null,
    ) {
        $this->organizationId = $organization?->getKey();
        $this->actorId = $actor?->getKey();
        $this->subjectType = $subject?->getMorphClass();
        $this->subjectId = $subject?->getKey();
        $this->payload = $payload;
        $this->relationshipPeriodId = $period?->getKey();
        $this->occurredAt = CarbonImmutable::now();
    }

    /**
     * The audit event recorded for this change.
     */
    abstract public function auditEvent(): AuditEvent;
}
