<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\StoreImage;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class Profile extends Component
{
    use ProfileValidationRules, WithFileUploads;

    public string $name = '';

    public string $handle = '';

    public string $email = '';

    /**
     * @var mixed
     */
    public $avatar = null;

    #[Locked]
    public ?string $avatar_url = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->handle = Auth::user()->handle;
        $this->email = Auth::user()->email;
        $this->avatar_url = Auth::user()->getRawOriginal('avatar_url');
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.settings.profile')->title(__('Profile settings'));
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(StoreImage $storeImage): void
    {
        /** @var User */
        $user = Auth::user();

        $this->email = Str::lower($this->email);

        $this->validate([
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        $previousAvatar = $user->getRawOriginal('avatar_url');

        if ($this->avatar instanceof UploadedFile) {
            $path = $storeImage->replace($this->avatar, 'avatars', $previousAvatar);
            $user->avatar_url = $path;
            $this->avatar = null;
            $this->avatar_url = $path;
        } elseif ($previousAvatar !== $this->avatar_url) {
            $storeImage->delete($previousAvatar);
            $user->avatar_url = $this->avatar_url;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

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
