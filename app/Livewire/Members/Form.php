<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\Addresses\SaveAddress;
use App\Actions\Contacts\SaveContactDetails;
use App\Actions\FileStorage;
use App\Actions\Memberships\ChangeMemberRole;
use App\Actions\Memberships\CreateMember;
use App\Actions\Users\CreateUser;
use App\Concerns\ProfileValidationRules;
use App\Enums\ContactDetailType;
use App\Enums\Gender;
use App\Enums\Role;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Livewire\Concerns\ManagesAddress;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use App\Support\Feature\ContactGender;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * @property-read bool $canEditProfile
 * @property-read bool $genderEditable
 */
class Form extends Component
{
    use HasCurrentOrganization, ManagesAddress, ProfileValidationRules, WithFileUploads;

    public ?Person $person = null;

    public bool $showModal = false;

    public string $name = '';

    public string $email = '';

    public string $contact_email = '';

    public string $contact_phone = '';

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
    public ?string $photo_url = null;

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

    /**
     * Fill the form from the selected member.
     */
    #[On('edit-member')]
    public function editMember(int $personId): void
    {
        $person = $this->member($personId);

        $this->authorizeManager();
        $this->authorizeTarget($person);
        $this->genderEnabled = ContactGender::enabled($this->requireOrganization());
        $this->fillFrom($person);

        $this->showModal = true;
    }

    /**
     * Open the form for a new member.
     */
    #[On('create-member')]
    public function openCreate(): void
    {
        $this->authorizeManager();

        $this->reset('person', 'name', 'email', 'contact_email', 'contact_phone', 'originalDetails', 'originalPhotoUrl', 'originalAddress', 'originalRole', 'originalEmail', 'originalName', 'hasCredential', 'createCredential', 'gender', 'photo', 'photo_url');

        $this->role = Role::Member->value;
        $this->genderEnabled = false;
        $this->resetAddress();
        $this->resetValidation();

        $this->showModal = true;
    }

