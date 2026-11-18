<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Contacts\SaveContactDetails;
use App\Actions\FileStorage;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * @property-read ?Person $person
 * @property-read bool $canEditOwnProfile
 * @property-read bool $genderEnabled
 * @property-read bool $genderEditable
 */
class Profile extends Component
{
    use HasCurrentOrganization, ProfileValidationRules, WithFileUploads;

    public string $name = '';

    public string $handle = '';

    public string $email = '';

    public string $summary = '';

    public string $contact_email = '';

    public string $contact_phone = '';

    /**
     * The detail values loaded when the form opened; only changes are submitted.
     *
     * @var array<string, string>
     */
    #[Locked]
    public array $originalDetails = [];

    public ?string $gender = null;

    /**
     * @var mixed
     */
    public $avatar = null;

    #[Locked]
    public ?string $avatar_url = null;

    public function mount(): void
    {
        /** @var User */
        $user = Auth::user();
        $person = $user->person;

        $this->name = $person->name ?? $user->name;
        $this->handle = $user->handle;
        $this->email = $user->email;
        $this->summary = (string) $person?->summary;
        $this->contact_email = (string) $person?->contactDetailValue(ContactDetailType::Email);
        $this->contact_phone = (string) $person?->contactDetailValue(ContactDetailType::Phone);
        $this->gender = $person?->gender?->value;
        $this->avatar_url = $person?->getRawOriginal('photo_url');
        $this->originalDetails = $this->contactDetailValues();
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

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.settings.profile')->title(__('Profile settings'));
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(FileStorage $fileStorage, SaveContactDetails $saveContactDetails): void
    {
        /** @var User */
        $user = Auth::user();
        $canEdit = $this->canEditOwnProfile;
        $person = $user->person;

        $this->email = Str::lower($this->email);

        $rules = [
            'handle' => $this->handleRules($user->id),
            'summary' => ['nullable', 'string', 'max:255'],
            ...$this->contactDetailRules(),
        ];

        if ($canEdit) {
            $rules['name'] = $this->nameRules();
            $rules['email'] = $this->emailRules($user->id);
            $rules['avatar'] = ['nullable', 'image', 'max:2048'];
        }

        if ($this->genderEditable) {
            $rules['gender'] = ['nullable', Rule::enum(Gender::class)];
        }

        $validated = $this->validate($rules);

        $previousAvatar = $person?->getRawOriginal('photo_url');
        $photoPath = $previousAvatar;
        $stagedAvatar = null;
        $removeAvatar = false;

        if ($canEdit) {
            $photoPath = $this->avatar_url;

            if ($this->avatar instanceof UploadedFile) {
                $stagedAvatar = $fileStorage->handle($this->avatar, 'avatars');
                $photoPath = $stagedAvatar;
            } elseif ($previousAvatar !== $this->avatar_url) {
                $removeAvatar = true;
            }
        }

        try {
            DB::transaction(function () use ($user, $person, $canEdit, $validated, $photoPath, $saveContactDetails): void {
                if ($person === null) {
                    $person = $user->person()->create([
                        'name' => $canEdit ? $validated['name'] : $user->name,
                        'owner_organization_id' => null,
                    ]);

                    $user->setRelation('person', $person);
                }

                $saveContactDetails->handle($person, $this->changedContactDetails());

                $attributes = [
                    'summary' => $validated['summary'] ?? null,
                ];

                if ($canEdit) {
                    $attributes['name'] = $validated['name'];
                    $attributes['photo_url'] = $photoPath;
                }

                if ($this->genderEditable && \array_key_exists('gender', $validated)) {
                    $attributes['gender'] = $validated['gender'];
                }

                $person->update($attributes);

                $user->handle = $validated['handle'];

                if ($canEdit) {
                    $user->email = $validated['email'];
                }

                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }

                $user->save();
            });
        } catch (Throwable $exception) {
            $fileStorage->delete($stagedAvatar);

            throw $exception;
        }

        if ($stagedAvatar !== null) {
            // Only drop the previous file once the new path is committed.
            $fileStorage->delete($previousAvatar);
        }

        $this->avatar = null;
        $this->avatar_url = $photoPath;
        $this->rememberContactDetails();
        $this->gender = $user->fresh()?->person?->gender?->value;

        if ($removeAvatar && ! $fileStorage->delete($previousAvatar)) {
            $restored = Person::query()
                ->whereKey($person?->getKey())
                ->whereNull('photo_url')
                ->update(['photo_url' => $previousAvatar]);

            $this->avatar_url = $restored === 0
                ? $user->fresh()?->person?->getRawOriginal('photo_url')
                : $previousAvatar;

            Flux::toast(variant: 'danger', text: __('Unable to remove the :field. Please try again.', ['field' => __('User avatar')]));

            return;
        }

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Clear the pending or stored avatar.
     */
    public function clearAvatar(): void
    {
        $this->avatar = null;
        $this->avatar_url = null;
    }

    #[Computed]
    public function avatarPreviewUrl(): ?string
    {
        return $this->avatar_url ? route('uploads', ['path' => $this->avatar_url]) : null;
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

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        /** @var User */
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
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
}
