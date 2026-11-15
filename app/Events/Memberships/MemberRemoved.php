<?php

declare(strict_types=1);

namespace App\Events\Memberships;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Organization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use App\Models\User;
use Illuminate\Queue\SerializesModels;

final class MemberRemoved extends AuditableEvent
{
    use SerializesModels;

    /**
     * @param  'self'|'system'|'manager'  $via
     */
    public function __construct(
        public Organization $organization,
        public ?User $actor,
        public Person $person,
        public string $via,
        public bool $lastMembership,
        ?OrganizationRelationshipPeriod $period = null,
    ) {
        parent::__construct($organization, $actor, $person, [
            'via' => $via,
            'last_membership' => $lastMembership,
        ], $period);
    }

    public function auditEvent(): AuditEvent
    {
        return AuditEvent::MemberRemoved;
    }
}
