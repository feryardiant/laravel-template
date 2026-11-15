<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Enums\AuditEvent;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Audit extends Component
{
    use HasCurrentOrganization, WithPagination;

    public ?string $event = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        abort_if($this->currentOrganization() === null, 404);

        $this->authorizeManager();
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
     * The current organization's audit entries, newest first.
     *
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        return AuditLog::query()
            ->where('organization_id', $this->requireOrganization()->getKey())
            ->with(['actor', 'subject'])
            ->when($this->event !== null, fn ($query) => $query->where('event', $this->event))
            ->latest('id')
            ->paginate(20);
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.audit', [
            'events' => AuditEvent::cases(),
        ])->title(__('Audit log'));
    }
}
