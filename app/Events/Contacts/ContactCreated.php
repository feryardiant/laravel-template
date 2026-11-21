<?php

declare(strict_types=1);

namespace App\Events\Contacts;

use App\Actions\Contacts\Audit\ContactRelationPayload;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Events\AuditableEvent;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\User;

final class ContactCreated extends AuditableEvent
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        Organization $organization,
        ?User $actor,
        Entity $contact,
        OrganizationRelationshipType $type,
        array $fields = [],
        ?OrganizationRelationshipPeriod $period = null,
    ) {
        parent::__construct($organization, $actor, $contact, new ContactRelationPayload(
            $contact instanceof Organization ? 'organization' : 'individual',
            $type,
            $fields,
        ), $period);
    }

    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ContactCreated;
    }
}
