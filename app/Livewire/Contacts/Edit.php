<?php

declare(strict_types=1);

namespace App\Livewire\Contacts;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\FindContact;
use App\Actions\Contacts\UpdateContact;
use App\Concerns\ProfileValidationRules;
use App\Enums\Gender;
use App\Enums\OrganizationRelationshipType;
use App\Livewire\Concerns\HasCurrentOrganization;
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
class Edit extends Component
{
    use HasCurrentOrganization, ProfileValidationRules, WithFileUploads;

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

    public ?string $start_date = null;

    public ?string $finish_date = null;

    /**
     * @var mixed
     */
    public $photo = null;

    #[Locked]
    public ?string $photo_url = null;

    public ?string $selectedEntity = null;

    public ?string $reusedType = null;

    public ?string $reusedName = null;

    #[Locked]
    public ?int $reusedContactId = null;

    public string $contact_name = '';

    public ?string $contact_gender = null;

    public string $contact_summary = '';

    #[Locked]
    public bool $genderEnabled = false;

    #[On('create-contact')]
    public function openCreate(): void
    {
        Gate::authorize('createContact', Organization::class);

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
            'start_date',
            'finish_date',
            'photo',
            'photo_url',
            'selectedEntity',
            'reusedType',
            'reusedName',
            'reusedContactId',
            'contact_name',
            'contact_gender',
            'contact_summary',
        );

        $this->type = 'individual';
        $this->role = OrganizationRelationshipType::Customer->value;
        $this->start_date = now()->format('Y-m-d');

        $this->resetValidation();
        $this->showModal = true;
    }

    #[On('edit-contact')]
    public function openEdit(int $contactId): void
    {
        $organization = $this->requireOrganization();

        $contact = app(FindContact::class)->byId($organization, $contactId);

        Gate::authorize('updateContact', $contact->stakeholder);

        $this->genderEnabled = ContactGender::enabled($organization);

        $this->fillFrom($contact);
        $this->showModal = true;
    }

    public function updatedSelectedEntity(?string $reference): void
    {
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
        $this->reusedContactId = OrganizationRelationship::query()
            ->external()
            ->forOrganization($organization)
            ->expired()
            ->whereStakeholder($entity)
            ->value('id');
        $this->reusedType = $entity instanceof Organization ? 'organization' : 'individual';
        $this->reusedName = $entity->name;
        $this->type = $this->reusedType;
        $this->name = $entity->name;
        $this->summary = (string) $entity->summary;
        $this->gender = $entity instanceof Person ? $entity->gender?->value : null;
        $this->photo = null;
        $this->photo_url = $entity->getRawOriginal($entity instanceof Organization ? 'logo_url' : 'photo_url');

        if ($entity instanceof Organization) {
            $contactPerson = $entity->primaryContact;
            $this->contact_name = (string) $contactPerson?->name;
            $this->contact_gender = $contactPerson?->gender?->value;
            $this->contact_summary = (string) $contactPerson?->summary;
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
        $this->photo_url = null;
    }

    public function save(CreateContact $createContact, UpdateContact $updateContact): void
    {
        $organization = $this->requireOrganization();

        $this->genderEnabled = ContactGender::enabled($organization);

        if ($this->contactId !== null) {
            abort_if($this->contact === null, 404);
            Gate::authorize('updateContact', $this->contact->stakeholder);
        } else {
            Gate::authorize('createContact', Organization::class);
        }

        $sharedFieldsLocked = $this->sharedFieldsLocked;

        if (! $sharedFieldsLocked) {
            $this->validate([
                'photo' => ['nullable', 'image', 'max:2048'],
            ]);
        }

        $this->validate($this->rules($organization, $sharedFieldsLocked));

        $data = [
            'name' => $this->name,
            'summary' => $this->summary !== '' ? $this->summary : null,
            'code' => $this->code !== '' ? $this->code : null,
            'role' => OrganizationRelationshipType::from($this->role),
            'start_date' => $this->start_date,
            'finish_date' => $this->finish_date !== '' ? $this->finish_date : null,
            'contact_name' => $this->contact_name !== '' ? $this->contact_name : null,
            'contact_summary' => $this->contact_summary !== '' ? $this->contact_summary : null,
        ];

        if ($this->genderEnabled) {
            $data['gender'] = $this->gender;
            $data['contact_gender'] = $this->contact_gender;
        }

        if ($this->contactId === null) {
            $data['type'] = $this->isOrganizationType ? 'organization' : 'individual';
            $data['reference'] = $this->reusing ? $this->selectedEntity : null;
        }

        try {
            $actor = Auth::user();

            if ($this->contactId !== null) {
                $updateContact->handle(
                    $organization,
                    $this->contactId,
                    $data,
                    $this->photo instanceof UploadedFile ? $this->photo : null,
                    $this->photo_url === null,
                    $sharedFieldsLocked,
                    $actor,
                );

                $message = __('Contact updated.');
            } else {
                $createContact->handle(
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
        $this->dispatch('contact-updated');

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
        return \is_string($this->selectedEntity) && $this->selectedEntity !== '';
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
        return $this->photo_url ? route('uploads', ['path' => $this->photo_url]) : null;
    }

    public function render(): View
    {
        return view('livewire.contacts.edit');
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
            'start_date' => ['required', 'date'],
            'finish_date' => ['nullable', 'date', 'after_or_equal:start_date'],
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
            $rules['contact_name'] = ['required', 'string', 'max:255'];
            $rules['contact_summary'] = ['nullable', 'string', 'max:255'];

            if ($this->genderEnabled) {
                $rules['contact_gender'] = ['required', Rule::enum(Gender::class)];
            }
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
        $this->start_date = $contact->start_date?->format('Y-m-d');
        $this->finish_date = $contact->finish_date?->format('Y-m-d');
        $this->photo = null;
        $this->photo_url = $stakeholder->getRawOriginal($stakeholder instanceof Organization ? 'logo_url' : 'photo_url');
        $this->selectedEntity = null;
        $this->reusedType = null;
        $this->reusedName = null;
        $this->contact_gender = null;
        $this->contact_name = '';
        $this->contact_summary = '';

        if ($stakeholder instanceof Organization) {
            $contactPerson = $stakeholder->primaryContact;
            $this->contact_name = (string) $contactPerson?->name;
            $this->contact_gender = $contactPerson?->gender?->value;
            $this->contact_summary = (string) $contactPerson?->summary;
        }

        $this->resetValidation();
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
        $this->photo_url = null;
        $this->contact_name = '';
        $this->contact_gender = null;
        $this->contact_summary = '';
        $this->type = 'individual';

        if ($resetName) {
            $this->name = '';
        }
    }
}
