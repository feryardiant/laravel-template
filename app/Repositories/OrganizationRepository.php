<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Reads organizations, subsidiary trees, and the caller's memberships.
 */
final class OrganizationRepository
{
    public function __construct(
        private ApprovalRepository $approvals,
    ) {
        // .
    }

    /**
     * Resolve any organization by id, regardless of membership.
     */
    public function find(int $organizationId): Organization
    {
        return Organization::query()->findOrFail($organizationId);
    }

    /**
     * Resolve one of the user's organizations without failing.
     */
    public function findUser(User $user, int $organizationId): ?Organization
    {
        return $user->organizations()->firstWhere('id', $organizationId);
    }

    /**
     * List organizations that can act as the parent of a new one.
     *
     * @return Collection<int, Organization>
     */
    public function getCreatable(User $user, bool $internal): Collection
    {
        if ($internal) {
            $root = Organization::query()->rootTenant()->first();

            return $root === null
                ? collect()
                : $this->getSubsidiaries($root)->pluck('organization');
        }

        $person = $user->person;

        if ($person === null) {
            return collect();
        }

        // Mirrors OrganizationPolicy::createSubsidiary without a role query per organization.
        return $person->organizations()
            ->wherePivot('role', Role::Owner->value)
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * List the organizations under the root tenant's subsidiary tree.
     *
     * @return Collection<int, array{organization: Organization, parent: Organization, kind: ?SubsidiaryKind, depth: int}>
     */
    public function getSubsidiaries(Organization $root): Collection
    {
        $relationships = OrganizationRelationship::query()
            ->subsidiaries()
            ->orderBy('id')
            ->get();

        $organizations = Organization::query()
            ->whereIn('id', $relationships->pluck('stakeholder_id')->unique()->all())
            ->get()
            ->keyBy(fn (Organization $organization): int => $organization->getKey())
            ->toBase();

        $rows = collect();

        $this->walk($root, $relationships->groupBy('organization_id')->toBase(), $organizations, 0, $rows);

        return $rows;
    }

    /**
     * Build the organization settings list for the user.
     *
     * @return Collection<int, array{organization: Organization, role: ?Role, isCurrent: bool, canManage: bool, isLastOwner: bool, isDefault: bool, parent: ?Organization, kind: ?SubsidiaryKind, pendingApproval: ?ApprovalRequest, pendingRequest: ?ApprovalRequest}>
     */
    public function getUsers(User $user, ?Organization $current): Collection
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
            : $this->approvals->getPendingForPerson($person);

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
     * Resolve a subsidiary and the number of rows it would remove.
     *
     * @return array{organization: Organization, count: int}|null
     */
    public function preview(Organization $root, int $organizationId): ?array
    {
        if (! $root->isRootTenant()) {
            return null;
        }

        $rows = $this->getSubsidiaries($root);
        $index = $rows->search(
            fn (array $row): bool => $row['organization']->getKey() === $organizationId,
        );

        if ($index === false) {
            return null;
        }

        $row = $rows->get($index);

        if ($row === null) {
            return null;
        }

        $count = 1;

        for ($position = $index + 1; $position < $rows->count(); $position++) {
            if ($rows[$position]['depth'] <= $row['depth']) {
                break;
            }

            $count++;
        }

        return ['organization' => $row['organization'], 'count' => $count];
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

    /**
     * Walk the tree depth-first, starting at the given parent.
     *
     * @param  Collection<int|string, EloquentCollection<int, OrganizationRelationship>>  $byParent
     * @param  Collection<int, Organization>  $organizations
     * @param  Collection<int, array{organization: Organization, parent: Organization, kind: ?SubsidiaryKind, depth: int}>  $rows
     */
    private function walk(Organization $parent, Collection $byParent, Collection $organizations, int $depth, Collection $rows): void
    {
        foreach ($byParent->get($parent->getKey(), []) as $relationship) {
            $organization = $organizations->get($relationship->stakeholder_id);

            if (! $organization instanceof Organization) {
                continue;
            }

            $rows->push([
                'organization' => $organization,
                'parent' => $parent,
                'kind' => $relationship->kind,
                'depth' => $depth,
            ]);

            $this->walk($organization, $byParent, $organizations, $depth + 1, $rows);
        }
    }
}
