<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads the user's notifications and unread counts.
 */
final class NotificationRepository
{
    /**
     * Paginate the user's notifications.
     *
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function get(User $user, bool $ascending, int $perPage = 10): LengthAwarePaginator
    {
        return $this->ordered($user, $ascending)->paginate($perPage);
    }

    /**
     * List the notification ids on a page.
     *
     * @return array<int, string>
     */
    public function getIdsOnPage(User $user, bool $ascending, int $page, int $perPage = 10): array
    {
        return $this->ordered($user, $ascending)
            ->forPage($page, $perPage)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * Count unread notifications, optionally limited to the given range.
     *
     * @param  array{start: string, end: string}|null  $range
     */
    public function countUnread(User $user, ?array $range = null, string $timezone = 'UTC'): int
    {
        $query = $user->unreadNotifications();

        if ($range !== null) {
            $query
                ->where('created_at', '>=', CarbonImmutable::parse($range['start'], $timezone)->startOfDay()->utc())
                ->where('created_at', '<=', CarbonImmutable::parse($range['end'], $timezone)->endOfDay()->utc());
        }

        return $query->count();
    }

    /**
     * Resolve one of the user's notifications.
     */
    public function find(User $user, string $id): ?DatabaseNotification
    {
        return $user->notifications()->whereKey($id)->first();
    }

    /**
     * Order the user's notifications by the requested direction.
     *
     * @return MorphMany<DatabaseNotification, User>
     */
    private function ordered(User $user, bool $ascending): MorphMany
    {
        $direction = $ascending ? 'asc' : 'desc';

        return $user->notifications()
            ->reorder('created_at', $direction)
            ->orderBy('id', $direction);
    }
}
