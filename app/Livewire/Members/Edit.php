<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\FileStorage;
use App\Actions\Memberships\ChangeMemberRole;
use App\Actions\Users\CreateUser;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use HasCurrentOrganization, ProfileValidationRules, WithFileUploads;

    public ?Person $person = null;

    public bool $showModal = false;

    public bool $fromDetail = false;

    public string $name = '';

    public string $email = '';

    #[Locked]
    public bool $hasCredential = false;

    public bool $createCredential = false;

    public int $role = Role::Member->value;

    /**
     * @var mixed
     */
    public $photo = null;

    #[Locked]
    public ?string $photo_url = null;

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
        $this->authorizeTarget($person);
        $this->fillFrom($person);

        $this->showModal = true;
    }

    /**
     * Update the selected member.
     */
    public function update(FileStorage $fileStorage): void
    {
        $organization = $this->requireOrganization();

        $this->authorizeManager();

        abort_if($this->person === null, 404);

        $this->person = $this->member($this->person->getKey());
        $this->authorizeTarget($this->person);
        $this->email = Str::lower($this->email);

        $this->validate([
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'email' => $this->hasCredential
                ? $this->emailRules($this->person->user_id)
                : ($this->createCredential ? $this->emailRules() : ['nullable', 'string', 'email', 'max:255']),
            'role' => ['required', Rule::in([Role::Admin->value, Role::Member->value])],
        ]);

        /** @var User|null $actor */
        $actor = Auth::user();

        abort_unless($actor instanceof User, 403);

        $role = Role::from((int) $validated['role']);
        $currentRole = $this->person->roleIn($organization);

        $this->authorizeTarget($this->person);

        if ($role !== $currentRole) {
            abort_if($this->person->user_id === $actor->getKey(), 403);

            $this->authorizeRole($role);
        }

        // Credentials are global: editing an email shared with other organizations would hand them the account.
        if ($this->hasCredential && $this->person->user !== null && Str::lower($this->person->user->email) !== $this->email) {
            abort_if($this->person->organizations()->whereKeyNot($organization->getKey())->exists(), 403);
        }

        $previousPhoto = $this->person->getRawOriginal('photo_url');
        $photoUrl = $this->photo_url;

        if ($this->photo instanceof UploadedFile) {
            $photoUrl = $fileStorage->replace($this->photo, 'photos', $previousPhoto);
        } elseif ($previousPhoto !== $this->photo_url) {
            $fileStorage->delete($previousPhoto);
        }

        $emailChanged = false;
        $credentialCreated = false;

        DB::transaction(function () use ($organization, $actor, $validated, $photoUrl, $role, $currentRole, &$emailChanged, &$credentialCreated): void {
            $this->person->update([
                'name' => $validated['name'],
                'photo_url' => $photoUrl,
            ]);

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
                $user = app(CreateUser::class)->handle(
                    name: $validated['name'],
                    email: $this->email,
                    password: Str::random(16),
                );

                $this->person->update(['user_id' => $user->getKey()]);

                $credentialCreated = true;
            }

            if ($role !== $currentRole) {
                app(ChangeMemberRole::class)->setRole($organization, $this->person, $role, $actor);
            }
        });

        $this->person = $this->person->fresh(['user']) ?? $this->person;
        $this->photo = null;
        $this->photo_url = $photoUrl;

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
        return [Role::Admin, Role::Member];
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
        $this->photo_url = $person->getRawOriginal('photo_url');
        $this->hasCredential = $person->user_id !== null;
        $this->createCredential = false;
        $this->email = $person->user_id === null ? '' : $person->user->email;
        $this->role = ($person->roleIn($this->requireOrganization()) ?? Role::Member)->value;
    }

    /**
     * Clear the pending or stored member photo.
     */
    public function clearPhoto(): void
    {
        $this->photo = null;
        $this->photo_url = null;
    }

    #[Computed]
    public function photoPreviewUrl(): ?string
    {
        return $this->photo_url ? route('uploads', ['path' => $this->photo_url]) : null;
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
