<?php

declare(strict_types=1);

use App\Enums\PersonRelativeType;
use App\Models\Address;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PersonRelative;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Person model tests.
 */
it('gets a stable uuid', function () {
    $person = Person::factory()->create();
    $uuid = $person->uuid;

    $person->update(['name' => 'Renamed']);

    expect($uuid)
        ->not->toBeEmpty()
        ->toBe($person->fresh()->uuid);
});

it('can exist without a credential', function () {
    $person = Person::factory()->withoutCredential()->create();

    expect($person->user_id)->toBeNull();
    expect($person->user)->toBeNull();
});

it('allows only one person per credential', function () {
    $user = User::factory()->create();

    Person::factory()->create(['user_id' => $user->id]);

    expect(fn () => Person::factory()->create(['user_id' => $user->id]))
        ->toThrow(QueryException::class);
});

it('keeps a person and their primary-contact organizations when their credential is deleted', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create(['contact_id' => $person->id]);

    $person->user->delete();

    expect($person->fresh()->user_id)->toBeNull();
    expect($organization->fresh())->toBeInstanceOf(Organization::class);
});

it('can have relatives', function () {
    $person = Person::factory()->create();
    $relative = Person::factory()->create();

    $person->addRelative($relative, PersonRelativeType::Sibling);

    expect($person->relatives->first())
        ->relation->toBeInstanceOf(PersonRelative::class)
        ->relation->type->toBe(PersonRelativeType::Sibling);
});

it('have many addresses', function () {
    $person = Person::factory()->withAddresses(2)->create();

    expect($person)
        ->addresses->toBeInstanceOf(Collection::class)
        ->addresses->count()->toBe(2)
        ->addresses->first()->toBeInstanceOf(Address::class);
});

it('can be the primary contact for organizations', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create(['contact_id' => $person->id]);

    expect($organization->primaryContact->is($person))->toBeTrue();
    expect($person->primaryContactOrganizations->first()->is($organization))->toBeTrue();
});

it('resolves a primary contact after it is soft-deleted', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create(['contact_id' => $person->id]);

    $person->delete();

    expect($organization->fresh()->primaryContact->is($person))->toBeTrue();
});

it('gets a new uuid when replicated', function () {
    $person = Person::factory()->withoutCredential()->create();

    $replica = $person->replicate();
    $replica->save();

    expect($replica->uuid)
        ->not->toBe($person->uuid);
    expect($replica->fresh()->uuid)->toBe($replica->uuid);
});

it('prevents deleting a primary contact while organizations reference them', function () {
    $person = Person::factory()->create();
    Organization::factory()->create(['contact_id' => $person->id]);

    expect(fn () => $person->forceDelete())->toThrow(QueryException::class);
});
