<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MemberRoleChanged;
use App\Notifications\UserRoleChangedNotification;

class SendMemberRoleChangedNotification
{
    public function handle(MemberRoleChanged $event): void
    {
        $event->person->user?->notify(new UserRoleChangedNotification(
            $event->actor,
            $event->newRole,
            $event->organization,
        ));
    }
}
