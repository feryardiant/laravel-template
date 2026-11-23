<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;

final class MarkNotificationsAsRead
{
    /**
     * Mark the selected notifications as read or unread.
     *
     * @param  array<int, string>  $ids
     */
    public function handle(User $user, array $ids, bool $asRead): int
    {
        if ($ids === []) {
            return 0;
        }

        return $user->notifications()
            ->whereIn('id', $ids)
            ->update(['read_at' => $asRead ? now() : null]);
    }
}
