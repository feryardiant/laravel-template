<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * @property-read ?Organization $currentOrganization
 */
trait HasCurrentOrganization
{
    /**
     * The workspace the user currently has active.
     */
    #[Computed()]
    public function currentOrganization(): ?Organization
    {
        return app(CurrentOrganization::class)->get();
    }

    /**
     * The user's role in the current organization.
     */
    protected function currentRole(): ?Role
    {
        $organization = $this->currentOrganization();
        /** @var User $user */
        $user = Auth::user();

        return $organization !== null
            ? $user->roleIn($organization)
            : null;
    }

    /**
     * The current organization, aborting when none is active.
     *
     * @param  array<int, Closure(?Organization $organization):bool>  $aborts
     */
    protected function requireOrganization(array $aborts = []): Organization
    {
        $organization = $this->currentOrganization();

        if (blank($aborts)) {
            $aborts = [
                403 => fn (?Organization $org) => $org === null,
            ];
        }

        foreach ($aborts as $httpCode => $condition) {
            abort_if($condition($organization), $httpCode);
        }

        return $organization;
    }

    /**
     * Switch the authenticated user's current organization.
     */
    public function switchOrganization(int $organizationId): void
    {
        abort_unless(app(CurrentOrganization::class)->switch($organizationId), 403);

        $referer = request()->header('Referer');

        $target = $referer !== null && parse_url($referer, PHP_URL_HOST) === request()->getHost()
            ? $referer
            : route('dashboard');

        $this->redirect($target, navigate: true);
    }
}