    /**
     * Add a person to the current organization.
     */
    public function create(CreateMember $createMember, FileStorage $fileStorage): void
    {
        $organization = $this->requireOrganization();

        $this->authorizeManager();

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
        $this->authorizeRole($role);

        $hasCredential = $this->createCredential;
        $photoUrl = $this->photo instanceof UploadedFile
            ? $fileStorage->handle($this->photo, 'photos')
            : null;

        try {
            $createMember->handle(
                $organization,
                $validated['name'],
                $role,
                $hasCredential ? $validated['email'] : null,
                $photoUrl,
                Auth::user(),
                $this->addressPayload(),
                $validated['contact_email'] ?? null,
                $validated['contact_phone'] ?? null,
            );
        } catch (Throwable $exception) {
            // A failure after the commit (such as the invite email) must not
            // delete a photo the committed member already references.
            if ($photoUrl !== null && ! Person::query()->where('photo_url', $photoUrl)->exists()) {
                $fileStorage->delete($photoUrl);
            }

            throw $exception;
        }

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
    public function save(CreateMember $createMember, FileStorage $fileStorage, SaveAddress $saveAddress, SaveContactDetails $saveContactDetails): void
    {
        if ($this->person === null) {
            $this->create($createMember, $fileStorage);

            return;
        }

        $this->update($fileStorage, $saveAddress, $saveContactDetails);
    }

    /**
     * Update the selected member.
     */
    public function update(FileStorage $fileStorage, SaveAddress $saveAddress, SaveContactDetails $saveContactDetails): void
    {
        $organization = $this->requireOrganization();

        $this->authorizeManager();

        abort_if($this->person === null, 404);

        $this->person = $this->member($this->person->getKey());
        $this->authorizeTarget($this->person);
        $this->email = Str::lower($this->email);

        $canEditProfile = $this->canEditProfile;

        if (! $canEditProfile) {
            // Compare with what the form loaded: the member may have renamed themselves.
            abort_if($this->name !== $this->originalName, 403);
            abort_if($this->createCredential, 403);
            abort_if($this->photo instanceof UploadedFile, 403);

            if ($this->hasCredential && $this->person->user !== null && Str::lower($this->person->user->email) !== $this->email && $this->email !== $this->originalEmail) {
                abort(403);
            }
        }

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

        $role = Role::from((int) $validated['role']);
        $currentRole = $this->person->roleIn($organization);

        if ($role->value === $this->originalRole && $role !== $currentRole) {
            // The editor did not change the role; keep the value saved elsewhere.
            $role = $currentRole ?? $role;
        }

        $this->authorizeTarget($this->person);

        if ($role !== $currentRole) {
            abort_if($this->person->user_id === $actor->getKey(), 403);

            $this->authorizeRole($role);
        }

        // Credentials are global: editing an email shared with other organizations would hand them the account.
        if ($canEditProfile && $this->hasCredential && $this->person->user !== null && Str::lower($this->person->user->email) !== $this->email && $this->email !== $this->originalEmail) {
            abort_if($this->person->organizations()->whereKeyNot($organization->getKey())->exists(), 403);
        }

        $previousPhoto = $this->person->getRawOriginal('photo_url');
        $photoUrl = $previousPhoto;
        $stagedPhoto = null;
        $removePhoto = false;

        if ($canEditProfile) {
            if ($this->photo instanceof UploadedFile) {
                $stagedPhoto = $fileStorage->handle($this->photo, 'photos');
                $photoUrl = $stagedPhoto;
            } elseif ($this->photo_url === null && $this->originalPhotoUrl !== null) {
                $photoUrl = null;
                $removePhoto = true;
            }
        }

        $emailChanged = false;
        $credentialCreated = false;
        $gender = $this->genderEnabled && $this->genderEditable
            ? ($validated['gender'] ?? null)
            : null;

        try {
            DB::transaction(function () use ($organization, $actor, $validated, $photoUrl, $stagedPhoto, $removePhoto, $role, $currentRole, $canEditProfile, $gender, $saveAddress, $saveContactDetails, &$emailChanged, &$credentialCreated): void {
                if ($canEditProfile) {
                    $attributes = [];

                    if ($this->name !== $this->originalName) {
                        $attributes['name'] = $validated['name'];
                    }

                    if ($stagedPhoto !== null || $removePhoto) {
                        $attributes['photo_url'] = $photoUrl;
                    }

                    if ($gender !== null) {
                        $attributes['gender'] = $gender;
                    }

                    if ($attributes !== []) {
                        $this->person->update($attributes);
                    }

                    $address = $this->addressPayload();

                    if ($address !== $this->originalAddress) {
                        $saveAddress->handle($this->person, $address);
                    }
                }

                // Communication records stay editable even when the profile is managed elsewhere.
                $saveContactDetails->handle($this->person, $this->changedContactDetails());

                if ($canEditProfile && $this->hasCredential) {
                    if ($this->email !== $this->originalEmail) {
                        $user = $this->person->user;

                        abort_if($user === null, 404);

                        $emailChanged = $user->email !== $this->email;

                        $user->email = $this->email;

                        if ($emailChanged) {
                            $user->email_verified_at = null;
                        }

                        $user->save();
                    }
                } elseif ($canEditProfile && $this->createCredential) {
                    $hasUser = Person::query()
                        ->whereKey($this->person->getKey())
                        ->lockForUpdate()
                        ->value('user_id') !== null;

                    if (! $hasUser) {
                        $user = app(CreateUser::class)->handle(
                            name: $this->person->name,
                            email: $this->email,
                            password: Str::random(16),
                        );

                        $this->person->update(['user_id' => $user->getKey()]);

                        $credentialCreated = true;
                    }
                }

                if ($role !== $currentRole) {
                    app(ChangeMemberRole::class)->setRole($organization, $this->person, $role, $actor);
                }
            });
        } catch (Throwable $exception) {
            // A failure after the commit (an after-commit audit push) must not
            // delete a photo the committed member references.
            if ($stagedPhoto !== null && ! Person::query()->where('photo_url', $stagedPhoto)->exists()) {
                $fileStorage->delete($stagedPhoto);
            }

            throw $exception;
        }

        if ($stagedPhoto !== null) {
            // The replacement is committed; a failed cleanup only leaves an
            // orphaned file, so it is logged rather than rolled back.
            $fileStorage->delete($previousPhoto);
        }

        $this->person = $this->person->fresh(['user']) ?? $this->person;
        $this->photo = null;
        $this->photo_url = $photoUrl;

        $this->rememberContactDetails();
        $this->rememberBaselines($organization);

        $photoRemovalFailed = false;

        if ($removePhoto && ! $fileStorage->delete($previousPhoto)) {
            $restored = Person::query()
                ->whereKey($this->person->getKey())
                ->whereNull('photo_url')
                ->update(['photo_url' => $previousPhoto]);

            $this->photo_url = $restored === 0
                ? ($this->person->refresh())->getRawOriginal('photo_url')
                : $previousPhoto;

            $this->rememberBaselines($organization);

            $photoRemovalFailed = true;
        }

        if (($this->hasCredential && $emailChanged) || $credentialCreated) {
            $this->person->user?->notify(new VerifyEmailForNewUser);
        }

        if ($photoRemovalFailed) {
            Flux::toast(variant: 'danger', text: __('Unable to remove the :field. Please try again.', ['field' => __('Photo')]));

            // The rest of the edit committed; let the list reload.
            $this->dispatch('member-updated');

            return;
        }

        $this->showModal = false;

        $this->dispatch('member-updated');

        Flux::toast(
            variant: 'success',
            text: $emailChanged || $credentialCreated
                ? __('Member updated. A verification email has been sent to the new address.')
                : __('Member updated.'),
        );
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

    public function render(): View
    {
        return view('livewire.members.form');
    }

    /**
     * Fill the form from the selected member.
     */
    private function fillFrom(Person $person): void
    {
        $this->person = $person->loadMissing('user');
        $this->name = $person->name;
        $this->photo = null;
        $this->photo_url = $person->getRawOriginal('photo_url');
        $this->hasCredential = $person->user_id !== null;
        $this->createCredential = false;
        $this->email = $person->user_id === null ? '' : $person->user->email;
        $this->contact_email = (string) $person->contactDetailValue(ContactDetailType::Email);
        $this->contact_phone = (string) $person->contactDetailValue(ContactDetailType::Phone);
        $this->gender = $person->gender?->value;
        $this->role = ($person->roleIn($this->requireOrganization()) ?? Role::Member)->value;
        $this->fillAddress($person->addresses()->first());
        $this->originalDetails = $this->contactDetailValues();
        $this->originalPhotoUrl = $this->photo_url;
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
            ContactDetailType::Email->value => $this->contact_email,
            ContactDetailType::Phone->value => $this->contact_phone,
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
        $role = ($this->person?->roleIn($organization) ?? Role::Member)->value;
        $email = Str::lower((string) $this->person?->user?->email);
        $name = (string) $this->person?->name;
        $hasCredential = $this->person?->user_id !== null;

        $this->originalPhotoUrl = $this->photo_url;
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

    /**
     * Clear the pending or stored member photo.
     */
    public function clearPhoto(): void
    {
        $this->photo = null;
        $this->photo_url = null;
    }

    #[Computed]
    public function photoPreviewUrl(): ?string
    {
        return $this->photo_url ? route('uploads', ['path' => $this->photo_url]) : null;
    }

    private function member(int $personId): Person
    {
        return $this->requireOrganization()
            ->members()
            ->with('user')
            ->whereKey($personId)
            ->firstOrFail();
    }
}
