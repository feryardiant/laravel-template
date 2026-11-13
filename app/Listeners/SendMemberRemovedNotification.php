<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MemberRemoved;
use App\Notifications\MembershipRemovedNotification;

class SendMemberRemovedNotification
{
    public function handle(MemberRemoved $event): void
    {
        $event->person->user?->notify(new MembershipRemovedNotification(
            $event->organization,
            $event->actor,
            $event->lastMembership,
        ));
    }
}
