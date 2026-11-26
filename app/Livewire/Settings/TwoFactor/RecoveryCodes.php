<?php

declare(strict_types=1);

namespace App\Livewire\Settings\TwoFactor;

use App\Actions\Users\GetRecoveryCodes;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Shows and regenerates the signed-in user's two-factor recovery codes.
 */
class RecoveryCodes extends Component
{
    /** @var list<string> */
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(GetRecoveryCodes $getRecoveryCodes): void
    {
        $this->loadRecoveryCodes($getRecoveryCodes);
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes, GetRecoveryCodes $getRecoveryCodes): void
    {
        $generateNewRecoveryCodes(Auth::user());

        $this->loadRecoveryCodes($getRecoveryCodes);
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(GetRecoveryCodes $getRecoveryCodes): void
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $this->recoveryCodes = $getRecoveryCodes->handle($user) ?? [];
        } catch (Exception) {
            $this->addError('recoveryCodes', __('Failed to load recovery codes'));

            $this->recoveryCodes = [];
        }
    }
}
