<?php

declare(strict_types=1);

namespace App\Livewire\Contacts;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\FindContact;
use App\Actions\Contacts\UpdateContact;
use App\Concerns\ProfileValidationRules;
use App\Enums\ContactDetailType;
use App\Enums\Gender;
use App\Enums\OrganizationRelationshipType;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Livewire\Concerns\ManagesAddress;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Support\Feature\ContactGender;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * @property-read array<int, OrganizationRelationshipType> $roles
 * @property-read bool $reusing
 * @property-read bool $isOrganizationType
 * @property-read bool $sharedFieldsLocked
 * @property-read ?string $photoPreviewUrl
 */
class Form extends Component
{
    use HasCurrentOrganization, ManagesAddress, ProfileValidationRules, WithFileUploads;

    public bool $showModal = false;

    public ?int $contactId = null;

    #[Locked]
    public ?OrganizationRelationship $contact = null;

    public string $type = 'individual';

    public string $name = '';

    public string $summary = '';

    public ?string $gender = null;

    public string $code = '';

    public string $role = 'customer';

    public ?string $startDate = null;

    public ?string $finishDate = null;

    /**
     * @var mixed
     */
    public $photo = null;

    #[Locked]
    public ?string $photoUrl = null;

    public ?string $selectedEntity = null;

    public ?string $reusedType = null;

    public ?string $reusedName = null;

    #[Locked]
    public ?int $reusedContactId = null;

    public string $contactName = '';

    public ?string $contactGender = null;

    public string $contactSummary = '';

    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $contactPersonEmail = '';

    public string $contactPersonPhone = '';

    /**
     * The detail values loaded when the form opened; only changes are submitted.
     *
     * @var array<string, string>
     */
    #[Locked]
    public array $originalDetails = [];

    /**
     * The profile values loaded when the form opened.
     *
     * @var array<string, string|null>
     */
    #[Locked]
    public array $originalProfile = [];

    /**
     * The relationship values loaded when the form opened.
     *
     * @var array<string, string|null>
     */
    #[Locked]
    public array $originalRelationship = [];

    #[Locked]
    public ?string $originalPhotoUrl = null;

    /**
     * The address payload loaded when the form opened.
     *
     * @var array<string, string|null>|null
     */
    #[Locked]
    public ?array $originalAddress = null;

    #[Locked]
    public bool $genderEnabled = false;

    #[On('create-contact')]
    public function openCreate(?string $role = null): void
    {
        Gate::authorize('createContact', $this->requireOrganization());

        $this->genderEnabled = ContactGender::enabled($this->currentOrganization());

        $this->reset(
            'contactId',
            'contact',
            'type',
            'name',
            'summary',
            'gender',
            'code',
            'role',
            'startDate',
            'finishDate',
            'photo',
            'photoUrl',
            'selectedEntity',
            'reusedType',
            'reusedName',
            'reusedContactId',
            'contactName',
            'contactGender',
            'contactSummary',
            'contactEmail',
            'contactPhone',
            'contactPersonEmail',
            'contactPersonPhone',
            'originalDetails',
            'originalProfile',
            'originalRelationship',
            'originalPhotoUrl',
            'originalAddress',
        );

        $this->type = 'individual';
        $this->role = $role !== null && OrganizationRelationshipType::tryFrom($role)?->isExternal()
            ? $role
            : OrganizationRelationshipType::Customer->value;
        $this->startDate = now()->format('Y-m-d');
        $this->resetAddress();

        $this->resetValidation();
        $this->showModal = true;
    }

    #[On('edit-contact')]
    public function openEdit(int $contactId): void
    {
        $organization = $this->requireOrganization();

        $contact = app(FindContact::class)->byId($organization, $contactId);

        Gate::authorize('updateContact', [$contact->stakeholder, $organization]);

        $this->genderEnabled = ContactGender::enabled($organization);

        $this->fillFrom($contact);
        $this->showModal = true;
    }

