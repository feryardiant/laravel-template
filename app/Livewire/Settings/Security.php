<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Users\UpdatePassword;
use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Security extends Component
{
    use PasswordValidationRules;

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    #[Locked]
    public bool $canManageTwoFactor;

    #[Locked]
    public bool $twoFactorEnabled;

    #[Locked]
    public bool $requiresConfirmation;

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            /** @var User $user */
            $user = Auth::user();

            if (Fortify::confirmsTwoFactorAuthentication() && \is_null($user->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication($user);
            }

            $this->twoFactorEnabled = $user->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.settings.security')->title(__('Security settings'));
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(UpdatePassword $updatePassword): void
    {
        try {
            $validated = $this->validate([
                'currentPassword' => $this->currentPasswordRules(),
                'password' => $this->passwordRulesFor('passwordConfirmation'),
            ]);
        } catch (ValidationException $e) {
            $this->reset('currentPassword', 'password', 'passwordConfirmation');

            throw $e;
        }

        /** @var User $user */
        $user = Auth::user();

        $updatePassword->handle($user, $validated['password']);

        $this->reset('currentPassword', 'password', 'passwordConfirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(Auth::user());

        $this->twoFactorEnabled = false;
    }

    /**
     * Refresh the enabled state after the setup modal changes it.
     */
    #[On('two-factor-changed')]
    public function refreshTwoFactor(bool $enabled): void
    {
        $this->twoFactorEnabled = $enabled;
    }
}
