<?php

declare(strict_types=1);

use App\Actions\Contacts\SaveContactDetails;
use App\Actions\Memberships\CreateMember;
use App\Enums\ContactDetailType;
use App\Enums\Gender;
use App\Enums\Role;
use App\Livewire\Members\Form;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use App\Support\Feature\ContactGender;
use Closure;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\ContactDetailWriteFailed;

uses(RefreshDatabase::class);

test('editing a member updates the person and the per-organization role', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(Form::class, ['person' => $member->person])
        ->set('name', 'Renamed Member')
        ->set('role', Role::Admin->value)
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->fresh()->name)->toBe('Renamed Member');
    expect($member->person->roleIn($organization))->toBe(Role::Admin);
});

test('editing a member can create a credential', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $person = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $person, startDate: now());

    Livewire::test(Form::class, ['person' => $person])
        ->set('createCredential', true)
        ->set('email', 'credential@example.com')
        ->call('update')
        ->assertHasNoErrors();

    $person->refresh();

    expect($person->user)->not->toBeNull();
    expect($person->user->email)->toBe('credential@example.com');

    Notification::assertSentTo($person->user, VerifyEmailForNewUser::class);
});

test('a self-owned member profile is read-only for managers', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->withProfile()->create();
    app(CreateMember::class)->attach($organization, $member->person, startDate: now(), role: Role::Member);

    $original = $member->person->name;

    Livewire::test(Form::class, ['person' => $member->person])
        ->assertSee(__('This person controls their profile.'))
        ->set('name', 'Tampered Name')
        ->call('update')
        ->assertForbidden();

    expect($member->person->fresh()->name)->toBe($original);

    Livewire::test(Form::class, ['person' => $member->person])
        ->set('role', Role::Admin->value)
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->roleIn($organization))->toBe(Role::Admin);
});

test('a read-only manager can save a role change while the member renames themselves', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->withProfile()->create(['name' => 'Old Name']);
    app(CreateMember::class)->attach($organization, $member->person, startDate: now(), role: Role::Member);

    $form = Livewire::test(Form::class, ['person' => $member->person]);

    $member->person->update(['name' => 'Newer Name']);

    $form->set('role', Role::Admin->value)->call('update')->assertHasNoErrors();

    expect($member->person->fresh()->name)->toBe('Newer Name')
        ->and($member->person->roleIn($organization))->toBe(Role::Admin);
});

test('gender can only be set once for an org-owned member', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    ContactGender::activate($organization);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();
    $member->person->update(['gender' => null]);

    Livewire::test(Form::class, ['person' => $member->person])
        ->assertSee(__('Gender'))
        ->set('gender', Gender::Male->value)
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->fresh()->gender)->toBe(Gender::Male);

    Livewire::test(Form::class, ['person' => $member->person->fresh()])
        ->set('gender', Gender::Female->value)
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->fresh()->gender)->toBe(Gender::Male);
});

test('an admin cannot edit an owner', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();

    Livewire::test(Form::class, ['person' => $owner->person])
        ->set('email', 'attacker@example.com')
        ->call('update')
        ->assertForbidden();

    expect($owner->fresh()->email)->not->toBe('attacker@example.com');
});

test('an admin cannot change the email of a credential shared with another organization', function () {
    $home = Organization::factory()->create();
    $guest = Organization::factory()->create();

    $member = User::factory()->asMemberOf($home, Role::Owner)->create();

    app(CreateMember::class)->attach($guest, $member->person, role: Role::Member);

    actingAsMember($guest, Role::Admin);

    Livewire::test(Form::class, ['person' => $member->person])
        ->set('email', 'attacker@example.com')
        ->call('update')
        ->assertForbidden();

    expect($member->fresh()->email)->not->toBe('attacker@example.com');
});

test('an owner can change the email of a member credential', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(Form::class, ['person' => $member->person])
        ->set('email', 'renamed@example.com')
        ->call('update')
        ->assertHasNoErrors();

    expect($member->fresh()->email)->toBe('renamed@example.com');
});

test('the owner role cannot be assigned when editing a member', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    $component = Livewire::test(Form::class, ['person' => $member->person]);

    expect($component->instance()->roles())->toBe([Role::Admin, Role::Member]);

    $component->set('role', Role::Owner->value)
        ->call('update')
        ->assertHasErrors('role');

    expect($member->person->fresh()->roleIn($organization))->toBe(Role::Member);
});

