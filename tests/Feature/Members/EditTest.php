<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Enums\Role;
use App\Livewire\Members\Edit;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('editing a member updates the person and the per-organization role', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(Edit::class, ['person' => $member->person])
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

    Livewire::test(Edit::class, ['person' => $person])
        ->set('createCredential', true)
        ->set('email', 'credential@example.com')
        ->call('update')
        ->assertHasNoErrors();

    $person->refresh();

    expect($person->user)->not->toBeNull();
    expect($person->user->email)->toBe('credential@example.com');

    Notification::assertSentTo($person->user, VerifyEmailForNewUser::class);
});

test('an admin cannot edit an owner', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();

    Livewire::test(Edit::class, ['person' => $owner->person])
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

    Livewire::test(Edit::class, ['person' => $member->person])
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

    Livewire::test(Edit::class, ['person' => $member->person])
        ->set('email', 'renamed@example.com')
        ->call('update')
        ->assertHasNoErrors();

    expect($member->fresh()->email)->toBe('renamed@example.com');
});

test('the member photo path cannot be changed from the client', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();

    expect(fn () => Livewire::test(Edit::class, ['person' => $member->person])
        ->set('photo_url', 'photos/other.jpg'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('the edit member modal is a floating right-side flyout with a bottom footer', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $member = User::factory()->asMemberOf($organization)->create();

    Livewire::test(Edit::class, ['person' => $member->person])
        ->assertSeeHtml('data-flux-flyout')
        ->assertSeeHtml('ml-auto')
        ->assertSeeHtml('rounded-xl')
        ->assertSeeHtml('min-h-[calc(100dvh-5rem)]')
        ->assertSeeHtml('mt-auto');
});

test('a member photo can be updated', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();

    Livewire::test(Edit::class, ['person' => $member->person])
        ->set('photo', UploadedFile::fake()->image('updated.jpg'))
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->fresh()->getRawOriginal('photo_url'))->toContain('photos/');
});
