<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\PersonRelativeType;
use App\Models\Address;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PersonRelative;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

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

    expect($person->user_id)->toBeNull()
        ->and($person->user)->toBeNull();
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

    expect($person->fresh()->user_id)->toBeNull()
        ->and($organization->fresh())->toBeInstanceOf(Organization::class);
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

    expect($organization->primaryContact->is($person))->toBeTrue()
        ->and($person->primaryContactOrganizations->first()->is($organization))->toBeTrue();
});

it('prevents deleting a primary contact while organizations reference them', function () {
    $person = Person::factory()->create();
    Organization::factory()->create(['contact_id' => $person->id]);

    expect(fn () => $person->forceDelete())->toThrow(QueryException::class);
});

it('can join many organizations with only one primary organization', function () {
    $person = Person::factory()->create();
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();
    $third = Organization::factory()->create();

    $first->addMember($person);
    $second->addMember($person, isPrimary: true);
    $third->addMember($person);

    $primaries = $person->organizationRelationships()
        ->wherePivot('type', OrganizationRelationshipType::Member)
        ->wherePivot('is_primary', true)
        ->get();

    expect($person->organizationRelationships()->wherePivot('type', OrganizationRelationshipType::Member)->count())->toBe(3)
        ->and($primaries)->toHaveCount(1)
        ->and($primaries->first()->is($second))->toBeTrue();

    $second->addMember(Person::factory()->create(), isPrimary: true);
    $second->addMember(Person::factory()->create(), isPrimary: true);

    expect($second->members()->wherePivot('is_primary', true)->count())->toBe(3);
});
