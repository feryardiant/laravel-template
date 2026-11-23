<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\Memberships\CreateMember;
use App\Actions\Memberships\FindMember;
use App\Actions\Memberships\UpdateMember;
use App\Concerns\ProfileValidationRules;
use App\Enums\ContactDetailType;
use App\Enums\Gender;
use App\Enums\Role;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Livewire\Concerns\ManagesAddress;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Support\Feature\ContactGender;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * @property-read array<int, Role> $roles
 * @property-read bool $canEditProfile
 * @property-read bool $genderEditable
 * @property-read ?string $photoPreviewUrl
 */
class Form extends Component
{
    use HasCurrentOrganization, ManagesAddress, ProfileValidationRules, WithFileUploads;

    public ?Person $person = null;

    public bool $showModal = false;

    public string $name = '';

    public string $email = '';

    public string $contactEmail = '';

    public string $contactPhone = '';

    /**
     * The detail values loaded when the form opened; only changes are submitted.
     *
     * @var array<string, string>
     */
    #[Locked]
    public array $originalDetails = [];

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
    public ?int $originalRole = null;

    #[Locked]
    public string $originalEmail = '';

    #[Locked]
    public string $originalName = '';

    #[Locked]
    public bool $hasCredential = false;

    public bool $createCredential = false;

    public int $role = Role::Member->value;

    public ?string $gender = null;

    #[Locked]
    public bool $genderEnabled = false;

    /**
     * @var mixed
     */
    public $photo = null;

    #[Locked]
    public ?string $photoUrl = null;

    /**
     * Mount the component.
     */
    public function mount(?Person $person = null): void
    {
        if ($person instanceof Person) {
            $this->genderEnabled = ContactGender::enabled($this->currentOrganization());
            $this->fillFrom($person);
        }
    }

    public function render(): View
    {
        return view('livewire.members.form');
    }

    /**
     * Fill the form from the selected member.
     */
    #[On('edit-member')]
    public function editMember(int $personId): void
    {
        $organization = $this->requireOrganization();
        $person = app(FindMember::class)->inOrganization($organization, $personId, ['user']);

        $this->authorize('manageMember', [$person, $organization]);
        $this->genderEnabled = ContactGender::enabled($organization);
        $this->fillFrom($person);

        $this->showModal = true;
    }

    /**
     * Open the form for a new member.
     */
    #[On('create-member')]
    public function openCreate(): void
    {
        $this->authorize('manageMembers', $this->requireOrganization());

        $this->reset('person', 'name', 'email', 'contactEmail', 'contactPhone', 'originalDetails', 'originalPhotoUrl', 'originalAddress', 'originalRole', 'originalEmail', 'originalName', 'hasCredential', 'createCredential', 'gender', 'photo', 'photoUrl');

        $this->role = Role::Member->value;
        $this->genderEnabled = false;
        $this->resetAddress();
        $this->resetValidation();

        $this->showModal = true;
    }

    /**
     * @return array<int, Role>
     */
    #[Computed]
    public function roles(): array
    {
        return [Role::Admin, Role::Member];
    }

    /**
     * Determine whether the current organization controls this person's profile.
     */
    #[Computed]
    public function canEditProfile(): bool
    {
        $organization = $this->currentOrganization();

        // A new member is always editable; existing profiles only when this organization owns them.
        return $organization !== null && ($this->person === null || $this->person->isOwnedBy($organization));
    }

    /**
     * Gender can be set once.
     */
    #[Computed]
    public function genderEditable(): bool
    {
        return $this->canEditProfile && $this->person !== null && $this->person->gender === null;
    }

    #[Computed]
    public function photoPreviewUrl(): ?string
    {
        return $this->photoUrl ? route('uploads', ['path' => $this->photoUrl]) : null;
    }

    /**
     * Add a person to the current organization.
     */
    public function create(CreateMember $createMember): void
    {
        $organization = $this->requireOrganization();

        $this->authorize('manageMembers', $organization);

        $this->email = Str::lower($this->email);

        $this->validate([
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'email' => $this->createCredential
                ? $this->emailRules()
                : ['nullable', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::in([Role::Admin->value, Role::Member->value])],
            ...$this->contactDetailRules(),
            ...$this->addressRules(),
        ]);

        $role = Role::from((int) $validated['role']);

        $hasCredential = $this->createCredential;

        $createMember->handle(
            $organization,
            $validated['name'],
            $role,
            $hasCredential ? $validated['email'] : null,
            $this->photo instanceof UploadedFile ? $this->photo : null,
            Auth::user(),
            $this->addressPayload(),
            $validated['contactEmail'] ?? null,
            $validated['contactPhone'] ?? null,
        );

        $this->photo = null;
        $this->showModal = false;

        $this->dispatch('member-updated');

        Flux::toast(
            variant: 'success',
            text: $hasCredential
                ? __('Member added. A verification email has been sent.')
                : __('Member added.'),
        );
    }

    /**
     * Save the form, either creating a member or updating the selected one.
     */
    public function save(CreateMember $createMember, UpdateMember $updateMember): void
    {
        if ($this->person === null) {
            $this->create($createMember);

            return;
        }

        $this->update($updateMember);
    }

