<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads the organization's audit log and its actor options.
 */
final class AuditRepository
{
    public function __construct(
        private MembershipRepository $memberships,
    ) {
        // .
    }

    /**
     * Paginate the organization's audit entries.
     *
     * @param  array{event?: ?string, actor?: ?int, start?: ?string, end?: ?string}  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function get(
        Organization $organization,
        array $filters,
        string $timezone,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $start = $filters['start'] ?? null;
        $end = $filters['end'] ?? null;

        return AuditLog::query()
            ->forOrganization($organization)
            ->ofEvent($filters['event'] ?? null)
            ->byActor($filters['actor'] ?? null)
            ->createdBetween(
                $start !== null ? CarbonImmutable::parse($start, $timezone)->startOfDay()->utc() : null,
                $end !== null ? CarbonImmutable::parse($end, $timezone)->endOfDay()->utc() : null,
            )
            ->with(['actor.person', 'subject'])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * List members with a credential, current user first.
     *
     * @return Collection<int, Person>
     */
    public function getActors(Organization $organization, ?User $current): Collection
    {
        $currentId = $current?->getKey();
        $members = $this->memberships->getCredentialed($organization);
        $currentPerson = $current?->person;

        if ($currentPerson instanceof Person
            && ! $members->contains(fn (Person $member): bool => $member->user_id === $currentId)) {
            $currentPerson->loadMissing('user');
            $members->push($currentPerson);
        }

        return $members
            ->sortBy(fn (Person $member): int => $member->user_id === $currentId ? 0 : 1)
            ->values();
    }
}
