<?php

declare(strict_types=1);

namespace App\Livewire\Settings\Profile;

use App\Actions\Users\DeleteUser;
use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout, DeleteUser $deleteUser): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        /** @var User $user */
        $user = Auth::user();

        $deleteUser->handle($user, $logout(...));

        $this->redirect('/', navigate: true);
    }
}
