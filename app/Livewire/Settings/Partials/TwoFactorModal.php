<?php

declare(strict_types=1);

namespace App\Livewire\Settings\Partials;

use App\Models\User;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Enables and confirms two-factor authentication.
 *
 * @property-read array{title: string, description: string, buttonText: string} $modalConfig
 */
class TwoFactorModal extends Component
{
    public bool $showModal = false;

    public bool $showVerificationStep = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    #[Locked]
    public bool $twoFactorEnabled = false;

    #[Locked]
    public bool $requiresConfirmation = false;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public function mount(bool $requiresConfirmation = false): void
    {
        $this->requiresConfirmation = $requiresConfirmation;

        /** @var User $user */
        $user = Auth::user();

        $this->twoFactorEnabled = $user->hasEnabledTwoFactorAuthentication();
    }

    public function render(): View
    {
        return view('livewire.settings.partials.two-factor-modal');
    }

    /**
     * Get the current modal configuration state.
     *
     * @return array{title: string, description: string, buttonText: string}
     */
    #[Computed]
    public function modalConfig(): array
    {
        if ($this->twoFactorEnabled) {
            return [
                'title' => __('Two-factor authentication enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify authentication code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable two-factor authentication'),
            'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
            'buttonText' => __('Continue'),
        ];
    }

    /**
     * Enable two-factor authentication for the user.
     */
    #[On('enable-two-factor')]
    public function enable(EnableTwoFactorAuthentication $enableTwoFactorAuthentication): void
    {
        /** @var User $user */
        $user = Auth::user();
        $enableTwoFactorAuthentication($user);

        $this->twoFactorEnabled = ! $this->requiresConfirmation;

        if (! $this->requiresConfirmation) {
            $this->dispatch('two-factor-changed', enabled: true);
        }

        $this->loadSetupData();

        $this->showModal = true;
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->close();
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        /** @var User $user */
        $user = Auth::user();
        $confirmTwoFactorAuthentication($user, $this->code);

        $this->twoFactorEnabled = true;

        $this->close();

        $this->dispatch('two-factor-changed', enabled: true);
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function close(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showModal',
            'showVerificationStep',
        );

        $this->resetErrorBag();

        if (! $this->requiresConfirmation) {
            /** @var User $user */
            $user = Auth::user();
            $this->twoFactorEnabled = $user->hasEnabledTwoFactorAuthentication();

            $this->dispatch('two-factor-changed', enabled: $this->twoFactorEnabled);
        }
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', __('Failed to fetch setup data.'));

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }
}
