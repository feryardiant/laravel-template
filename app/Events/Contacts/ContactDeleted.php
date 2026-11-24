<?php

declare(strict_types=1);

namespace App\Events\Contacts;

use App\Actions\Contacts\Audit\ContactDeletedPayload;
use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\User;

/**
 * Raised when a contact relationship is deleted.
 */
final class ContactDeleted extends AuditableEvent
{
    public function __construct(
        Organization $organization,
        ?User $actor,
        Entity $contact,
        bool $detachedOnly,
        ?OrganizationRelationshipPeriod $period = null,
    ) {
        parent::__construct($organization, $actor, $contact, new ContactDeletedPayload($detachedOnly), $period);
    }

    /**
     * The audit event recorded for this change.
     */
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ContactDeleted;
    }
}