    /**
     * Update the selected member.
     */
    public function update(UpdateMember $updateMember): void
    {
        $organization = $this->requireOrganization();

        abort_if($this->person === null, 404);

        $this->person = app(FindMember::class)->inOrganization($organization, $this->person->getKey(), ['user']);
        $this->authorize('manageMember', [$this->person, $organization]);
        $this->email = Str::lower($this->email);

        $canEditProfile = $this->canEditProfile;

        if ($canEditProfile) {
            $this->validate([
                'photo' => ['nullable', 'image', 'max:2048'],
            ]);
        }

        $rules = [
            'role' => ['required', Rule::in([Role::Admin->value, Role::Member->value])],
            ...$this->contactDetailRules(),
        ];

        if ($canEditProfile) {
            $rules['name'] = $this->nameRules();
            $rules['email'] = $this->hasCredential
                ? ($this->email !== $this->originalEmail
                    ? $this->emailRules($this->person->user_id)
                    : ['required', 'string', 'email', 'max:255'])
                : ($this->createCredential ? $this->emailRules() : ['nullable', 'string', 'email', 'max:255']);

            if ($this->genderEnabled && $this->genderEditable) {
                $rules['gender'] = ['nullable', Rule::enum(Gender::class)];
            }

            $rules = [...$rules, ...$this->addressRules()];
        }

        $validated = $this->validate($rules);

        /** @var User|null $actor */
        $actor = Auth::user();

        abort_unless($actor instanceof User, 403);

        $result = $updateMember->handle(
            organization: $organization,
            person: $this->person,
            actor: $actor,
            data: [
                'name' => $this->name,
                'email' => $this->email,
                'role' => Role::from((int) $validated['role']),
                'gender' => $this->genderEnabled && $this->genderEditable
                    ? ($validated['gender'] ?? null)
                    : null,
                'address' => $this->addressPayload(),
                'details' => $this->changedContactDetails(),
                'create_credential' => $this->createCredential,
                'can_edit_profile' => $canEditProfile,
                'remove_photo' => $this->photoUrl === null && $this->originalPhotoUrl !== null,
            ],
            expected: [
                'name' => $this->originalName,
                'email' => $this->originalEmail,
                'role' => $this->originalRole ?? -1,
                'address' => $this->originalAddress,
                'details' => $this->originalDetails,
                'photo_url' => $this->originalPhotoUrl,
                'has_credential' => $this->hasCredential,
            ],
            photo: $this->photo instanceof UploadedFile ? $this->photo : null,
        );

        $this->person = $result['person'];
        $this->photo = null;
        $this->photoUrl = $result['photo_url'];

        $this->rememberContactDetails();
        $this->rememberBaselines($organization);

        if ($result['photo_removal_failed']) {
            Flux::toast(variant: 'danger', text: __('Unable to remove the :field. Please try again.', ['field' => __('Photo')]));

            // The rest of the edit committed; let the list reload.
            $this->dispatch('member-updated');

            return;
        }

        $this->showModal = false;

        $this->dispatch('member-updated');

        Flux::toast(
            variant: 'success',
            text: $result['email_changed'] || $result['credential_created']
                ? __('Member updated. A verification email has been sent to the new address.')
                : __('Member updated.'),
        );
    }

    /**
     * Clear the pending or stored member photo.
     */
    public function clearPhoto(): void
    {
        $this->photo = null;
        $this->photoUrl = null;
    }

    /**
     * Fill the form from the selected member.
     */
    private function fillFrom(Person $person): void
    {
        $this->person = $person->loadMissing('user');
        $this->name = $person->name;
        $this->photo = null;
        $this->photoUrl = $person->getRawOriginal('photo_url');
        $this->hasCredential = $person->user_id !== null;
        $this->createCredential = false;
        $this->email = $person->user_id === null ? '' : $person->user->email;
        $this->contactEmail = (string) $person->contactDetailValue(ContactDetailType::Email);
        $this->contactPhone = (string) $person->contactDetailValue(ContactDetailType::Phone);
        $this->gender = $person->gender?->value;
        $membership = $person->membershipIn($this->requireOrganization());
        $storedRole = $membership?->role;
        $this->role = ($storedRole ?? Role::Member)->value;
        $this->fillAddress($person->addresses()->first());
        $this->originalDetails = $this->contactDetailValues();
        $this->originalPhotoUrl = $this->photoUrl;
        $this->originalAddress = $this->addressPayload();
        $this->originalRole = (int) $this->role;
        $this->originalEmail = Str::lower($this->email);
        $this->originalName = $this->name;
    }

    /**
     * The detail values currently held by the form.
     *
     * @return array<string, string>
     */
    private function contactDetailValues(): array
    {
        return [
            ContactDetailType::Email->value => $this->contactEmail,
            ContactDetailType::Phone->value => $this->contactPhone,
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

    private function rememberBaselines(Organization $organization): void
    {
        $membership = $this->person?->membershipIn($organization);
        $storedRole = $membership?->role;
        $role = ($storedRole ?? Role::Member)->value;
        $email = Str::lower((string) $this->person?->user?->email);
        $name = (string) $this->person?->name;
        $hasCredential = $this->person?->user_id !== null;

        $this->originalPhotoUrl = $this->photoUrl;
        $this->originalAddress = $this->addressPayload();
        $this->originalRole = $role;
        $this->originalEmail = $email;
        $this->originalName = $name;

        // Keep what the form displays in step with the new baselines, so a retry
        // after a failed cleanup cannot resubmit a newer value as an edit.
        $this->role = $role;
        $this->email = $email;
        $this->name = $name;
        $this->hasCredential = $hasCredential;

        if ($hasCredential) {
            $this->createCredential = false;
        }
    }
}
