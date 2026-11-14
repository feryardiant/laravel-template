<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Members\Index;
use App\Models\Organization;
use App\Models\Person;
use App\Notifications\VerifyEmailForNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('an admin can add a person without a credential', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Index::class)
        ->set('name', 'No Credential')
        ->set('role', Role::Member->value)
        ->set('createCredential', false)
        ->call('createMember')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'No Credential')->firstOrFail();

    expect($person->user_id)->toBeNull();
    expect($organization->members()->whereKey($person->getKey())->exists())->toBeTrue();
    expect($person->roleIn($organization))->toBe(Role::Member);
});

test('an admin can add a member with an invite credential', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Index::class)
        ->set('name', 'Invited Member')
        ->set('email', 'invited@example.com')
        ->set('createCredential', true)
        ->set('role', Role::Member->value)
        ->call('createMember')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'Invited Member')->firstOrFail();
    $user = $person->user;

    expect($user)->not->toBeNull();
    expect($user->email)->toBe('invited@example.com');
    expect($user->email_verified_at)->toBeNull();
    expect($person->roleIn($organization))->toBe(Role::Member);

    Notification::assertSentTo($user, VerifyEmailForNewUser::class, function (VerifyEmailForNewUser $notification) use ($user): bool {
        return str_contains((string) $notification->toMail($user)->render(), $user->handle);
    });
});

test('the owner role cannot be assigned when adding a member', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Index::class)
        ->set('name', 'New Owner')
        ->set('role', Role::Owner->value)
        ->call('createMember')
        ->assertHasErrors('role');

    expect(Person::query()->where('name', 'New Owner')->exists())->toBeFalse();
});

test('a plain member cannot add members', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    Livewire::test(Index::class)
        ->set('name', 'Forbidden Member')
        ->set('role', Role::Member->value)
        ->call('createMember')
        ->assertForbidden();

    expect(Person::query()->where('name', 'Forbidden Member')->exists())->toBeFalse();
});

test('an admin can add a member with a photo', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Index::class)
        ->set('name', 'Photo Member')
        ->set('role', Role::Member->value)
        ->set('photo', UploadedFile::fake()->image('member.jpg'))
        ->call('createMember')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'Photo Member')->firstOrFail();

    expect($person->getRawOriginal('photo_url'))->toContain('photos/');
    expect(Storage::disk()->allFiles('photos'))->not->toBeEmpty();
});
