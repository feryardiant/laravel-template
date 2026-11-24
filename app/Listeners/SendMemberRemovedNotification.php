<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Memberships\MemberRemoved;
use App\Notifications\MembershipRemovedNotification;

/**
 * Queues the membership removed notification for the removed member.
 */
class SendMemberRemovedNotification
{
    public function handle(MemberRemoved $event): void
    {
        $event->person->user?->notify(new MembershipRemovedNotification(
            $event->organization,
            $event->actor,
            $event->lastMembership,
            $event->via,
        ));
    }
}