test('a member photo replacement keeps the new path when the old file cannot be deleted', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();
    $member->person->update(['photo_url' => 'photos/blocked']);

    // A directory at the object path cannot be unlinked, which forces the failure.
    Storage::disk()->makeDirectory('photos/blocked');

    Livewire::test(Form::class, ['person' => $member->person])
        ->set('photo', UploadedFile::fake()->image('new.jpg'))
        ->call('update')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'success');

    $stored = $member->person->fresh()->getRawOriginal('photo_url');

    expect($stored)->toContain('photos/')
        ->and($stored)->not->toBe('photos/blocked')
        ->and(Storage::disk()->exists((string) $stored))->toBeTrue()
        ->and(Storage::disk()->directoryExists('photos/blocked'))->toBeTrue();
});

test('a stale member save keeps a photo saved by another session', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();

    $form = Livewire::test(Form::class, ['person' => $member->person]);

    Storage::disk()->put('photos/new.jpg', 'photo');
    $member->person->update(['photo_url' => 'photos/new.jpg']);

    $form->set('name', 'Renamed Member')->call('update')->assertHasNoErrors();

    expect($member->person->fresh()->getRawOriginal('photo_url'))->toBe('photos/new.jpg')
        ->and(Storage::disk()->exists('photos/new.jpg'))->toBeTrue();
});

test('a stale member save keeps an address saved by another session', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();

    $form = Livewire::test(Form::class, ['person' => $member->person]);

    $member->person->addresses()->create(validAddress());

    $form->set('name', 'Renamed Member')->call('update')->assertHasNoErrors();

    expect($member->person->addresses()->exists())->toBeTrue();
});

test('a stale member save does not revert a role change', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    $form = Livewire::test(Form::class, ['person' => $member->person]);

    OrganizationRelationship::query()
        ->forOrganization($organization)
        ->whereStakeholder($member->person)
        ->update(['role' => Role::Admin->value]);

    $form->set('name', 'Renamed Member')->call('update')->assertHasNoErrors();

    expect($member->person->fresh()?->roleIn($organization))->toBe(Role::Admin);
});

test('a stale member save does not revert a login email change', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();

    $form = Livewire::test(Form::class, ['person' => $member->person]);

    $member->update(['email' => 'newer@example.test']);

    $form->set('name', 'Renamed Member')->call('update')->assertHasNoErrors();

    expect($member->fresh()->email)->toBe('newer@example.test');
});

test('a failed member update keeps the previous photo', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();
    $member->person->update(['photo_url' => 'photos/old.jpg']);
    Storage::disk()->put('photos/old.jpg', 'photo');

    failContactDetailWrites();

    $thrown = false;

    try {
        Livewire::test(Form::class, ['person' => $member->person])
            ->set('name', 'Renamed Member')
            ->set('contact_phone', '0899 111')
            ->set('photo', UploadedFile::fake()->image('new.jpg'))
            ->call('update');
    } catch (ContactDetailWriteFailed) {
        $thrown = true;
    } finally {
        restoreContactDetailWrites();
    }

    expect($thrown)->toBeTrue()
        ->and($member->person->fresh()->getRawOriginal('photo_url'))->toBe('photos/old.jpg')
        ->and(Storage::disk()->allFiles('photos'))->toBe(['photos/old.jpg']);
});

test('a failed member update keeps a photo being removed', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();
    $member->person->update(['photo_url' => 'photos/old.jpg']);
    Storage::disk()->put('photos/old.jpg', 'photo');

    failContactDetailWrites();

    $thrown = false;

    try {
        Livewire::test(Form::class, ['person' => $member->person])
            ->set('contact_phone', '0899 111')
            ->call('clearPhoto')
            ->call('update');
    } catch (ContactDetailWriteFailed) {
        $thrown = true;
    } finally {
        restoreContactDetailWrites();
    }

    expect($thrown)->toBeTrue()
        ->and($member->person->fresh()->getRawOriginal('photo_url'))->toBe('photos/old.jpg')
        ->and(Storage::disk()->exists('photos/old.jpg'))->toBeTrue();
});

test('a failed invitation still removes a photo the member no longer uses', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();
    $member->person->update(['photo_url' => 'photos/old.jpg']);
    Storage::disk()->put('photos/old.jpg', 'photo');

    $this->mock(Dispatcher::class, function ($mock): void {
        $mock->shouldReceive('send')->andThrow(new RuntimeException('mail failed'));
    });

    $thrown = false;

    try {
        Livewire::test(Form::class, ['person' => $member->person])
            ->set('email', 'newer@example.test')
            ->call('clearPhoto')
            ->call('update');
    } catch (RuntimeException) {
        $thrown = true;
    }

    expect($thrown)->toBeTrue()
        ->and($member->person->fresh()->getRawOriginal('photo_url'))->toBeNull()
        ->and(Storage::disk()->exists('photos/old.jpg'))->toBeFalse();
});

