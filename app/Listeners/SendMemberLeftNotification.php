<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Memberships\MemberLeft;
use App\Notifications\MemberLeftNotification;
use Illuminate\Database\Eloquent\Builder;

class SendMemberLeftNotification
{
    public function handle(MemberLeft $event): void
    {
        $today = now()->toDateString();

        $owners = $event->organization->owners()
            ->whereKeyNot($event->person->getKey())
            ->where(fn (Builder $query): Builder => $query->whereNull('organization_relationships.start_date')
                ->orWhere('organization_relationships.start_date', '<=', $today))
            ->where(fn (Builder $query): Builder => $query->whereNull('organization_relationships.finish_date')
                ->orWhere('organization_relationships.finish_date', '>=', $today))
            ->get();

        foreach ($owners as $owner) {
            $owner->user?->notify(new MemberLeftNotification($event->organization, $event->person->name));
        }
    }
}
