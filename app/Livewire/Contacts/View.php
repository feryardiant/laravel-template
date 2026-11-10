<?php

declare(strict_types=1);

namespace App\Livewire\Contacts;

use App\Actions\Contacts\FindContact;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class View extends Component
{
    use HasCurrentOrganization;

    #[Locked]
    public OrganizationRelationship $contact;

    public function mount(string $stakeholder): void
    {
        $this->contact = app(FindContact::class)->byUuid($this->requireOrganization(), $stakeholder);

        Gate::authorize('viewContact', $this->contact->stakeholder);
    }

    public function render(): ViewContract
    {
        $stakeholder = $this->contact->stakeholder;

        abort_if($stakeholder === null, 404);

        /** @disregard P1013 */
        return view('livewire.contacts.view', [
            'stakeholder' => $stakeholder,
            'isOrganization' => $this->contact->isOrganization(),
            'contactPerson' => $stakeholder instanceof Organization ? $stakeholder->primaryContact : null,
        ])->title(__('Contact details'));
    }
}
