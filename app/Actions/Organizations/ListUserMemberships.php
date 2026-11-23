<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Approvals\ListPendingApprovalsForPerson;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class ListUserMemberships
{
    public function __construct(
        private ListPendingApprovalsForPerson $pendingApprovals,
    ) {
        // .
    }

    /**
     * Build the organization settings list for the user.
     *
     * @return Collection<int, array{organization: Organization, role: ?Role, isCurrent: bool, canManage: bool, isLastOwner: bool, isDefault: bool, parent: ?Organization, kind: ?SubsidiaryKind, pendingApproval: ?ApprovalRequest, pendingRequest: ?ApprovalRequest}>
     */
    public function handle(User $user, ?Organization $current): Collection
    {
        $defaultId = $user->defaultOrganization()?->getKey();
        $organizations = $user->organizations();

        $parents = $organizations->isEmpty()
            ? collect()
            : OrganizationRelationship::query()
                ->with('organization')
                ->where('type', OrganizationRelationshipType::Subsidiary)
                ->where('stakeholder_type', (new Organization)->getMorphClass())
                ->whereIn('stakeholder_id', $organizations->pluck('id')->all())
                ->get()
                ->keyBy('stakeholder_id');

        $person = $user->person;
        $personId = $person?->getKey();
        $approvals = $person === null
            ? collect()
            : $this->pendingApprovals->handle($person);

        $asApprover = $approvals->where('approver_person_id', $personId)->keyBy('organization_id');
        $asRequester = $approvals->where('requester_person_id', $personId)->keyBy('organization_id');

        return $organizations->map(function (Organization $organization) use ($user, $current, $defaultId, $parents, $asApprover, $asRequester): array {
            $role = $user->roleIn($organization);
            $parent = $parents->get($organization->getKey());
            $approval = $asApprover->get($organization->getKey());
            $request = $asRequester->get($organization->getKey());

            return [
                'organization' => $organization,
                'role' => $role,
                'isCurrent' => $current?->is($organization) === true,
                'canManage' => Gate::forUser($user)->allows('update', $organization),
                'isLastOwner' => $role?->isOwner() === true && $organization->activeOwnerCount() === 1,
                'isDefault' => $defaultId !== null && $defaultId === $organization->getKey(),
                'parent' => $parent instanceof OrganizationRelationship ? $parent->organization : null,
                'kind' => $parent instanceof OrganizationRelationship
                    ? ($parent->kind ?? SubsidiaryKind::Subsidiary)
                    : null,
                'pendingApproval' => $approval instanceof ApprovalRequest ? $approval : null,
                'pendingRequest' => $request instanceof ApprovalRequest ? $request : null,
            ];
        });
    }
}
