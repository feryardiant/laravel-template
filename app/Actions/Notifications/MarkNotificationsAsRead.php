<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;

/**
 * Marks the user's notifications as read or unread.
 */
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

    /**
     * Mark one notification as read, keeping an existing timestamp.
     */
    public function markOne(User $user, string $id): void
    {
        $user->notifications()->whereKey($id)->first()?->markAsRead();
    }
}