test('a failed photo removal still sends the verification email', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    Notification::fake();

    $member = User::factory()->asMemberOf($organization)->create();
    $member->person->update(['photo_url' => 'photos/blocked']);

    Storage::disk()->makeDirectory('photos/blocked');

    Livewire::test(Form::class, ['person' => $member->person])
        ->set('email', 'newer@example.test')
        ->call('clearPhoto')
        ->call('update')
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');

    Notification::assertSentTo($member, VerifyEmailForNewUser::class);
});

test('creating a credential leaves the form ready for a photo retry', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    Notification::fake();

    $person = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $person, startDate: now());
    $person->update(['photo_url' => 'photos/blocked']);

    Storage::disk()->makeDirectory('photos/blocked');

    Livewire::test(Form::class, ['person' => $person])
        ->set('createCredential', true)
        ->set('email', 'credential@example.test')
        ->call('clearPhoto')
        ->call('update')
        ->assertSet('hasCredential', true)
        ->assertSet('createCredential', false);
});

test('retrying after a failed photo removal does not revert the role or email', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();
    $member->person->update(['photo_url' => 'photos/blocked']);

    Storage::disk()->makeDirectory('photos/blocked');

    $form = Livewire::test(Form::class, ['person' => $member->person]);

    OrganizationRelationship::query()
        ->forOrganization($organization)
        ->whereStakeholder($member->person)
        ->update(['role' => Role::Admin->value]);

    $member->update(['email' => 'newer@example.test']);

    $form->set('name', 'First Save')
        ->call('clearPhoto')
        ->call('update')
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');

    $form->set('name', 'Second Save')->call('update')->assertHasNoErrors();

    expect($member->person->fresh()?->roleIn($organization))->toBe(Role::Admin)
        ->and($member->fresh()->email)->toBe('newer@example.test');
});

test('a stale member save keeps a name saved by another session', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create(['name' => 'Old Name']);

    $form = Livewire::test(Form::class, ['person' => $member->person]);

    $member->person->update(['name' => 'Newer Name']);

    $form->set('contact_phone', '0899 333')->call('update')->assertHasNoErrors();

    expect($member->person->fresh()->name)->toBe('Newer Name');
});

test('a stale member save still succeeds when the opening email is taken', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();
    $oldEmail = $member->email;

    $form = Livewire::test(Form::class, ['person' => $member->person]);

    $member->update(['email' => 'newer@example.test']);
    User::factory()->create(['email' => $oldEmail]);

    $form->set('name', 'Renamed Member')->call('update')->assertHasNoErrors();

    expect($member->fresh()->email)->toBe('newer@example.test');
});

test('an after-transaction failure keeps the referenced staged photo', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();
    $member->person->update(['photo_url' => 'photos/old.jpg']);
    Storage::disk()->put('photos/old.jpg', 'photo');

    DB::shouldReceive('transaction')->once()->andReturnUsing(function (Closure $callback): void {
        $callback();

        throw new RuntimeException('after commit');
    });

    $thrown = false;

    try {
        Livewire::test(Form::class, ['person' => $member->person])
            ->set('name', 'Renamed Member')
            ->set('photo', UploadedFile::fake()->image('new.jpg'))
            ->call('update');
    } catch (RuntimeException) {
        $thrown = true;
    }

    $stored = $member->person->fresh()->getRawOriginal('photo_url');

    expect($thrown)->toBeTrue()
        ->and($stored)->not->toBe('photos/old.jpg')
        ->and(Storage::disk()->exists((string) $stored))->toBeTrue();
});

test('a stale create-credential form does not replace a credential added elsewhere', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $person = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $person, startDate: now());

    $form = Livewire::test(Form::class, ['person' => $person]);

    $other = User::factory()->create(['email' => 'other@example.test']);
    $person->update(['user_id' => $other->getKey()]);

    $form->set('createCredential', true)
        ->set('email', 'entered@example.test')
        ->call('update')
        ->assertHasNoErrors();

    expect($person->fresh()->user_id)->toBe($other->getKey())
        ->and($form->get('hasCredential'))->toBeTrue()
        ->and($form->get('createCredential'))->toBeFalse()
        ->and(User::query()->where('email', 'entered@example.test')->exists())->toBeFalse();
});

test('a credential attached during the save is preserved', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $person = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $person, startDate: now());

    $form = Livewire::test(Form::class, ['person' => $person]);

    $other = User::factory()->create();

    Person::updating(function (Person $updated) use ($other): void {
        $updated->user_id = $other->getKey();
    });

    try {
        $form->set('name', 'Renamed Member')
            ->set('createCredential', true)
            ->set('email', 'entered@example.test')
            ->call('update')
            ->assertHasNoErrors();
    } finally {
        Person::getEventDispatcher()->forget('eloquent.updating: '.Person::class);
    }

    expect($person->fresh()->user_id)->toBe($other->getKey())
        ->and(User::query()->where('email', 'entered@example.test')->exists())->toBeFalse();
});

