<?php

declare(strict_types=1);

use App\Enums\ContactDetailType;
use App\Enums\Role;
use App\Livewire\Members\Form;
use App\Models\Organization;
use App\Models\Person;
use App\Notifications\VerifyEmailForNewUser;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;
use Tests\Support\ContactDetailWriteFailed;

uses(RefreshDatabase::class);

test('an admin can add a person without a credential', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Form::class)->call('openCreate')
        ->set('name', 'No Credential')
        ->set('role', Role::Member->value)
        ->set('createCredential', false)
        ->call('create')
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

    Livewire::test(Form::class)->call('openCreate')
        ->set('name', 'Invited Member')
        ->set('email', 'invited@example.com')
        ->set('createCredential', true)
        ->set('role', Role::Member->value)
        ->call('create')
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

    $component = Livewire::test(Form::class)->call('openCreate');

    expect($component->instance()->roles())->toBe([Role::Admin, Role::Member]);

    $component->set('name', 'New Owner')
        ->set('role', Role::Owner->value)
        ->call('create')
        ->assertHasErrors('role');

    expect(Person::query()->where('name', 'New Owner')->exists())->toBeFalse();
});

test('a plain member cannot add members', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    Livewire::test(Form::class)
        ->call('create')
        ->assertForbidden();

    expect(Person::query()->where('name', 'Forbidden Member')->exists())->toBeFalse();
});

test('an admin can add a member with a photo', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Form::class)->call('openCreate')
        ->set('name', 'Photo Member')
        ->set('role', Role::Member->value)
        ->set('photo', UploadedFile::fake()->image('member.jpg'))
        ->call('create')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'Photo Member')->firstOrFail();

    expect($person->getRawOriginal('photo_url'))->toContain('photos/');
    expect(Storage::disk()->allFiles('photos'))->not->toBeEmpty();
});

test('a failed invitation keeps the created member photo', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $this->mock(Dispatcher::class, function ($mock): void {
        $mock->shouldReceive('send')->andThrow(new RuntimeException('mail failed'));
    });

    $thrown = false;

    try {
        Livewire::test(Form::class)->call('openCreate')
            ->set('name', 'Invited Member')
            ->set('role', Role::Member->value)
            ->set('createCredential', true)
            ->set('email', 'invite@example.test')
            ->set('photo', UploadedFile::fake()->image('member.jpg'))
            ->call('create');
    } catch (RuntimeException) {
        $thrown = true;
    }

    $person = Person::query()->where('name', 'Invited Member')->first();
    $photo = $person?->getRawOriginal('photo_url');

    expect($thrown)->toBeTrue()
        ->and($photo)->not->toBeNull()
        ->and(Storage::disk()->exists((string) $photo))->toBeTrue();
});

test('a failed member create removes the uploaded photo', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    failContactDetailWrites();

    $thrown = false;

    try {
        Livewire::test(Form::class)->call('openCreate')
            ->set('name', 'Failed Member')
            ->set('role', Role::Member->value)
            ->set('contact_email', 'fail@example.test')
            ->set('photo', UploadedFile::fake()->image('member.jpg'))
            ->call('create');
    } catch (ContactDetailWriteFailed) {
        $thrown = true;
    } finally {
        restoreContactDetailWrites();
    }

    expect($thrown)->toBeTrue()
        ->and(Storage::disk()->allFiles('photos'))->toBe([])
        ->and(Person::query()->where('name', 'Failed Member')->exists())->toBeFalse();
});

test('an admin can add a member with contact details', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Form::class)->call('openCreate')
        ->set('name', 'Contactable Member')
        ->set('role', Role::Member->value)
        ->set('contact_email', 'Reach@Example.Test')
        ->set('contact_phone', '0812 3456')
        ->call('create')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'Contactable Member')->firstOrFail();

    expect($person->user_id)->toBeNull()
        ->and($person->contactDetailValue(ContactDetailType::Email))->toBe('reach@example.test')
        ->and($person->contactDetailValue(ContactDetailType::Phone))->toBe('0812 3456');
});