    public function updatedSelectedEntity(?string $reference): void
    {
        // Reuse only applies while creating; an edited contact keeps its own values.
        if ($this->contactId !== null) {
            return;
        }

        if ($reference === null || $reference === '') {
            if ($this->reusedName !== null || $this->reusedType !== null) {
                $this->resetReuse();
            }

            return;
        }

        $organization = $this->requireOrganization();

        try {
            $entity = app(FindContact::class)->byReference($organization, $reference);
        } catch (Throwable) {
            $this->resetReuse();

            Flux::toast(variant: 'danger', text: __('Unable to load the contact.'));

            return;
        }

        $this->resetReuse(false);

        $this->selectedEntity = $reference;
        $this->resetAddress();
        $this->reusedContactId = app(FindContact::class)
            ->expiredRelationshipFor($organization, $entity)
            ?->getKey();
        $this->reusedType = $entity instanceof Organization ? 'organization' : 'individual';
        $this->reusedName = $entity->name;
        $this->type = $this->reusedType;
        $this->name = $entity->name;
        $this->summary = (string) $entity->summary;
        $this->gender = $entity instanceof Person ? $entity->gender?->value : null;
        $this->photo = null;
        $this->photoUrl = $entity->getRawOriginal($entity instanceof Organization ? 'logo_url' : 'photo_url');

        if ($entity instanceof Organization) {
            $contactPerson = $entity->primaryContact;
            $this->contactName = (string) $contactPerson?->name;
            $this->contactGender = $contactPerson?->gender?->value;
            $this->contactSummary = (string) $contactPerson?->summary;
        }

        $this->resetValidation();
    }

    public function updatedName(): void
    {
        if ($this->contactId !== null || ! $this->reusing) {
            return;
        }

        if (trim($this->name) === $this->reusedName) {
            return;
        }

        $this->resetReuse();
    }

    public function clearPhoto(): void
    {
        $this->photo = null;
        $this->photoUrl = null;
    }

