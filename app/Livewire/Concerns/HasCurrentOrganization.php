<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Auth;

trait HasCurrentOrganization
{
    protected function currentOrganization(): ?Organization
    {
        return app(CurrentOrganization::class)->get(Auth::user());
    }

    protected function currentRole(): ?Role
    {
        $organization = $this->currentOrganization();
        $user = Auth::user();

        return $organization !== null && $user instanceof User
            ? $user->roleIn($organization)
            : null;
    }

    protected function requireOrganization(): Organization
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            abort(403);
        }

        return $organization;
    }

    protected function authorizeManager(): void
    {
        $role = $this->currentRole();

        abort_unless($role?->isOwner() || $role?->isAdmin(), 403);
    }

    protected function authorizeRole(Role $role): void
    {
        abort_if($role->isOwner() && ! $this->currentRole()?->isOwner(), 403);
    }

    protected function authorizeTarget(Person $person): void
    {
        $targetRole = $person->roleIn($this->requireOrganization());

        abort_if($targetRole?->isOwner() && ! $this->currentRole()?->isOwner(), 403);
    }
}
