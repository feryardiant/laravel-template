<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Concerns\PasswordValidationRules;
use App\Enums\Role;
use App\Livewire\Actions\Logout;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        /** @var User $user */
        $user = Auth::user();

        DB::transaction(function () use ($user, $logout): void {
            if ($this->isSoleOwner($user)) {
                throw ValidationException::withMessages([
                    'password' => __('The last owner cannot delete their account.'),
                ]);
            }

            tap($user, $logout(...))->delete();
        });

        $this->redirect('/', navigate: true);
    }

    /**
     * Determine whether the user is the only owner of an active organization.
     */
    private function isSoleOwner(User $user): bool
    {
        $person = $user->person;

        if ($person === null) {
            return false;
        }

        $organizationIds = $person->organizations()
            ->wherePivot('role', Role::Owner->value)
            ->orderBy('organizations.id')
            ->pluck('organizations.id');

        foreach ($organizationIds as $organizationId) {
            // Serialize concurrent account deletions for this organization; the
            // count below then starts from a fresh snapshot.
            $organization = Organization::query()->whereKey($organizationId)->lockForUpdate()->firstOrFail();

            if ($organization->activeOwnerCount() === 1) {
                return true;
            }
        }

        return false;
    }
}
