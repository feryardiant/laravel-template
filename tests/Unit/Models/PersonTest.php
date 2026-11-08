<?php

declare(strict_types=1);

use App\Enums\OrganizationRelativeType;
use App\Enums\PersonRelativeType;
use App\Models\Address;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PersonRelative;
use Illuminate\Database\Eloquent\Collection;

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

it('can have relatives', function () {
    $person = Person::factory()->create();
    $relative = Person::factory()->create();

    $person->addPersonalRelative($relative, PersonRelativeType::Sibling);

    expect($person->personalRelative->first())
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

it('can own organizations', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create(['owner_id' => $person->id]);

    expect($person->organizations->first()->is($organization))->toBeTrue();
});

it('can join many organizations but only have one primary organization', function () {
    $person = Person::factory()->create();
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();
    $third = Organization::factory()->create();

    $first->addMember($person);
    $second->addMember($person, isPrimary: true);
    $third->addMember($person);

    $primaries = $person->organizationalRelatives()
        ->wherePivot('type', OrganizationRelativeType::Member)
        ->wherePivot('is_primary', true)
        ->get();

    expect($person->organizationalRelatives()->wherePivot('type', OrganizationRelativeType::Member)->count())->toBe(3)
        ->and($primaries)->toHaveCount(1)
        ->and($primaries->first()->is($second))->toBeTrue();

    $third->addMember($person, isPrimary: true);

    $primaries = $person->organizationalRelatives()
        ->wherePivot('type', OrganizationRelativeType::Member)
        ->wherePivot('is_primary', true)
        ->get();

    expect($primaries)->toHaveCount(1)
        ->and($primaries->first()->is($third))->toBeTrue();

    $second->addMember(Person::factory()->create(), isPrimary: true);
    $second->addMember(Person::factory()->create(), isPrimary: true);

    expect($second->members()->wherePivot('is_primary', true)->count())->toBe(2);
});
