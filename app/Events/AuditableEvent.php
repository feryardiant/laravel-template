<?php

declare(strict_types=1);

namespace App\Events;

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
 * scalar identifiers are retained so the queued listener can serialize safely
 * even when a subject is deleted before the worker runs.
 */
abstract class AuditableEvent
{
    use Dispatchable;

    public ?int $organizationId;

    public ?int $actorId;

    public ?string $subjectType;

    public ?int $subjectId;

    /**
     * @var array<string, mixed>
     */
    public array $changes;

    public ?int $relationshipPeriodId;

    public CarbonImmutable $occurredAt;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        ?Organization $organization,
        ?User $actor,
        ?Model $subject = null,
        array $changes = [],
        ?OrganizationRelationshipPeriod $period = null,
    ) {
        $this->organizationId = $organization?->getKey();
        $this->actorId = $actor?->getKey();
        $this->subjectType = $subject?->getMorphClass();
        $this->subjectId = $subject?->getKey();
        $this->changes = $changes;
        $this->relationshipPeriodId = $period?->getKey();
        $this->occurredAt = CarbonImmutable::now();
    }

    abstract public function auditEvent(): AuditEvent;
}
