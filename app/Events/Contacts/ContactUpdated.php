<?php

declare(strict_types=1);

namespace App\Events\Contacts;

use App\Actions\Contacts\Audit\ContactUpdatedPayload;
use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\User;

final class ContactUpdated extends AuditableEvent
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        Organization $organization,
        ?User $actor,
        Entity $contact,
        array $fields,
        ?OrganizationRelationshipPeriod $period = null,
    ) {
        parent::__construct($organization, $actor, $contact, new ContactUpdatedPayload($fields), $period);
    }

    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ContactUpdated;
    }
}
