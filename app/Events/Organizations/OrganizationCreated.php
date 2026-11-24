<?php

declare(strict_types=1);

namespace App\Events\Organizations;

use App\Actions\Organizations\Audit\OrganizationCreatedPayload;
use App\Enums\AuditEvent;
use App\Enums\SubsidiaryKind;
use App\Events\AuditableEvent;
use App\Models\Organization;
use App\Models\User;

/**
 * Raised when an organization is created.
 */
final class OrganizationCreated extends AuditableEvent
{
    public function __construct(
        Organization $organization,
        ?User $actor,
        ?int $parentId,
        ?SubsidiaryKind $kind,
    ) {
        parent::__construct(
            $organization,
            $actor,
            $organization,
            new OrganizationCreatedPayload($parentId, $kind),
        );
    }

    /**
     * The audit event recorded for this change.
     */
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::OrganizationCreated;
    }
}
