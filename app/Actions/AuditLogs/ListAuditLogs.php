<?php

declare(strict_types=1);

namespace App\Actions\AuditLogs;

use App\Models\AuditLog;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListAuditLogs
{
    /**
     * Paginate the organization's audit entries.
     *
     * @param  array{event?: ?string, actor?: ?int, start?: ?string, end?: ?string}  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function handle(
        Organization $organization,
        array $filters,
        string $timezone,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $event = $filters['event'] ?? null;
        $actor = $filters['actor'] ?? null;
        $start = $filters['start'] ?? null;
        $end = $filters['end'] ?? null;

        $query = AuditLog::query()
            ->where('organization_id', $organization->getKey())
            ->with(['actor.person', 'subject'])
            ->when($event !== null, fn (Builder $query): Builder => $query->where('event', $event))
            ->when($actor !== null, fn (Builder $query): Builder => $query->where('actor_id', $actor));

        if ($start !== null) {
            $query->where('created_at', '>=', CarbonImmutable::parse($start, $timezone)->startOfDay()->utc());
        }

        if ($end !== null) {
            $query->where('created_at', '<=', CarbonImmutable::parse($end, $timezone)->endOfDay()->utc());
        }

        return $query->latest('id')->paginate($perPage);
    }
}
