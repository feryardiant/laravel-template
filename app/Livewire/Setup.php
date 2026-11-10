<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Setup\ProvisionTenant;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Support\Setup as SetupState;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Setup extends Component
{
    use PasswordValidationRules, ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Redirect away once setup has already been completed.
     */
    public function mount(): void
    {
        if (! SetupState::required()) {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    /**
     * Provision the first credential and root tenant organization.
     */
    public function setup(ProvisionTenant $provisionTenant): void
    {
        abort_unless(SetupState::required(), 403);

        $this->email = Str::lower($this->email);

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'email' => [
                ...$this->emailRules(),
                Rule::notIn([config('setup.initial_email')]),
            ],
            'password' => $this->passwordRules(),
        ]);

        $user = $provisionTenant->handle(
            $validated['name'],
            Str::lower($validated['email']),
            $validated['password'],
        );

        Auth::login($user);
        session()->regenerate();

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.setup')->layout('layouts.auth')->title(__('Set up your account'));
    }
}
