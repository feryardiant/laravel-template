<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OrganizationRelationshipType;
use App\Enums\SubsidiaryKind;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class ListSubsidiaries
{
    /**
     * List the organizations under the root tenant's subsidiary tree.
     *
     * @return Collection<int, array{organization: Organization, parent: Organization, kind: ?SubsidiaryKind, depth: int}>
     */
    public function handle(Organization $root): Collection
    {
        $relationships = OrganizationRelationship::query()
            ->where('type', OrganizationRelationshipType::Subsidiary)
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
