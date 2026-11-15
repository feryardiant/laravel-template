<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Enums\AuditEvent;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Audit extends Component
{
    use HasCurrentOrganization, WithPagination;

    public ?string $event = null;

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

        $this->authorizeManager();

        $this->range = $this->defaultRange();
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
     * Normalize the date range and reset to the first page.
     */
    public function updatedRange(): void
    {
        $this->range = $this->normalizeRange($this->range);

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
        $this->authorizeManager();

        $timezone = $this->userTimezone();
        $start = $this->range['start'] ?? null;
        $end = $this->range['end'] ?? null;

        $query = AuditLog::query()
            ->where('organization_id', $this->requireOrganization()->getKey())
            ->with(['actor.person', 'subject'])
            ->when($this->event !== null, fn ($query) => $query->where('event', $this->event));

        if ($start !== null) {
            $query->where('created_at', '>=', CarbonImmutable::parse($start, $timezone)->startOfDay()->utc());
        }

        if ($end !== null) {
            $query->where('created_at', '<=', CarbonImmutable::parse($end, $timezone)->endOfDay()->utc());
        }

        return $query->latest('id')->paginate(20);
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.audit', [
            'events' => AuditEvent::cases(),
            'today' => CarbonImmutable::now($this->userTimezone())->toDateString(),
            'timezone' => $this->userTimezone(),
        ])->title(__('Audit log'));
    }

    /**
     * @return array{start: string, end: string}
     */
    private function defaultRange(): array
    {
        $today = CarbonImmutable::now($this->userTimezone());

        return [
            'start' => $today->startOfWeek(CarbonImmutable::SUNDAY)->toDateString(),
            'end' => $today->endOfWeek(CarbonImmutable::SATURDAY)->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $range
     * @return array{start: string, end: string}
     */
    private function normalizeRange(array $range): array
    {
        $start = $this->dateKey($range['start'] ?? null);
        $end = $this->dateKey($range['end'] ?? null);

        if ($start === null || $end === null) {
            return $this->defaultRange();
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return ['start' => $start, 'end' => $end];
    }

    private function dateKey(mixed $value): ?string
    {
        if (! \is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }

    private function userTimezone(): string
    {
        $user = Auth::user();

        return $user instanceof User && $user->timezone !== null ? $user->timezone : 'UTC';
    }
}
