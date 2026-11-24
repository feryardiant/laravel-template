<?php

declare(strict_types=1);

namespace App\Events\Organizations;

use App\Actions\Organizations\Audit\OrganizationUpdatedPayload;
use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Organization;
use App\Models\User;

/**
 * Raised when an organization is updated.
 */
final class OrganizationUpdated extends AuditableEvent
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        Organization $organization,
        ?User $actor,
        array $fields,
        bool $restored = false,
    ) {
        parent::__construct(
            $organization,
            $actor,
            $organization,
            new OrganizationUpdatedPayload($fields, $restored),
        );
    }

    /**
     * The audit event recorded for this change.
     */
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::OrganizationUpdated;
    }
}
