<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Memberships\MemberRoleChanged;
use App\Notifications\UserRoleChangedNotification;

/**
 * Queues the role changed notification for the member whose role changed.
 */
class SendMemberRoleChangedNotification
{
    public function handle(MemberRoleChanged $event): void
    {
        if (! $event->actor) {
            return;
        }

        $event->person->user?->notify(new UserRoleChangedNotification(
            $event->actor,
            $event->toRole,
            $event->organization,
        ));
    }
}
