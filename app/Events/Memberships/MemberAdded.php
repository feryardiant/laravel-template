<?php

declare(strict_types=1);

namespace App\Events\Memberships;

use App\Actions\Memberships\Audit\MemberAddedPayload;
use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Events\AuditableEvent;
use App\Models\Organization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use App\Models\User;

final class MemberAdded extends AuditableEvent
{
    public function __construct(
        Organization $organization,
        ?User $actor,
        Person $person,
        Role $role,
        ?OrganizationRelationshipPeriod $period = null,
    ) {
        parent::__construct($organization, $actor, $person, new MemberAddedPayload($role), $period);
    }

    public function auditEvent(): AuditEvent
    {
        return AuditEvent::MemberAdded;
    }
}
