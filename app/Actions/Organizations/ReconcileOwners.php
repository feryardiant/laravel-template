<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

final class ReconcileOwners
{
    /**
     * Keep one owner per organization, preferring one who can still sign in.
     *
     * Earlier releases allowed several owners, so the remaining rows are
     * demoted to Admin before the single-owner index is created.
     */
    public function handle(): void
    {
        $today = now()->toDateString();

        $owners = DB::table('organization_relationships as relationships')
            ->leftJoin('people', 'people.id', '=', 'relationships.stakeholder_id')
            ->where('relationships.type', OrganizationRelationshipType::Member->value)
            ->where('relationships.stakeholder_type', (new Person)->getMorphClass())
            ->where('relationships.role', Role::Owner->value)
            ->orderBy('relationships.id')
            ->get([
                'relationships.id',
                'relationships.organization_id',
                'people.user_id',
                'relationships.start_date',
                'relationships.finish_date',
            ])
            ->groupBy('organization_id');

        foreach ($owners as $organizationOwners) {
            $keep = $organizationOwners->first(fn (object $owner): bool => $owner->user_id !== null
                && ($owner->start_date === null || $owner->start_date <= $today)
                && ($owner->finish_date === null || $owner->finish_date >= $today))
                ?? $organizationOwners->first();

            DB::table('organization_relationships')
                ->whereIn('id', $organizationOwners->pluck('id')->reject(
                    fn (int $id): bool => $id === (int) $keep->id,
                ))
                ->update(['role' => Role::Admin->value]);
        }
    }
}
