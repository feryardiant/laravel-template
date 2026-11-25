<?php

declare(strict_types=1);

use App\Actions\Contacts\SaveContactDetails;
use App\Actions\Memberships\CreateMember;
use App\Enums\ContactDetailType;
use App\Enums\Gender;
use App\Enums\Role;
use App\Livewire\Settings\Profile;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\Feature\ContactGender;
use App\Support\Feature\ProfileSelfEdit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;
use Tests\Support\ContactDetailWriteFailed;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('profile page is displayed', function () {
    actingAs($user = User::factory()->create());

    get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    actingAs($user = User::factory()->create());

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('profile summary and gender live on the person', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withProfile()->create();
    app(CreateMember::class)->attach($organization, $user->person, startDate: now());
    $user->person->update(['owner_organization_id' => $organization->getKey(), 'gender' => null]);

    ContactGender::activate($organization);

    actingAs($user);
    session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);

    Livewire::test(Profile::class)
        ->set('summary', 'A short bio')
        ->set('gender', Gender::Male->value)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->person?->summary)->toBe('A short bio')
        ->and($user->fresh()->person?->gender)->toBe(Gender::Male);
});

test('an org-owned profile is locked until the workspace enables self-edit', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withProfile()->create();
    $user->person->update(['owner_organization_id' => $organization->getKey()]);

    actingAs($user);

    Livewire::test(Profile::class)
        ->set('name', 'Tampered Name')
        ->set('email', 'tampered@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->person?->name)->not->toBe('Tampered Name')
        ->and($user->fresh()->email)->not->toBe('tampered@example.com');

    ProfileSelfEdit::activate($organization);

    Livewire::test(Profile::class)
        ->set('name', 'Updated Name')
        ->set('email', 'updated@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->person?->name)->toBe('Updated Name')
        ->and($user->fresh()->email)->toBe('updated@example.com');
});

test('gender is only editable once and follows the workspace flag', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withProfile()->create();
    app(CreateMember::class)->attach($organization, $user->person, startDate: now());
    $user->person->update([
        'owner_organization_id' => $organization->getKey(),
        'gender' => Gender::Male,
    ]);

    ContactGender::activate($organization);

    actingAs($user);
    session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);

    Livewire::test(Profile::class)
        ->assertSee(__('Gender'))
        ->assertSee(__('This field is set once and can no longer be changed.'))
        ->set('gender', Gender::Female->value)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->person?->gender)->toBe(Gender::Male);
});

test('profile handle can be updated', function () {
    actingAs($user = User::factory()->create());

    Livewire::test(Profile::class)
        ->set('handle', 'newhandle')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->handle)->toBe('newhandle');
});

test('profile handle must be unique', function () {
    actingAs($user = User::factory()->create());
    User::factory()->create(['handle' => 'takenhandle']);

    Livewire::test(Profile::class)
        ->set('handle', 'takenhandle')
        ->call('updateProfileInformation')
        ->assertHasErrors(['handle']);

    expect($user->refresh()->handle)->not->toBe('takenhandle');
});

test('profile handle must be lowercase alphanumeric and 4 to 15 characters', function (string $handle) {
    actingAs($user = User::factory()->create());

    Livewire::test(Profile::class)
        ->set('handle', $handle)
        ->call('updateProfileInformation')
        ->assertHasErrors(['handle']);

    expect($user->refresh()->handle)->not->toBe($handle);
})->with([
    'too short' => 'abc',
    'too long' => 'abcdefghijklmnop',
    'uppercase' => 'InvalidHandle',
    'punctuation' => 'abc-def',
    'empty' => '',
]);

test('email verification status is unchanged when email address is unchanged', function () {
    actingAs($user = User::factory()->create());

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    actingAs($user = User::factory()->create());

    $response = Livewire::test('settings.profile.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(Auth::check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    actingAs($user = User::factory()->create());

    $response = Livewire::test('settings.profile.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('the last owner cannot delete their account', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->asMemberOf($organization, Role::Owner, isPrimary: true)->create();

    actingAs($owner);

    Livewire::test('settings.profile.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors(['password']);

    expect($owner->fresh())->not->toBeNull();
    expect(Auth::check())->toBeTrue();
});

test('the user avatar can be uploaded', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    actingAs($user = User::factory()->create());

    Livewire::test(Profile::class)
        ->set('avatar', UploadedFile::fake()->image('avatar.jpg'))
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->person?->getRawOriginal('photo_url'))->toContain('avatars/');
    expect(Storage::disk()->allFiles('avatars'))->not->toBeEmpty();
});

test('the user avatar can be removed', function () {
    Storage::fake();

    $user = User::factory()->withProfile()->create();
    $user->person->update(['photo_url' => 'avatars/old.jpg']);
    Storage::disk()->put('avatars/old.jpg', 'avatar');

    actingAs($user);

    Livewire::test(Profile::class)
        ->call('clearAvatar')
        ->call('updateProfileInformation')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'success');

    expect($user->person->fresh()->getRawOriginal('photo_url'))->toBeNull();
    expect(Storage::disk()->exists('avatars/old.jpg'))->toBeFalse();
});

