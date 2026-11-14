<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
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

    $response = Livewire::test('settings.delete-user-form')
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

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('the last owner cannot delete their account', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->asMemberOf($organization, Role::Owner, isPrimary: true)->create();

    actingAs($owner);

    Livewire::test('settings.delete-user-form')
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
