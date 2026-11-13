<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Concerns\PasswordValidationRules;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\Actions\Logout;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

        $today = now()->toDateString();
        $organizationIds = $person->organizations()
            ->wherePivot('role', Role::Owner->value)
            ->pluck('organizations.id');

        foreach ($organizationIds as $organizationId) {
            // Serialize concurrent account deletions for this organization; the
            // count below then starts from a fresh snapshot.
            Organization::query()->whereKey($organizationId)->lockForUpdate()->firstOrFail();

            $owners = OrganizationRelationship::query()
                ->where('organization_id', $organizationId)
                ->where('type', OrganizationRelationshipType::Member->value)
                ->where('role', Role::Owner->value)
                ->whereHasMorph('stakeholder', [Person::class], fn (Builder $query): Builder => $query->whereHas('user'))
                ->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', $today))
                ->where(fn ($query) => $query->whereNull('finish_date')->orWhere('finish_date', '>=', $today))
                ->count();

            if ($owners === 1) {
                return true;
            }
        }

        return false;
    }
}
