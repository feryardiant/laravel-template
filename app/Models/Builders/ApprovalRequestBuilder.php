<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Models\ApprovalRequest;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<ApprovalRequest>
 */
final class ApprovalRequestBuilder extends Builder
{
    use Concerns\ForOrganization;

    /**
     * Resolve an approval for the approver, without changing its lifecycle.
     */
    public function forApprover(Person|int $approver): static
    {
        $approver = $approver instanceof Person ? $approver->getKey() : $approver;

        return $this->where('approver_person_id', $approver);
    }

    /**
     * Resolve an approval for the requester.
     */
    public function forRequester(Person|int $requester): static
    {
        $requester = $requester instanceof Person ? $requester->getKey() : $requester;

        return $this->where('requester_person_id', $requester);
    }

    /**
     * Limit the query to the given request type.
     */
    public function ofType(ApprovalRequestType $type): static
    {
        return $this->where('type', $type);
    }

    /**
     * Limit the query to requests where the person is requester or approver.
     */
    public function involvingPerson(Person|int $person): static
    {
        $person = $person instanceof Person ? $person->getKey() : $person;

        return $this->where(fn (Builder $query): Builder => $query
            ->where('requester_person_id', $person)
            ->orWhere('approver_person_id', $person));
    }

    /**
     * Limit the query to pending requests that have not expired.
     */
    public function onlyPendings(): static
    {
        return $this
            ->where('status', ApprovalRequestStatus::Pending)
            ->where('expires_at', '>', now());
    }

    /**
     * Limit the query to pending requests that have expired.
     */
    public function expiredPendings(): static
    {
        return $this
            ->where('status', ApprovalRequestStatus::Pending)
            ->where('expires_at', '<=', now());
    }
}
