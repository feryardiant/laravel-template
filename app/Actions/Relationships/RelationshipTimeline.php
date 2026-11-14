<?php

declare(strict_types=1);

namespace App\Actions\Relationships;

use App\Enums\RelationshipPeriodReason;
use App\Models\OrganizationRelationship;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RelationshipTimeline
{
    /**
     * Open a period for a newly attached relationship.
     */
    public function open(OrganizationRelationship $relationship, ?CarbonInterface $startDate = null): OrganizationRelationshipPeriod
    {
        $period = $this->openPeriod($relationship);

        if ($period !== null) {
            $this->sync($relationship);

            return $period->refresh();
        }

        return $this->create($relationship, $startDate);
    }

    /**
     * Close the current period before the relationship changes or is removed.
     */
    public function close(
        OrganizationRelationship $relationship,
        RelationshipPeriodReason $reason,
        ?User $actor = null,
    ): OrganizationRelationshipPeriod {
        $period = $this->openPeriod($relationship) ?? $this->open($relationship);

        $finish = $period->finish_date;
        $end = $finish !== null && $finish->lt(today()) ? $finish : today();

        $period->update([
            'finish_date' => $end,
            'reason' => $reason,
            'actor_id' => $actor?->getKey(),
            'closed_at' => now(),
        ]);

        return $period;
    }

    /**
     * Sync the open period after dates, code, or type were edited in place.
     */
    public function sync(OrganizationRelationship $relationship): void
    {
        $period = $this->openPeriod($relationship);

        if ($period === null) {
            $this->open($relationship);

            return;
        }

        $period->update([
            'type' => $relationship->type ?? $period->type,
            'code' => $relationship->code,
            'role' => $relationship->role,
            'start_date' => $relationship->start_date ?? $period->start_date,
            'finish_date' => $relationship->finish_date,
        ]);
    }

    /**
     * Find the current, unclosed period.
     */
    private function openPeriod(OrganizationRelationship $relationship): ?OrganizationRelationshipPeriod
    {
        return OrganizationRelationshipPeriod::query()
            ->where('organization_relationship_id', $relationship->getKey())
            ->whereNull('closed_at')
            ->latest('id')
            ->first();
    }

    /**
     * Persist a new period from the relationship's current state.
     */
    private function create(
        OrganizationRelationship $relationship,
        ?CarbonInterface $startDate,
    ): OrganizationRelationshipPeriod {
        $type = $relationship->type;

        if ($type === null) {
            throw new InvalidArgumentException('A relationship needs a type to record a period.');
        }

        return DB::transaction(fn (): OrganizationRelationshipPeriod => OrganizationRelationshipPeriod::create([
            'organization_id' => $relationship->organization_id,
            'organization_relationship_id' => $relationship->getKey(),
            'stakeholder_type' => $relationship->stakeholder_type,
            'stakeholder_id' => $relationship->stakeholder_id,
            'type' => $type,
            'code' => $relationship->code,
            'role' => $relationship->role,
            'start_date' => $startDate ?? $relationship->start_date ?? today(),
            'finish_date' => $relationship->finish_date,
        ]));
    }
}