    public function save(CreateContact $createContact, UpdateContact $updateContact): void
    {
        $organization = $this->requireOrganization();

        $this->genderEnabled = ContactGender::enabled($organization);

        if ($this->contactId !== null) {
            abort_if($this->contact === null, 404);
            Gate::authorize('updateContact', [$this->contact->stakeholder, $organization]);
        } else {
            Gate::authorize('createContact', $organization);
        }

        $sharedFieldsLocked = $this->sharedFieldsLocked;

        if (! $sharedFieldsLocked) {
            $this->validate([
                'photo' => ['nullable', 'image', 'max:2048'],
            ]);
        }

        $this->validate($this->rules($organization, $sharedFieldsLocked));

        $profile = $this->changedProfile();

        if (! $this->genderEnabled) {
            unset($profile['gender'], $profile['contact_gender']);
        }

        $data = [
            ...$profile,
            ...$this->changedRelationship(),
            ...$this->changedContactDetails(),
        ];

        $address = $this->addressPayload();

        if ($address !== $this->originalAddress) {
            $data['address'] = $address;
        }

        if ($this->contactId === null) {
            $data['type'] = $this->isOrganizationType ? 'organization' : 'individual';
            $data['reference'] = $this->reusing ? $this->selectedEntity : null;
        }

        try {
            $actor = Auth::user();
            $entity = null;

            if ($this->contactId !== null) {
                $updateContact->handle(
                    $organization,
                    $this->contactId,
                    $data,
                    $this->photo instanceof UploadedFile ? $this->photo : null,
                    $this->photoUrl === null && $this->originalPhotoUrl !== null,
                    $sharedFieldsLocked,
                    $actor,
                );

                $message = __('Contact updated.');
            } else {
                $entity = $createContact->handle(
                    $organization,
                    $data,
                    $this->photo instanceof UploadedFile ? $this->photo : null,
                    $actor,
                );

                $message = __('Contact created.');
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            Flux::toast(variant: 'danger', text: __('Failed to save the contact.'));

            return;
        }

        $this->showModal = false;
        $this->rememberContactDetails();
        $this->rememberBaselines();
        $this->dispatch('contact-updated', contactId: $entity?->getKey());

        Flux::toast(variant: 'success', text: $message);
    }

    /**
     * @return array<int, OrganizationRelationshipType>
     */
    #[Computed]
    public function roles(): array
    {
        return OrganizationRelationshipType::externals();
    }

    #[Computed]
    public function reusing(): bool
    {
        return $this->contactId === null
            && \is_string($this->selectedEntity)
            && $this->selectedEntity !== '';
    }

    #[Computed]
    public function isOrganizationType(): bool
    {
        if ($this->contactId !== null) {
            return $this->contact?->isOrganization() ?? false;
        }

        if ($this->reusing) {
            return $this->reusedType === 'organization';
        }

        return $this->type === 'organization';
    }

    #[Computed]
    public function sharedFieldsLocked(): bool
    {
        return $this->reusing || ($this->contactId !== null && ($this->contact?->profileLockedFor($this->requireOrganization()) ?? true));
    }

    #[Computed]
    public function photoPreviewUrl(): ?string
    {
        return $this->photoUrl ? route('uploads', ['path' => $this->photoUrl]) : null;
    }

    public function render(): View
    {
        return view('livewire.contacts.form');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(Organization $organization, bool $sharedFieldsLocked): array
    {
        $code = Rule::unique('organization_relationships', 'code')
            ->where('organization_id', $organization->getKey());

        if ($this->contactId !== null) {
            $code->ignore($this->contactId);
        } elseif ($this->reusedContactId !== null) {
            $code->ignore($this->reusedContactId);
        }

        $rules = [
            'code' => ['nullable', 'string', 'max:255', $code],
            'role' => [
                'required',
                Rule::enum(OrganizationRelationshipType::class),
                Rule::in(array_map(fn (OrganizationRelationshipType $type): string => $type->value, OrganizationRelationshipType::externals())),
            ],
            'startDate' => ['required', 'date'],
            'finishDate' => ['nullable', 'date', 'after_or_equal:startDate'],
        ];

        if (! $sharedFieldsLocked) {
            $rules['name'] = $this->nameRules();
            $rules['summary'] = ['nullable', 'string', 'max:255'];
        }

        if ($this->contactId === null) {
            $rules['type'] = ['required', Rule::in(['individual', 'organization'])];
        }

        if (! $this->isOrganizationType && ! $sharedFieldsLocked && $this->genderEnabled) {
            $rules['gender'] = $this->contactId === null
                ? ['required', Rule::enum(Gender::class)]
                : ['nullable', Rule::enum(Gender::class)];
        }

        if ($this->isOrganizationType && ! $sharedFieldsLocked) {
            $rules['contactName'] = ['required', 'string', 'max:255'];
            $rules['contactSummary'] = ['nullable', 'string', 'max:255'];

            if ($this->genderEnabled) {
                $rules['contactGender'] = ['required', Rule::enum(Gender::class)];
            }
        }

        if (! $sharedFieldsLocked) {
            $rules = [...$rules, ...$this->addressRules()];
        }

        // Communication records stay editable even when the shared profile is locked.
        if (! $this->reusing) {
            $rules = [...$rules, ...$this->contactDetailRules()];
        }

        if ($this->isOrganizationType && ! $this->reusing) {
            $rules['contactPersonEmail'] = ['nullable', 'string', 'email', 'max:255'];
            $rules['contactPersonPhone'] = ['nullable', 'string', 'max:32'];
        }

        return $rules;
    }

    private function fillFrom(OrganizationRelationship $contact): void
    {
        $stakeholder = $contact->stakeholder;

        abort_if($stakeholder === null, 404);

        $this->contact = $contact;
        $this->contactId = $contact->getKey();
        $this->type = $contact->isOrganization() ? 'organization' : 'individual';
        $this->name = $stakeholder->name;
        $this->summary = (string) $stakeholder->summary;
        $this->gender = $stakeholder instanceof Person ? $stakeholder->gender?->value : null;
        $this->code = (string) $contact->code;
        $role = $contact->type;
        $this->role = ($role instanceof OrganizationRelationshipType ? $role : OrganizationRelationshipType::Customer)->value;
        $this->startDate = $contact->start_date?->format('Y-m-d');
        $this->finishDate = $contact->finish_date?->format('Y-m-d');
        $this->photo = null;
        $this->photoUrl = $stakeholder->getRawOriginal($stakeholder instanceof Organization ? 'logo_url' : 'photo_url');
        $this->contactEmail = (string) $stakeholder->contactDetailValue(ContactDetailType::Email);
        $this->contactPhone = (string) $stakeholder->contactDetailValue(ContactDetailType::Phone);
        $this->selectedEntity = null;
        $this->reusedType = null;
        $this->reusedName = null;
        $this->contactGender = null;
        $this->contactName = '';
        $this->contactSummary = '';
        $this->contactPersonEmail = '';
        $this->contactPersonPhone = '';

        $this->fillAddress($stakeholder instanceof Organization
            ? $stakeholder->address()->first()
            : $stakeholder->addresses()->first());

        if ($stakeholder instanceof Organization) {
            $contactPerson = $stakeholder->primaryContact;
            $this->contactName = (string) $contactPerson?->name;
            $this->contactGender = $contactPerson?->gender?->value;
            $this->contactSummary = (string) $contactPerson?->summary;
            $this->contactPersonEmail = (string) $contactPerson?->contactDetailValue(ContactDetailType::Email);
            $this->contactPersonPhone = (string) $contactPerson?->contactDetailValue(ContactDetailType::Phone);
        }

        $this->originalDetails = $this->contactDetailValues();
        $this->rememberBaselines();
        $this->resetValidation();
    }

    /**
     * The detail values currently held by the form.
     *
     * @return array<string, string>
     */
    private function contactDetailValues(): array
    {
        return [
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'contact_person_email' => $this->contactPersonEmail,
            'contact_person_phone' => $this->contactPersonPhone,
        ];
    }

    /**
     * The detail values the editor changed, so untouched ones keep another save.
     *
     * @return array<string, string>
     */
    private function changedContactDetails(): array
    {
        return array_diff_assoc($this->contactDetailValues(), $this->originalDetails);
    }

    private function rememberContactDetails(): void
    {
        $this->originalDetails = $this->contactDetailValues();
    }

    /**
     * The profile values currently held by the form.
     *
     * @return array<string, string|null>
     */
    private function profileValues(): array
    {
        return [
            'name' => $this->name,
            'summary' => $this->summary !== '' ? $this->summary : null,
            'gender' => $this->gender,
            'contact_name' => $this->contactName !== '' ? $this->contactName : null,
            'contact_summary' => $this->contactSummary !== '' ? $this->contactSummary : null,
            'contact_gender' => $this->contactGender,
        ];
    }

    /**
     * The profile values the editor changed.
     *
     * @return array<string, string|null>
     */
    private function changedProfile(): array
    {
        return array_diff_assoc($this->profileValues(), $this->originalProfile);
    }

    /**
     * The relationship values currently held by the form.
     *
     * @return array<string, string|null>
     */
    private function relationshipValues(): array
    {
        return [
            'code' => $this->code !== '' ? $this->code : null,
            'role' => $this->role,
            'start_date' => $this->startDate,
            'finish_date' => $this->finishDate !== '' ? $this->finishDate : null,
        ];
    }

    /**
     * The relationship values the editor changed.
     *
     * @return array<string, string|null>
     */
    private function changedRelationship(): array
    {
        return array_diff_assoc($this->relationshipValues(), $this->originalRelationship);
    }

    private function rememberBaselines(): void
    {
        $this->originalProfile = $this->profileValues();
        $this->originalRelationship = $this->relationshipValues();
        $this->originalPhotoUrl = $this->photoUrl;
        $this->originalAddress = $this->addressPayload();
    }

    private function resetReuse(bool $resetName = false): void
    {
        $this->selectedEntity = null;
        $this->reusedType = null;
        $this->reusedName = null;
        $this->reusedContactId = null;
        $this->summary = '';
        $this->gender = null;
        $this->photo = null;
        $this->photoUrl = null;
        $this->contactName = '';
        $this->contactGender = null;
        $this->contactSummary = '';
        $this->contactEmail = '';
        $this->contactPhone = '';
        $this->contactPersonEmail = '';
        $this->contactPersonPhone = '';
        $this->originalDetails = [];
        $this->originalProfile = [];
        $this->originalRelationship = [];
        $this->originalPhotoUrl = null;
        $this->originalAddress = null;
        $this->type = 'individual';
        $this->resetAddress();

        if ($resetName) {
            $this->name = '';
        }
    }
}
