<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Enums\AuditEvent;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\User;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read Collection<int, Person> $actors
 */
class Audit extends Component
{
    use HasCurrentOrganization, WithPagination;

    public ?string $event = null;

    public ?int $actor = null;

    /**
     * @var array{start: ?string, end: ?string}
     */
    public array $range = ['start' => null, 'end' => null];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        abort_if($this->currentOrganization() === null, 404);

        $this->authorize('viewAudit', $this->requireOrganization());

        $this->range = DateRange::defaultWeek($this->timezone());
    }

    /**
     * Reset to the first page when the event filter changes.
     */
    public function updatedEvent(): void
    {
        if ($this->event !== null && AuditEvent::tryFrom($this->event) === null) {
            $this->event = null;
        }

        unset($this->logs);

        $this->resetPage();
    }

    /**
     * Reset to the first page when the actor filter changes.
     */
    public function updatedActor(): void
    {
        if ($this->actor !== null && ! $this->actors->contains(
            fn (Person $member): bool => $member->user_id === $this->actor,
        )) {
            $this->actor = null;
        }

        unset($this->logs);

        $this->resetPage();
    }

    /**
     * Normalize the date range and reset to the first page.
     */
    public function updatedRange(): void
    {
        $this->range = DateRange::normalize($this->range, $this->timezone());

        unset($this->logs);

        $this->resetPage();
    }

    /**
     * The current organization's audit entries, newest first.
     *
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        $this->authorize('viewAudit', $this->requireOrganization());

        $timezone = $this->timezone();
        $start = $this->range['start'] ?? null;
        $end = $this->range['end'] ?? null;

        $query = AuditLog::query()
            ->where('organization_id', $this->requireOrganization()->getKey())
            ->with(['actor.person', 'subject'])
            ->when($this->event !== null, fn ($query) => $query->where('event', $this->event))
            ->when($this->actor !== null, fn ($query) => $query->where('actor_id', $this->actor));

        if ($start !== null) {
            $query->where('created_at', '>=', CarbonImmutable::parse($start, $timezone)->startOfDay()->utc());
        }

        if ($end !== null) {
            $query->where('created_at', '<=', CarbonImmutable::parse($end, $timezone)->endOfDay()->utc());
        }

        return $query->latest('id')->paginate(20);
    }

    /**
     * Organization members who can appear as an audit actor, current user first.
     *
     * @return Collection<int, Person>
     */
    #[Computed]
    public function actors(): Collection
    {
        $current = Auth::user();
        $currentId = $current instanceof User ? (int) $current->getKey() : null;

        $members = $this->requireOrganization()->members()
            ->with('user')
            ->whereNotNull('people.user_id')
            ->orderBy('people.name')
            ->get();

        $currentPerson = $current instanceof User ? $current->person : null;

        if ($currentPerson instanceof Person
            && ! $members->contains(fn (Person $member): bool => $member->user_id === $currentId)) {
            $currentPerson->loadMissing('user');
            $members->push($currentPerson);
        }

        return $members
            ->sortBy(fn (Person $member): int => $member->user_id === $currentId ? 0 : 1)
            ->values();
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.audit', [
            'events' => AuditEvent::cases(),
            'today' => CarbonImmutable::now($this->timezone())->toDateString(),
            'timezone' => $this->timezone(),
        ])->title(__('Audit log'));
    }

    private function timezone(): string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->timezoneOrDefault() : 'UTC';
    }
}
