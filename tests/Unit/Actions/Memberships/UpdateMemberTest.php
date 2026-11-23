<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Actions\Memberships\UpdateMember;
use App\Enums\ContactDetailType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('updates the member profile, details, and role', function () {
    $organization = Organization::factory()->create();
    $actor = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $person = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $person, startDate: now(), role: Role::Member);

    $result = app(UpdateMember::class)->handle(
        organization: $organization,
        person: $person,
        actor: $actor,
        data: [
            'name' => 'Renamed Member',
            'email' => '',
            'role' => Role::Admin,
            'gender' => null,
            'address' => null,
            'details' => [ContactDetailType::Email->value => 'member@example.com'],
            'create_credential' => false,
            'can_edit_profile' => true,
            'remove_photo' => false,
        ],
        expected: [
            'name' => $person->name,
            'email' => '',
            'role' => Role::Member->value,
            'address' => null,
            'details' => [],
            'photo_url' => null,
            'has_credential' => false,
        ],
    );

    expect($result['person']->name)->toBe('Renamed Member')
        ->and($result['person']->roleIn($organization))->toBe(Role::Admin)
        ->and($result['person']->contactDetailValue(ContactDetailType::Email))->toBe('member@example.com')
        ->and($result['email_changed'])->toBeFalse()
        ->and($result['credential_created'])->toBeFalse();
});

it('creates a credential and sends the verification email', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $actor = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $person = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $person, startDate: now(), role: Role::Member);

    $result = app(UpdateMember::class)->handle(
        organization: $organization,
        person: $person,
        actor: $actor,
        data: [
            'name' => $person->name,
            'email' => 'credential@example.com',
            'role' => Role::Member,
            'gender' => null,
            'address' => null,
            'details' => [],
            'create_credential' => true,
            'can_edit_profile' => true,
            'remove_photo' => false,
        ],
        expected: [
            'name' => $person->name,
            'email' => '',
            'role' => Role::Member->value,
            'address' => null,
            'details' => [],
            'photo_url' => null,
            'has_credential' => false,
        ],
    );

    expect($result['credential_created'])->toBeTrue()
        ->and($result['person']->user?->email)->toBe('credential@example.com');

    Notification::assertSentTo($result['person']->user, VerifyEmailForNewUser::class);
});

it('refuses to change the role of an inactive membership', function () {
    $organization = Organization::factory()->create();
    $actor = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $person = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $person, startDate: now()->addDay(), role: Role::Admin);

    expect(fn () => app(UpdateMember::class)->handle(
        organization: $organization,
        person: $person,
        actor: $actor,
        data: [
            'name' => $person->name,
            'email' => '',
            'role' => Role::Member,
            'gender' => null,
            'address' => null,
            'details' => [],
            'create_credential' => false,
            'can_edit_profile' => true,
            'remove_photo' => false,
        ],
        expected: [
            'name' => $person->name,
            'email' => '',
            'role' => Role::Admin->value,
            'address' => null,
            'details' => [],
            'photo_url' => null,
            'has_credential' => false,
        ],
    ))->toThrow(ValidationException::class);
});
