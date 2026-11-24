<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\AuditLog;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<AuditLog>
 */
final class AuditLogBuilder extends Builder
{
    use Concerns\ForOrganization;

    /**
     * Limit the query to the given event name.
     */
    public function ofEvent(?string $event): static
    {
        return $event === null ? $this : $this->where('event', $event);
    }

    /**
     * Limit the query to the given actor.
     */
    public function byActor(?int $actor): static
    {
        return $actor === null ? $this : $this->where('actor_id', $actor);
    }

    /**
     * Limit the query to entries created inside the bounds.
     */
    public function createdBetween(?DateTimeInterface $from, ?DateTimeInterface $until): static
    {
        return $this
            ->when($from !== null, fn (Builder $query): Builder => $query->where('created_at', '>=', $from))
            ->when($until !== null, fn (Builder $query): Builder => $query->where('created_at', '<=', $until));
    }
}
