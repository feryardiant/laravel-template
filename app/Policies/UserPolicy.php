<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function create(User $actor): bool
    {
        return $this->manages($actor);
    }

    public function update(User $actor, User $user): bool
    {
        return $this->manages($actor) && $this->canActOn($actor, $user);
    }

    public function deleteAny(User $actor): bool
    {
        return $this->manages($actor);
    }

    public function delete(User $actor, User $user): bool
    {
        return $this->manages($actor) && ! $actor->is($user) && $this->canActOn($actor, $user);
    }

    public function changeRole(User $actor, User $user): bool
    {
        return $this->manages($actor) && ! $actor->is($user) && $this->canActOn($actor, $user);
    }

    private function manages(User $actor): bool
    {
        return $actor->role->isOwner() || $actor->role->isAdmin();
    }

    private function canActOn(User $actor, User $user): bool
    {
        return $actor->role->isOwner() || ! $user->role->isOwner();
    }
}
