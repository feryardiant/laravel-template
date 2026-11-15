<?php

declare(strict_types=1);

namespace App\Events\Memberships;

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Events\AuditableEvent;
use App\Models\Organization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use App\Models\User;
use Illuminate\Queue\SerializesModels;

final class MemberRoleChanged extends AuditableEvent
{
    use SerializesModels;

    public function __construct(
        public Organization $organization,
        public Role $fromRole,
        public Role $toRole,
        public Person $person,
        public ?User $actor,
        ?OrganizationRelationshipPeriod $period = null,
    ) {
        parent::__construct($organization, $actor, $person, [
            'from' => $fromRole->value,
            'to' => $toRole->value,
        ], $period);
    }

    public function auditEvent(): AuditEvent
    {
        return AuditEvent::MemberRoleChanged;
    }
}
