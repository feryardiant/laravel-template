<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Settings\Profile;
use App\Models\Organization;
use App\Models\User;
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

    expect($user->getRawOriginal('avatar_url'))->toContain('avatars/');
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

    expect($user->fresh()->getRawOriginal('avatar_url'))->toBeNull();
});
