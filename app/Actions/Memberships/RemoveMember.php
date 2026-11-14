<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\ApprovalRequestStatus;
use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipPeriodReason;
use App\Events\MemberRemoved;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RemoveMember
{
    public function __construct(private RelationshipTimeline $timeline)
    {
        // .
    }

    /**
     * Remove a person from the organization.
     */
    public function handle(Organization $organization, Person $person, ?User $actor = null): void
    {
        $this->handleMany($organization, new Collection([$person]), $actor);
    }

    /**
     * Remove several people from the organization in one transaction.
     *
     * @param  Collection<int, Person>  $persons
     */
    public function handleMany(Organization $organization, Collection $persons, ?User $actor = null): void
    {
        if ($persons->isEmpty()) {
            return;
        }

        $removed = DB::transaction(function () use ($organization, $persons, $actor): Collection {
            $organization = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->first();

            if ($organization === null) {
                return new Collection;
            }

            $actorRole = $actor?->person?->roleIn($organization);

            if ($actor !== null && ($actorRole?->isOwner() !== true && $actorRole?->isAdmin() !== true)) {
                return new Collection;
            }

            // Owners only leave through `LeaveOrganization` or an ownership
            // transfer, even when the removal was authorized before a transfer.
            // Only people still in the organization are detached, so a repeated
            // or stale request cannot emit another removal event.
            $removed = $organization->members()
                ->whereKey($persons->modelKeys())
                ->get()
                ->reject(
                    fn (Person $person): bool => $person->roleIn($organization)?->isOwner() === true,
                );

            if ($removed->isNotEmpty()) {
                $relationships = OrganizationRelationship::query()
                    ->forOrganization($organization)
                    ->where('stakeholder_type', (new Person)->getMorphClass())
                    ->whereIn('stakeholder_id', $removed->modelKeys())
                    ->where('type', OrganizationRelationshipType::Member)
                    ->get();

                foreach ($relationships as $relationship) {
                    $this->timeline->close($relationship, RelationshipPeriodReason::Removed, $actor);
                }
            }

            $organization->members()->detach($removed->modelKeys());

            if ($removed->isNotEmpty()) {
                ApprovalRequest::query()
                    ->pending()
                    ->where('organization_id', $organization->getKey())
                    ->where(fn ($query) => $query
                        ->whereIn('requester_person_id', $removed->modelKeys())
                        ->orWhereIn('approver_person_id', $removed->modelKeys()))
                    ->update([
                        'status' => ApprovalRequestStatus::Cancelled,
                        'cancelled_at' => now(),
                    ]);
            }

            return $removed;
        });

        foreach ($removed as $person) {
            try {
                event(new MemberRemoved(
                    $organization,
                    $person,
                    $actor,
                    ! $person->organizations()->exists(),
                ));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
