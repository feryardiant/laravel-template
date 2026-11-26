<?php

declare(strict_types=1);

namespace App\Livewire\Contacts;

use App\Enums\ContactDetailType;
use App\Enums\Feature;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\OrganizationRelationshipPeriod;
use App\Repositories\ContactRepository;
use App\Repositories\RelationshipRepository;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Collection<int, OrganizationRelationshipPeriod> $history
 */
class View extends Component
{
    use HasCurrentOrganization;

    #[Locked]
    public OrganizationRelationship $contact;

    public function mount(string $stakeholder): void
    {
        $organization = $this->requireOrganization();

        $this->contact = app(ContactRepository::class)->findByUuid($organization, $stakeholder);

        Gate::authorize('viewContact', [$this->contact->stakeholder, $organization]);
    }

    public function render(): ViewContract
    {
        $stakeholder = $this->contact->stakeholder;
        $organization = $this->requireOrganization();

        abort_unless((int) $this->contact->organization_id === (int) $organization->getKey(), 404);

        Gate::authorize('viewContact', [$stakeholder, $organization]);

        $contactPerson = $stakeholder instanceof Organization && ! $stakeholder->trashed()
            ? $stakeholder->primaryContact
            : null;

        /** @disregard P1013 */
        return view('livewire.contacts.view', [
            'stakeholder' => $stakeholder,
            'isOrganization' => $this->contact->is_organization,
            'contactPerson' => $contactPerson,
            'contactEmail' => $stakeholder->contactDetailValue(ContactDetailType::Email),
            'contactPhone' => $stakeholder->contactDetailValue(ContactDetailType::Phone),
            'contactPersonEmail' => $contactPerson?->contactDetailValue(ContactDetailType::Email),
            'contactPersonPhone' => $contactPerson?->contactDetailValue(ContactDetailType::Phone),
            'showGender' => Feature::ContactGender->enabled($this->currentOrganization()),
        ])->title(__('Contact details'));
    }

    /**
     * The relationship's full stint history.
     *
     * @return Collection<int, OrganizationRelationshipPeriod>
     */
    #[Computed]
    public function history(): Collection
    {
        return app(RelationshipRepository::class)->getForContact($this->contact);
    }
}
