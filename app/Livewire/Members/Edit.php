<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\Organizations\ChangeMemberRole;
use App\Actions\Users\CreateUser;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Edit extends Component
{
    use HasCurrentOrganization, ProfileValidationRules;

    public ?Person $person = null;

    public bool $showModal = false;

    public bool $fromDetail = false;

    public string $name = '';

    public string $email = '';

    public bool $hasCredential = false;

    public bool $createCredential = false;

    public int $role = Role::Member->value;

    /**
     * Mount the component.
     */
    public function mount(?Person $person = null): void
    {
        if ($person instanceof Person) {
            $this->fromDetail = true;
            $this->fillFrom($person);
        }
    }

    /**
     * Fill the form from the selected member.
     */
    #[On('edit-member')]
    public function editMember(int $personId): void
    {
        $person = $this->member($personId);

        $this->authorizeManager();
        $this->fillFrom($person);

        $this->showModal = true;
    }

    /**
     * Update the selected member.
     */
    public function update(): void
    {
        $organization = $this->requireOrganization();

        $this->authorizeManager();

        abort_if($this->person === null, 404);

        $this->person = $this->member($this->person->getKey());
        $this->email = Str::lower($this->email);

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'email' => $this->hasCredential
                ? $this->emailRules($this->person->user_id)
                : ($this->createCredential ? $this->emailRules() : ['nullable', 'string', 'email', 'max:255']),
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        /** @var User|null $actor */
        $actor = Auth::user();

        abort_unless($actor instanceof User, 403);

        $role = Role::from((int) $validated['role']);
        $currentRole = $this->person->roleIn($organization);

        if ($role !== $currentRole) {
            abort_if($this->person->user_id === $actor->getKey(), 403);

            $this->authorizeTarget($this->person);
            $this->authorizeRole($role);
        }

        $emailChanged = false;
        $credentialCreated = false;

        DB::transaction(function () use ($organization, $actor, $validated, $role, $currentRole, &$emailChanged, &$credentialCreated): void {
            $this->person->update(['name' => $validated['name']]);

            if ($this->hasCredential) {
                $user = $this->person->user;

                abort_if($user === null, 404);

                $emailChanged = $user->email !== $this->email;

                $user->fill([
                    'name' => $validated['name'],
                    'email' => $this->email,
                ]);

                if ($emailChanged) {
                    $user->email_verified_at = null;
                }

                $user->save();
            } elseif ($this->createCredential) {
                $user = app(CreateUser::class)->handle([
                    'name' => $validated['name'],
                    'email' => $this->email,
                    'role' => Role::Member,
                    'password' => Str::random(16),
                ]);

                $this->person->update(['user_id' => $user->getKey()]);

                $credentialCreated = true;
            }

            if ($role !== $currentRole) {
                app(ChangeMemberRole::class)->setRole($organization, $this->person, $role, $actor);
            }
        });

        $this->person = $this->person->fresh(['user']) ?? $this->person;

        if (($this->hasCredential && $emailChanged) || $credentialCreated) {
            $this->person->user?->notify(new VerifyEmailForNewUser);
        }

        $this->showModal = false;

        $this->dispatch('member-updated');

        Flux::toast(
            variant: 'success',
            text: $emailChanged || $credentialCreated
                ? __('Member updated. A verification email has been sent to the new address.')
                : __('Member updated.'),
        );
    }

    /**
     * @return array<int, Role>
     */
    #[Computed]
    public function roles(): array
    {
        return $this->currentRole()?->isOwner()
            ? Role::cases()
            : [Role::Admin, Role::Member];
    }

    public function render(): View
    {
        return view('livewire.members.edit');
    }

    /**
     * Fill the form from the selected member.
     */
    private function fillFrom(Person $person): void
    {
        $this->person = $person->loadMissing('user');
        $this->name = $person->name;
        $this->hasCredential = $person->user_id !== null;
        $this->createCredential = false;
        $this->email = $person->user_id === null ? '' : $person->user->email;
        $this->role = ($person->roleIn($this->requireOrganization()) ?? Role::Member)->value;
    }

    private function member(int $personId): Person
    {
        return $this->requireOrganization()
            ->members()
            ->with('user')
            ->whereKey($personId)
            ->firstOrFail();
    }
}
