<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Contacts\CountExpiringContacts;
use App\Actions\Contacts\CountNewContacts;
use App\Actions\Contacts\ListExpiringContacts;
use App\Actions\Memberships\CountNewMembers;
use App\Actions\Memberships\ListRecentMembers;
use App\Actions\Notifications\CountUnreadNotifications;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Workspace overview for the current organization, filtered by a date range.
 *
 * @property-read int $newMembers
 * @property-read int $newContacts
 * @property-read int $expiringCount
 * @property-read int $unreadNotifications
 * @property-read Collection<int, OrganizationRelationship> $expiringContacts
 * @property-read Collection<int, Person> $recentMembers
 */
class Dashboard extends Component
{
    use HasCurrentOrganization;

    /**
     * @var array{start: ?string, end: ?string}
     */
    public array $range = ['start' => null, 'end' => null];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->range = DateRange::defaultWeek($this->timezone());
    }

    /**
     * Normalize the date range.
     */
    public function updatedRange(): void
    {
        $this->range = DateRange::normalize($this->range, $this->timezone());
    }

    /**
     * Members whose membership started inside the range and is still active.
     */
    #[Computed]
    public function newMembers(): int
    {
        return app(CountNewMembers::class)->handle($this->requireOrganization(), $this->range);
    }

    /**
     * External contacts whose relationship started inside the range.
     */
    #[Computed]
    public function newContacts(): int
    {
        return app(CountNewContacts::class)->handle($this->requireOrganization(), $this->range);
    }

    /**
     * Active external contacts finishing inside the range.
     */
    #[Computed]
    public function expiringCount(): int
    {
        return app(CountExpiringContacts::class)->handle($this->requireOrganization(), $this->range);
    }

    #[Computed]
    public function unreadNotifications(): int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return 0;
        }

        return app(CountUnreadNotifications::class)->handle($user, $this->range, $this->timezone());
    }

    /**
     * @return Collection<int, OrganizationRelationship>
     */
    #[Computed]
    public function expiringContacts(): Collection
    {
        return app(ListExpiringContacts::class)->handle($this->requireOrganization(), $this->range);
    }

    /**
     * @return Collection<int, Person>
     */
    #[Computed]
    public function recentMembers(): Collection
    {
        return app(ListRecentMembers::class)->handle($this->requireOrganization(), $this->range);
    }

    public function render(): View
    {
        $timezone = $this->timezone();

        /** @disregard P1013 */
        return view('livewire.dashboard', [
            'organization' => $this->currentOrganization(),
            'today' => CarbonImmutable::now($timezone)->toDateString(),
            'timezone' => $timezone,
        ])->title(__('Home'));
    }

    private function timezone(): string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->timezoneOrDefault() : 'UTC';
    }
}
