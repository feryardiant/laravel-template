<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait HasCurrentOrganization
{
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
}
