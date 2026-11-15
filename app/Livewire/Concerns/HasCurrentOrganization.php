<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait HasCurrentOrganization
{
    /**
     * Whether the current user owns the organization.
     */
    #[Computed()]
    public function isOwner(): bool
    {
        return $this->currentRole()?->isOwner() === true;
    }

    /**
     * Whether the current user can manage the organization.
     */
    #[Computed()]
    public function isManager(): bool
    {
        $role = $this->currentRole();

        return $role?->isOwner() || $role?->isAdmin();
    }

    #[Computed()]
    public function currentOrganization(): ?Organization
    {
        return app(CurrentOrganization::class)->get();
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

    /**
     * Switch the authenticated user's current organization.
     */
    public function switchOrganization(int $organizationId): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $organization = Organization::query()->findOrFail($organizationId);

        abort_unless(app(CurrentOrganization::class)->switch($user, $organization), 403);

        $referer = request()->header('Referer');

        $target = $referer !== null && parse_url($referer, PHP_URL_HOST) === request()->getHost()
            ? $referer
            : route('dashboard');

        $this->redirect($target, navigate: true);
    }

    private function authorizeOwner(): void
    {
        abort_unless($this->isOwner(), 403);
    }

    protected function authorizeManager(): void
    {
        abort_unless($this->isManager(), 403);
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
