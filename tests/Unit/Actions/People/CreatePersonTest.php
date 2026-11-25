<?php

declare(strict_types=1);

use App\Actions\People\CreatePerson;
use App\Enums\Gender;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a person without a credential', function () {
    $person = app(CreatePerson::class)->handle('Jane Doe');

    expect($person)->toBeInstanceOf(Person::class)
        ->and($person->user_id)->toBeNull()
        ->and($person->name)->toBe('Jane Doe');
});

it('creates a credential from the email', function () {
    $person = app(CreatePerson::class)->handle('Jane Doe', email: 'jane@example.com');

    expect($person->user)->toBeInstanceOf(User::class)
        ->and($person->user->email)->toBe('jane@example.com');
});

it('uses the given user instead of creating a credential', function () {
    $user = User::factory()->withoutProfile()->create();

    $person = app(CreatePerson::class)->handle(
        'Jane Doe',
        email: 'ignored@example.com',
        user: $user,
    );

    expect($person->user_id)->toBe($user->getKey())
        ->and(User::query()->where('email', 'ignored@example.com')->exists())->toBeFalse();
});

it('stores the profile attributes and owner', function () {
    $owner = Organization::factory()->create();

    $person = app(CreatePerson::class)->handle(
        'Jane Doe',
        gender: Gender::Female,
        photoPath: 'photos/jane.jpg',
        summary: 'Summary',
        owner: $owner,
    );

    expect($person->gender)->toBe(Gender::Female)
        ->and($person->getRawOriginal('photo_url'))->toBe('photos/jane.jpg')
        ->and($person->summary)->toBe('Summary')
        ->and($person->owner_organization_id)->toBe($owner->getKey());
});
