<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Approvals\ListApprovals;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lists the user's organizations with role, default, and approval state.
 */
final class ListUserMemberships
{
    public function __construct(
        private ListApprovals $listApprovals,
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
        $organizations = $user->organizations();
        $person = $user->person;
        $personId = $person?->getKey();
        $memberships = $this->membershipsFor($person, $organizations);

        $parents = $organizations->isEmpty()
            ? collect()
            : OrganizationRelationship::query()
                ->with('organization')
                ->subsidiaries()
                ->whereStakeholderType((new Organization)->getMorphClass())
                ->whereIn('stakeholder_id', $organizations->pluck('id')->all())
                ->get()
                ->keyBy('stakeholder_id');

        $approvals = $person === null
            ? collect()
            : $this->listApprovals->pendingForPerson($person);

        $asApprover = $approvals->where('approver_person_id', $personId)->keyBy('organization_id');
        $asRequester = $approvals->where('requester_person_id', $personId)->keyBy('organization_id');
        $ownerCounts = $this->ownerCounts($memberships);

        return $organizations->map(function (Organization $organization) use ($current, $memberships, $parents, $asApprover, $asRequester, $ownerCounts): array {
            $membership = $memberships->get($organization->getKey());
            $role = $membership?->role;
            $parent = $parents->get($organization->getKey());
            $approval = $asApprover->get($organization->getKey());
            $request = $asRequester->get($organization->getKey());

            return [
                'organization' => $organization,
                'role' => $role,
                'isCurrent' => $current?->is($organization) === true,
                'canManage' => $role?->isManager() === true,
                'isLastOwner' => $role?->isOwner() === true && $ownerCounts->get($organization->getKey()) === 1,
                'isDefault' => $membership?->is_primary === true,
                'parent' => $parent instanceof OrganizationRelationship ? $parent->organization : null,
                'kind' => $parent instanceof OrganizationRelationship
                    ? ($parent->kind ?? SubsidiaryKind::Subsidiary)
                    : null,
                'pendingApproval' => $approval instanceof ApprovalRequest ? $approval : null,
                'pendingRequest' => $request instanceof ApprovalRequest ? $request : null,
            ];
        });
    }

    /**
     * Load the user's membership rows for the listed organizations.
     *
     * @param  Collection<int, Organization>  $organizations
     * @return Collection<int, OrganizationRelationship>
     */
    private function membershipsFor(?Person $person, Collection $organizations): Collection
    {
        if ($person === null || $organizations->isEmpty()) {
            return new Collection;
        }

        return OrganizationRelationship::query()
            ->members()
            ->whereStakeholderType($person->getMorphClass())
            ->where('stakeholder_id', $person->getKey())
            ->whereIn('organization_id', $organizations->pluck('id')->all())
            ->get()
            ->keyBy('organization_id');
    }

    /**
     * Count the active owners of every organization the user owns.
     *
     * @param  Collection<int, OrganizationRelationship>  $memberships
     * @return Collection<int, int>
     */
    private function ownerCounts(Collection $memberships): Collection
    {
        $organizationIds = $memberships
            ->filter(fn (OrganizationRelationship $membership): bool => $membership->role?->isOwner() === true)
            ->keys()
            ->all();

        if ($organizationIds === []) {
            return collect();
        }

        return OrganizationRelationship::query()
            ->members()
            ->whereIn('organization_id', $organizationIds)
            ->where('role', Role::Owner)
            ->started()
            ->notExpired()
            ->whereHasMorph('stakeholder', [Person::class], fn (Builder $query): Builder => $query->whereHas('user'))
            ->groupBy('organization_id')
            ->selectRaw('organization_id, count(*) as aggregate')
            ->pluck('aggregate', 'organization_id');
    }
}
