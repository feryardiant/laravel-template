<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Role;
use App\Events\MemberRemoved;
use App\Notifications\MemberLeftNotification;
use Illuminate\Database\Eloquent\Builder;

class SendMemberLeftNotification
{
    public function handle(MemberRemoved $event): void
    {
        if ($event->actor !== null) {
            return;
        }

        $today = now()->toDateString();

        $owners = $event->organization->members()
            ->wherePivot('role', Role::Owner->value)
            ->whereHas('user')
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
