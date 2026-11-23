<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Memberships\SetDefaultOrganization;
use App\Actions\Organizations\FindUserOrganization;
use App\Actions\Organizations\ListUserMemberships;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Organizations extends Component
{
    use HasCurrentOrganization;

    /**
     * Switch to the organization and open its settings.
     */
    public function manage(int $organizationId): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $organization = app(FindUserOrganization::class)->handle($user, $organizationId);

        $this->authorize('update', $organization);

        abort_unless(app(CurrentOrganization::class)->switch($user, $organization), 403);

        $this->redirect(route('org-settings.general'), navigate: true);
    }

    /**
     * Make the given organization the user's default workspace.
     */
    public function setDefault(int $organizationId, SetDefaultOrganization $setDefault): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $organization = app(FindUserOrganization::class)->handle($user, $organizationId);

        abort_unless($setDefault->handle($user, $organization), 404);

        unset($this->memberships);

        Flux::toast(variant: 'success', text: __('Default organization updated.'));
    }

    /**
     * The organizations the user belongs to, with their role and options.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function memberships(): Collection
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user instanceof User
            ? app(ListUserMemberships::class)->handle($user, $this->currentOrganization())
            : collect();
    }

    #[On('organization-created')]
    #[On('memberships-changed')]
    public function refreshMemberships(): void
    {
        unset($this->memberships);
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.settings.organizations')->title(__('Organizations'));
    }
}
