<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Users\ResendEmailVerification;
use App\Actions\Users\UpdateUserProfile;
use App\Concerns\ProfileValidationRules;
use App\Enums\ContactDetailType;
use App\Enums\Gender;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Person;
use App\Models\User;
use App\Support\Feature\ContactGender;
use App\Support\Feature\ProfileSelfEdit;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * @property-read ?Person $person
 * @property-read bool $canEditOwnProfile
 * @property-read bool $genderEnabled
 * @property-read bool $genderEditable
 * @property-read ?string $avatarPreviewUrl
 * @property-read bool $hasUnverifiedEmail
 * @property-read bool $showDeleteUser
 */
class Profile extends Component
{
    use HasCurrentOrganization, ProfileValidationRules, WithFileUploads;

    public string $name = '';

    public string $handle = '';

    public string $email = '';

    public string $summary = '';

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
    public ?string $originalAvatarUrl = null;

    #[Locked]
    public string $originalEmail = '';

    #[Locked]
    public string $originalName = '';

    #[Locked]
    public string $originalSummary = '';

    #[Locked]
    public string $originalHandle = '';

    public ?string $gender = null;

    /**
     * @var mixed
     */
    public $avatar = null;

    #[Locked]
    public ?string $avatarUrl = null;

    public function mount(): void
    {
        /** @var User */
        $user = Auth::user();
        $person = $user->person;

        $this->name = $person->name ?? $user->name;
        $this->handle = $user->handle;
        $this->email = $user->email;
        $this->summary = (string) $person?->summary;
        $this->contactEmail = (string) $person?->contactDetailValue(ContactDetailType::Email);
        $this->contactPhone = (string) $person?->contactDetailValue(ContactDetailType::Phone);
        $this->gender = $person?->gender?->value;
        $this->avatarUrl = $person?->getRawOriginal('photo_url');
        $this->originalDetails = $this->contactDetailValues();
        $this->originalAvatarUrl = $this->avatarUrl;
        $this->originalEmail = Str::lower($this->email);
        $this->originalName = $this->name;
        $this->originalSummary = $this->summary;
        $this->originalHandle = $this->handle;
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.settings.profile')->title(__('Profile settings'));
    }

    #[Computed]
    public function avatarPreviewUrl(): ?string
    {
        return $this->avatarUrl ? route('uploads', ['path' => $this->avatarUrl]) : null;
    }

    /**
     * The authenticated user's person profile.
     */
    #[Computed]
    public function person(): ?Person
    {
        /** @var User */
        $user = Auth::user();

        return $user->person;
    }

    /**
     * Determine whether the person controls their profile fields.
     */
    #[Computed]
    public function canEditOwnProfile(): bool
    {
        $person = $this->person;

        return $person === null
            || $person->owner_organization_id === null
            || ProfileSelfEdit::enabled($person->ownerOrganization);
    }

    /**
     * Determine whether the current workspace exposes the gender field.
     */
    #[Computed]
    public function genderEnabled(): bool
    {
        return ContactGender::enabled($this->currentOrganization());
    }

    /**
     * Gender can be set once.
     */
    #[Computed]
    public function genderEditable(): bool
    {
        return $this->genderEnabled && $this->person !== null && $this->person->gender === null;
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        $user = Auth::user();

        return $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        $user = Auth::user();

        return ! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail();
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(UpdateUserProfile $updateUserProfile): void
    {
        /** @var User */
        $user = Auth::user();
        $canEdit = $this->canEditOwnProfile;

        $this->email = Str::lower($this->email);

        $rules = [
            'handle' => $this->handleRules($user->id, unique: $this->handle !== $this->originalHandle),
            'summary' => ['nullable', 'string', 'max:255'],
            ...$this->contactDetailRules(),
        ];

        if ($canEdit) {
            $rules['name'] = $this->nameRules();
            $rules['email'] = $this->email !== $this->originalEmail
                ? $this->emailRules($user->id)
                : ['required', 'string', 'email', 'max:255'];
            $rules['avatar'] = ['nullable', 'image', 'max:2048'];
        }

        if ($this->genderEditable) {
            $rules['gender'] = ['nullable', Rule::enum(Gender::class)];
        }

        $validated = $this->validate($rules);

        $result = $updateUserProfile->handle(
            user: $user,
            data: [
                'name' => $this->name,
                'handle' => $validated['handle'],
                'email' => $this->email,
                'summary' => $this->summary,
                'gender' => $validated['gender'] ?? null,
                'gender_editable' => $this->genderEditable,
                'details' => $this->changedContactDetails(),
                'can_edit' => $canEdit,
                'remove_avatar' => $this->avatarUrl === null && $this->originalAvatarUrl !== null,
            ],
            expected: [
                'name' => $this->originalName,
                'handle' => $this->originalHandle,
                'email' => $this->originalEmail,
                'summary' => $this->originalSummary,
                'details' => $this->originalDetails,
                'avatar_url' => $this->originalAvatarUrl,
            ],
            avatar: $this->avatar instanceof UploadedFile ? $this->avatar : null,
        );

        $fresh = $result['user'];

        $this->avatar = null;
        $this->avatarUrl = $result['avatar_url'];
        $this->rememberContactDetails();
        $this->rememberBaselines($fresh);
        $this->gender = $fresh->person?->gender?->value;

        if ($result['avatar_removal_failed']) {
            $this->originalAvatarUrl = $this->avatarUrl;

            Flux::toast(variant: 'danger', text: __('Unable to remove the :field. Please try again.', ['field' => __('User avatar')]));

            return;
        }

        $this->originalAvatarUrl = $this->avatarUrl;

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Clear the pending or stored avatar.
     */
    public function clearAvatar(): void
    {
        $this->avatar = null;
        $this->avatarUrl = null;
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(ResendEmailVerification $resendEmailVerification): void
    {
        /** @var User */
        $user = Auth::user();

        if (! $resendEmailVerification->handle($user)) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
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

    private function rememberBaselines(?User $fresh): void
    {
        $person = $fresh?->person;

        $this->originalName = (string) $person?->name;
        $this->name = $this->originalName;
        $this->originalSummary = (string) $person?->summary;
        $this->summary = $this->originalSummary;
        $this->originalHandle = (string) $fresh?->handle;
        $this->handle = $this->originalHandle;
        $this->originalEmail = Str::lower((string) $fresh?->email);
        $this->email = $this->originalEmail;
    }
}