test('an avatar replacement keeps the new path when the old file cannot be deleted', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $user = User::factory()->withProfile()->create();
    $user->person->update(['photo_url' => 'avatars/blocked']);

    // A directory at the object path cannot be unlinked, which forces the failure.
    Storage::disk()->makeDirectory('avatars/blocked');

    actingAs($user);

    Livewire::test(Profile::class)
        ->set('avatar', UploadedFile::fake()->image('new.jpg'))
        ->call('updateProfileInformation')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'success');

    $stored = $user->person->fresh()?->getRawOriginal('photo_url');

    /** @disregard P1013 */
    expect($stored)->toContain('avatars/')
        ->and($stored)->not->toBe('avatars/blocked')
        ->and(Storage::disk()->exists((string) $stored))->toBeTrue()
        ->and(Storage::disk()->directoryExists('avatars/blocked'))->toBeTrue();
});

test('a stale profile save keeps an avatar saved by another session', function () {
    Storage::fake();

    $user = User::factory()->withProfile()->create();

    actingAs($user);

    $component = Livewire::test(Profile::class);

    Storage::disk()->put('avatars/new.jpg', 'avatar');
    $user->person->update(['photo_url' => 'avatars/new.jpg']);

    $component->set('handle', 'newhandle')->call('updateProfileInformation')->assertHasNoErrors();

    expect($user->person->fresh()?->getRawOriginal('photo_url'))->toBe('avatars/new.jpg')
        ->and(Storage::disk()->exists('avatars/new.jpg'))->toBeTrue();
});

test('a stale profile save does not revert a login email change', function () {
    $user = User::factory()->withProfile()->create(['email' => 'old@example.test']);

    actingAs($user);

    $component = Livewire::test(Profile::class);

    $user->update(['email' => 'newer@example.test']);

    $component->set('handle', 'newhandle')->call('updateProfileInformation')->assertHasNoErrors();

    expect($user->fresh()->email)->toBe('newer@example.test');
});

test('a stale profile save keeps a name and summary saved by another session', function () {
    $user = User::factory()->withProfile()->create();
    $user->person->update(['name' => 'Old Name', 'summary' => 'Old summary']);

    actingAs($user);

    $component = Livewire::test(Profile::class);

    $user->person->update(['name' => 'Newer Name', 'summary' => 'Newer summary']);

    $component->set('contactPhone', '0899 555')->call('updateProfileInformation')->assertHasNoErrors();

    $person = $user->person->fresh();

    expect($person?->name)->toBe('Newer Name')
        ->and($person?->summary)->toBe('Newer summary');
});

test('a stale profile save keeps a handle saved by another session', function () {
    $user = User::factory()->withProfile()->create();

    actingAs($user);

    $component = Livewire::test(Profile::class);

    $user->update(['handle' => 'newerhandle']);

    $component->set('contactPhone', '0899 666')->call('updateProfileInformation')->assertHasNoErrors();

    expect($user->fresh()->handle)->toBe('newerhandle');
});

test('a stale profile save still succeeds when the opening email is taken', function () {
    $user = User::factory()->withProfile()->create(['email' => 'own@example.test']);

    actingAs($user);

    $component = Livewire::test(Profile::class);

    $user->update(['email' => 'newer@example.test']);
    User::factory()->create(['email' => 'own@example.test']);

    $component->set('handle', 'newhandle')->call('updateProfileInformation')->assertHasNoErrors();

    expect($user->fresh()->email)->toBe('newer@example.test');
});

test('a stale profile save still succeeds when the opening handle is taken', function () {
    $user = User::factory()->withProfile()->create(['handle' => 'oldhandle']);

    actingAs($user);

    $component = Livewire::test(Profile::class);

    $user->update(['handle' => 'newerhandle']);
    User::factory()->create(['handle' => 'oldhandle']);

    $component->set('contactPhone', '0899 777')->call('updateProfileInformation')->assertHasNoErrors();

    expect($user->fresh()->handle)->toBe('newerhandle')
        ->and($user->fresh()->person?->contactDetailValue(ContactDetailType::Phone))->toBe('0899 777');
});

