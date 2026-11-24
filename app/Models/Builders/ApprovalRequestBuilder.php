<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Enums\ApprovalRequestStatus;
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
    public function forRequester(Person|int $approver): static
    {
        $approver = $approver instanceof Person ? $approver->getKey() : $approver;

        return $this->where('requester_person_id', $approver);
    }

    /**
     * Limit the query to pending requests.
     */
    public function onlyPendings(bool $expired = false): static
    {
        return $this
            ->where('status', ApprovalRequestStatus::Pending)
            ->where('expires_at', $expired ? '<=' : '>', now());
    }
}
