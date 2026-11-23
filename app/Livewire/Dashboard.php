<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
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
        return $this->membershipQuery()->count();
    }

    /**
     * External contacts whose relationship started inside the range.
     */
    #[Computed]
    public function newContacts(): int
    {
        return $this->contactQuery()->count();
    }

    /**
     * Active external contacts finishing inside the range.
     */
    #[Computed]
    public function expiringCount(): int
    {
        return $this->expiringQuery()->count();
    }

    #[Computed]
    public function unreadNotifications(): int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return 0;
        }

        $timezone = $this->timezone();

        return $user->unreadNotifications()
            ->where('created_at', '>=', CarbonImmutable::parse($this->range['start'], $timezone)->startOfDay()->utc())
            ->where('created_at', '<=', CarbonImmutable::parse($this->range['end'], $timezone)->endOfDay()->utc())
            ->count();
    }

    /**
     * @return Collection<int, OrganizationRelationship>
     */
    #[Computed]
    public function expiringContacts(): Collection
    {
        return $this->expiringQuery()
            ->with(['stakeholder' => fn (MorphTo $morphTo) => $morphTo->withTrashed()])
            ->orderBy('finish_date')
            ->limit(5)
            ->get();
    }

    /**
     * @return Collection<int, Person>
     */
    #[Computed]
    public function recentMembers(): Collection
    {
        return $this->membershipQuery()
            ->with('user')
            ->orderByDesc('organization_relationships.start_date')
            ->limit(5)
            ->get();
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

    /**
     * @return MorphToMany<Person, Organization, OrganizationRelationship>
     */
    private function membershipQuery(): MorphToMany
    {
        return $this->requireOrganization()
            ->members()
            ->whereDate('organization_relationships.start_date', '>=', $this->range['start'])
            ->whereDate('organization_relationships.start_date', '<=', $this->range['end'])
            ->where(fn ($query) => $query
                ->whereNull('organization_relationships.finish_date')
                ->orWhereDate('organization_relationships.finish_date', '>=', today()));
    }

    /**
     * @return Builder<OrganizationRelationship>
     */
    private function contactQuery(): Builder
    {
        return OrganizationRelationship::query()
            ->forOrganization($this->requireOrganization())
            ->external()
            ->whereDate('start_date', '>=', $this->range['start'])
            ->whereDate('start_date', '<=', $this->range['end']);
    }

    /**
     * @return Builder<OrganizationRelationship>
     */
    private function expiringQuery(): Builder
    {
        return OrganizationRelationship::query()
            ->forOrganization($this->requireOrganization())
            ->external()
            ->notExpired()
            ->whereDate('finish_date', '>=', $this->range['start'])
            ->whereDate('finish_date', '<=', $this->range['end'])
            ->where(fn ($query) => $query
                ->whereNull('start_date')
                ->orWhereDate('start_date', '<=', today()));
    }

    private function timezone(): string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->timezoneOrDefault() : 'UTC';
    }
}
