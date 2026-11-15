<?php

declare(strict_types=1);

namespace App\Events\Memberships;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use App\Models\User;
use Illuminate\Queue\SerializesModels;

final class MemberLeft extends AuditableEvent
{
    use SerializesModels;

    /**
     * @param  'self'|'approval'  $via
     */
    public function __construct(
        public Organization $organization,
        public ?User $actor,
        public Person $person,
        public string $via,
        public ?ApprovalRequest $approval = null,
        ?OrganizationRelationshipPeriod $period = null,
    ) {
        parent::__construct($organization, $actor, $person, array_filter([
            'via' => $via,
            'approval_id' => $approval?->getKey(),
        ]), $period);
    }

    public function auditEvent(): AuditEvent
    {
        return AuditEvent::MemberLeft;
    }
}
