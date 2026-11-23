<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListNotifications
{
    /**
     * Paginate the user's notifications.
     *
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function paginate(User $user, bool $ascending, int $perPage = 10): LengthAwarePaginator
    {
        $direction = $ascending ? 'asc' : 'desc';

        return $user->notifications()
            ->reorder('created_at', $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage);
    }

    /**
     * List the notification ids on a page.
     *
     * @return array<int, string>
     */
    public function idsOnPage(User $user, bool $ascending, int $page, int $perPage = 10): array
    {
        $direction = $ascending ? 'asc' : 'desc';

        return $user->notifications()
            ->reorder('created_at', $direction)
            ->orderBy('id', $direction)
            ->forPage($page, $perPage)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }
}
