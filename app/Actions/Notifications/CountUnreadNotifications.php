<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;
use Carbon\CarbonImmutable;

final class CountUnreadNotifications
{
    /**
     * Count unread notifications created inside the range.
     *
     * @param  array{start: string, end: string}  $range
     */
    public function handle(User $user, array $range, string $timezone): int
    {
        return $user->unreadNotifications()
            ->where('created_at', '>=', CarbonImmutable::parse($range['start'], $timezone)->startOfDay()->utc())
            ->where('created_at', '<=', CarbonImmutable::parse($range['end'], $timezone)->endOfDay()->utc())
            ->count();
    }
}
