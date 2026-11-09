<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AddMember
{
    /**
     * Add a person as a member of the organization.
     */
    public function handle(
        Organization $organization,
        Person $member,
        bool $isPrimary = false,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $finishDate = null,
        Role $role = Role::Member,
    ): void {
        DB::transaction(function () use ($organization, $member, $isPrimary, $startDate, $finishDate, $role): void {
            $member->newQuery()
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($isPrimary) {
                // shortcut: primary membership is global until relationship rows
                // carry a tenant key; then scope this reset to the organization.
                $member->organizationRelationships()->newPivotQuery()
                    ->where('type', OrganizationRelationshipType::Member)
                    ->update(['is_primary' => null]);
            }

            OrganizationRelationship::query()->updateOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'stakeholder_type' => $member->getMorphClass(),
                    'stakeholder_id' => $member->getKey(),
                    'type' => OrganizationRelationshipType::Member,
                ],
                [
                    'is_primary' => $isPrimary ? true : null,
                    'role' => $role,
                    'start_date' => $startDate,
                    'finish_date' => $finishDate,
                ],
            );
        });
    }
}