test('a committed rename dispatches the list refresh when photo removal fails', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create(['name' => 'Old Name']);
    $member->person->update(['photo_url' => 'photos/blocked']);

    Storage::disk()->makeDirectory('photos/blocked');

    Livewire::test(Form::class, ['person' => $member->person])
        ->set('name', 'New Name')
        ->call('clearPhoto')
        ->call('update')
        ->assertDispatched('member-updated')
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');

    expect($member->person->fresh()->name)->toBe('New Name')
        ->and($member->person->fresh()->getRawOriginal('photo_url'))->toBe('photos/blocked');
});

test('the member photo path cannot be changed from the client', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();

    expect(fn () => Livewire::test(Form::class, ['person' => $member->person])
        ->set('photo_url', 'photos/other.jpg'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('a member photo can be removed', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();
    $member->person->update(['photo_url' => 'photos/old.jpg']);
    Storage::disk()->put('photos/old.jpg', 'photo');

    Livewire::test(Form::class, ['person' => $member->person])
        ->call('clearPhoto')
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->fresh()->getRawOriginal('photo_url'))->toBeNull();
    expect(Storage::disk()->exists('photos/old.jpg'))->toBeFalse();
});

test('the member photo path is kept when removal fails', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();
    $member->person->update(['photo_url' => 'photos/blocked']);

    // A directory at the object path cannot be unlinked, which forces the failure.
    Storage::disk()->makeDirectory('photos/blocked');

    Livewire::test(Form::class, ['person' => $member->person])
        ->call('clearPhoto')
        ->call('update')
        ->assertSet('photo_url', 'photos/blocked')
        ->assertNotDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'success')
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');

    expect($member->person->fresh()->getRawOriginal('photo_url'))->toBe('photos/blocked');
});

test('the edit member modal is a floating right-side flyout with a bottom footer', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $member = User::factory()->asMemberOf($organization)->create();

    Livewire::test(Form::class, ['person' => $member->person])
        ->assertSeeHtml('data-flux-flyout')
        ->assertSeeHtml('ml-auto')
        ->assertSeeHtml('rounded-xl')
        ->assertSeeHtml('class="-mb-6 flex flex-1 flex-col"')
        ->assertSeeHtml('sticky bottom-0')
        ->assertSeeHtml('mt-auto');
});

test('a member photo can be updated', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();

    Livewire::test(Form::class, ['person' => $member->person])
        ->set('photo', UploadedFile::fake()->image('updated.jpg'))
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->fresh()->getRawOriginal('photo_url'))->toContain('photos/');
});

test('a photo picked for a new member does not follow into an edit', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();
    $member->person->update(['photo_url' => 'photos/original.jpg']);

    Livewire::test(Form::class)
        ->call('openCreate')
        ->set('photo', UploadedFile::fake()->image('pending.jpg'))
        ->call('editMember', $member->person->getKey())
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->fresh()->getRawOriginal('photo_url'))->toBe('photos/original.jpg');
});

test('saving a member keeps a contact detail saved by another session', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    $form = Livewire::test(Form::class, ['person' => $member->person]);

    app(SaveContactDetails::class)->handle($member->person, [
        ContactDetailType::Email->value => 'newer@example.test',
    ]);

    $form->set('name', 'Renamed Member')->call('update')->assertHasNoErrors();

    expect($member->person->fresh()?->contactDetailValue(ContactDetailType::Email))->toBe('newer@example.test');
});

test('clearing a member contact detail deletes it', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();
    $member->person->contactDetails()->create([
        'type' => ContactDetailType::Email,
        'value' => 'clear@example.test',
    ]);

    Livewire::test(Form::class, ['person' => $member->person])
        ->set('contact_email', '')
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->fresh()?->contactDetailValue(ContactDetailType::Email))->toBeNull();
});

test('contact details stay editable when the profile is managed elsewhere', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->withProfile()->create();
    app(CreateMember::class)->attach($organization, $member->person, startDate: now(), role: Role::Member);

    Livewire::test(Form::class, ['person' => $member->person])
        ->set('contact_email', 'Reach@Example.Test')
        ->set('contact_phone', '0812 3456')
        ->call('update')
        ->assertHasNoErrors();

    $person = $member->person->fresh();

    expect($person?->contactDetailValue(ContactDetailType::Email))->toBe('reach@example.test')
        ->and($person?->contactDetailValue(ContactDetailType::Phone))->toBe('0812 3456')
        ->and($person?->name)->toBe($member->name);
});
