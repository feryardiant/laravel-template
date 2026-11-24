<?php

declare(strict_types=1);

namespace App\Actions\AuditLogs;

use App\Models\AuditLog;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Paginates the organization's audit log with its filters applied.
 */
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
}
