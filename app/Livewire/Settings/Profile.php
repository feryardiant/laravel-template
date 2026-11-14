<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\FileStorage;
use App\Concerns\ProfileValidationRules;
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
        $this->gender = $person?->gender?->value;
        $this->avatar_url = $person?->getRawOriginal('photo_url');
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.settings.profile')->title(__('Profile settings'));
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(FileStorage $fileStorage): void
    {
        /** @var User */
        $user = Auth::user();
        $canEdit = $this->canEditOwnProfile;
        $person = $user->person;

        $this->email = Str::lower($this->email);

        $rules = [
            'handle' => $this->handleRules($user->id),
            'summary' => ['nullable', 'string', 'max:255'],
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

        if ($canEdit) {
            $photoPath = $this->avatar_url;

            if ($this->avatar instanceof UploadedFile) {
                $photoPath = $fileStorage->replace($this->avatar, 'avatars', $previousAvatar);
            } elseif ($previousAvatar !== $this->avatar_url) {
                $fileStorage->delete($previousAvatar);
            }
        }

        DB::transaction(function () use ($user, $person, $canEdit, $validated, $photoPath): void {
            if ($person === null) {
                $person = $user->person()->create([
                    'name' => $canEdit ? $validated['name'] : $user->name,
                    'owner_organization_id' => null,
                ]);

                $user->setRelation('person', $person);
            }

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

        $this->avatar = null;
        $this->avatar_url = $photoPath;
        $this->gender = $user->fresh()?->person?->gender?->value;

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