test('a failed profile save removes the staged avatar', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    actingAs($user = User::factory()->create());

    failContactDetailWrites();

    $thrown = false;

    try {
        Livewire::test(Profile::class)
            ->set('contactPhone', '0899 000')
            ->set('avatar', UploadedFile::fake()->image('avatar.jpg'))
            ->call('updateProfileInformation');
    } catch (ContactDetailWriteFailed) {
        $thrown = true;
    } finally {
        restoreContactDetailWrites();
    }

    expect($thrown)->toBeTrue()
        ->and(Storage::disk()->allFiles('avatars'))->toBe([])
        ->and($user->fresh()?->person?->contactDetailValue(ContactDetailType::Phone))->toBeNull();
});

test('a failed profile update keeps the previous avatar', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $user = User::factory()->withProfile()->create();
    $user->person->update(['photo_url' => 'avatars/old.jpg']);
    Storage::disk()->put('avatars/old.jpg', 'avatar');

    actingAs($user);

    failContactDetailWrites();

    $thrown = false;

    try {
        Livewire::test(Profile::class)
            ->set('contactPhone', '0899 000')
            ->set('avatar', UploadedFile::fake()->image('new.jpg'))
            ->call('updateProfileInformation');
    } catch (ContactDetailWriteFailed) {
        $thrown = true;
    } finally {
        restoreContactDetailWrites();
    }

    expect($thrown)->toBeTrue()
        ->and($user->person->fresh()->getRawOriginal('photo_url'))->toBe('avatars/old.jpg')
        ->and(Storage::disk()->allFiles('avatars'))->toBe(['avatars/old.jpg']);
});

test('a failed profile update keeps an avatar being removed', function () {
    Storage::fake();

    $user = User::factory()->withProfile()->create();
    $user->person->update(['photo_url' => 'avatars/old.jpg']);
    Storage::disk()->put('avatars/old.jpg', 'avatar');

    actingAs($user);

    failContactDetailWrites();

    $thrown = false;

    try {
        Livewire::test(Profile::class)
            ->set('contactPhone', '0899 000')
            ->call('clearAvatar')
            ->call('updateProfileInformation');
    } catch (ContactDetailWriteFailed) {
        $thrown = true;
    } finally {
        restoreContactDetailWrites();
    }

    expect($thrown)->toBeTrue()
        ->and($user->person->fresh()->getRawOriginal('photo_url'))->toBe('avatars/old.jpg')
        ->and(Storage::disk()->exists('avatars/old.jpg'))->toBeTrue();
});

test('the user avatar path is kept when removal fails', function () {
    Storage::fake();

    $user = User::factory()->withProfile()->create();
    $user->person->update(['photo_url' => 'avatars/blocked']);

    // A directory at the object path cannot be unlinked, which forces the failure.
    Storage::disk()->makeDirectory('avatars/blocked');

    actingAs($user);

    Livewire::test(Profile::class)
        ->call('clearAvatar')
        ->call('updateProfileInformation')
        ->assertSet('avatarUrl', 'avatars/blocked')
        ->assertNotDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'success')
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');

    expect($user->person->fresh()->getRawOriginal('photo_url'))->toBe('avatars/blocked');
});

test('the user avatar renders as a circle', function () {
    $user = User::factory()->create(['avatar_url' => 'avatars/avatar.jpg']);

    actingAs($user);

    get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('uploads', ['path' => 'avatars/avatar.jpg']))
        ->assertSeeHtml('data-circle="true"');
});

test('a non-image avatar is rejected', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    actingAs($user = User::factory()->create());

    Livewire::test(Profile::class)
        ->set('avatar', UploadedFile::fake()->create('avatar.pdf', 10, 'application/pdf'))
        ->call('updateProfileInformation')
        ->assertHasErrors(['avatar']);

    expect($user->fresh()->person?->getRawOriginal('photo_url'))->toBeNull();
});

test('saving the profile keeps a contact detail saved by another session', function () {
    actingAs($user = User::factory()->withProfile()->create());

    $component = Livewire::test(Profile::class);

    app(SaveContactDetails::class)->handle($user->person, [
        ContactDetailType::Phone->value => '0899 000',
    ]);

    $component->set('handle', 'newhandle')->call('updateProfileInformation')->assertHasNoErrors();

    expect($user->fresh()?->person?->contactDetailValue(ContactDetailType::Phone))->toBe('0899 000');
});

test('contact details are stored without changing the login email', function () {
    actingAs($user = User::factory()->create(['email' => 'login@example.test']));

    Livewire::test(Profile::class)
        ->set('contactEmail', 'Reach@Example.Test')
        ->set('contactPhone', '0812 3456')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $person = $user->fresh()?->person;

    expect($person?->contactDetailValue(ContactDetailType::Email))->toBe('reach@example.test')
        ->and($person?->contactDetailValue(ContactDetailType::Phone))->toBe('0812 3456')
        ->and($user->fresh()?->email)->toBe('login@example.test');
});
