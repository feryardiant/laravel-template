<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Repositories\ContactRepository;
use App\Repositories\MembershipRepository;
use App\Repositories\NotificationRepository;
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
class Index extends Component
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

    public function render(): View
    {
        $timezone = $this->timezone();

        /** @disregard P1013 */
        return view('livewire.dashboard.index', [
            'organization' => $this->currentOrganization(),
            'today' => CarbonImmutable::now($timezone)->toDateString(),
            'timezone' => $timezone,
        ])->title(__('Home'));
    }

    /**
     * Members whose membership started inside the range and is still active.
     */
    #[Computed]
    public function newMembers(): int
    {
        return app(MembershipRepository::class)->countRecent($this->requireOrganization(), $this->range);
    }

    /**
     * External contacts whose relationship started inside the range.
     */
    #[Computed]
    public function newContacts(): int
    {
        return app(ContactRepository::class)->countRecent($this->requireOrganization(), $this->range);
    }

    /**
     * Active external contacts finishing inside the range.
     */
    #[Computed]
    public function expiringCount(): int
    {
        return app(ContactRepository::class)->countExpiring($this->requireOrganization(), $this->range);
    }

    /**
     * Unread notifications received inside the selected range.
     */
    #[Computed]
    public function unreadNotifications(): int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return 0;
        }

        return app(NotificationRepository::class)->countUnread($user, $this->range, $this->timezone());
    }

    /**
     * @return Collection<int, OrganizationRelationship>
     */
    #[Computed]
    public function expiringContacts(): Collection
    {
        return app(ContactRepository::class)->getExpiring($this->requireOrganization(), $this->range);
    }

    /**
     * @return Collection<int, Person>
     */
    #[Computed]
    public function recentMembers(): Collection
    {
        return app(MembershipRepository::class)->getRecent($this->requireOrganization(), $this->range);
    }

    /**
     * Normalize the date range.
     */
    public function updatedRange(): void
    {
        $this->range = DateRange::normalize($this->range, $this->timezone());
    }

    /**
     * The signed-in user's timezone, falling back to UTC.
     */
    private function timezone(): string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->timezoneOrDefault() : 'UTC';
    }
}
