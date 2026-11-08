<?php

declare(strict_types=1);

/**
 * Entity model relationship tests.
 */

use App\Enums\OrganizationRelativeType;
use App\Enums\PersonRelativeType;
use App\Models\Address;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PersonRelative;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

test('entities get a stable uuid', function () {
    $person = Person::factory()->create();
    $uuid = $person->uuid;

    $person->update(['name' => 'Renamed']);

    expect($uuid)
        ->not->toBeEmpty()
        ->toBe($person->fresh()->uuid);
});

test('people own organizations', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create(['owner_id' => $person->id]);

    expect($person->organizations->first()->is($organization))->toBeTrue();
});

test('people can be related to each other', function () {
    $person = Person::factory()->create();
    $relative = Person::factory()->create();

    $person->addPersonalRelative($relative, PersonRelativeType::Sibling);

    expect($person->personalRelative->first())
        ->relation->toBeInstanceOf(PersonRelative::class)
        ->relation->type->toBe(PersonRelativeType::Sibling);
});

test('organizations can have members', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();

    $organization->addMember($person);

    $member = $organization->members()->first();

    expect($member)
        ->is($person)->toBeTrue()
        ->member->type->toBe(OrganizationRelativeType::Member);
});

test('people have many addresses', function () {
    $person = Person::factory()->withAddresses(2)->create();

    expect($person)
        ->addresses()->toBeInstanceOf(MorphMany::class)
        ->addresses()->count()->toBe(2)
        ->addresses->toBeInstanceOf(Collection::class)
        ->addresses->count()->toBe(2)
        ->addresses->first()->toBeInstanceOf(Address::class);
});

test('organization can only have one address', function () {
    $organization = Organization::factory()->withAddress()->create();

    expect($organization)
        ->address()->toBeInstanceOf(MorphOne::class)
        ->address()->count()->toBe(1)
        ->address->toBeInstanceOf(Address::class);
});

test('people can join many organizations but only have one primary organization', function () {
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

    $second->addMember(Person::factory()->create(), isPrimary: true);

    expect($second->members()->wherePivot('is_primary', true)->count())->toBe(2);
});

test('external relatives can be organizations or people', function (OrganizationRelativeType $type) {
    $organization = Organization::factory()->create();
    $relatedOrganization = Organization::factory()->create();
    $relatedPerson = Person::factory()->create();

    $organization->organizationalRelatives()->attach($relatedOrganization, ['type' => $type]);
    $organization->individualRelatives()->attach($relatedPerson, ['type' => $type]);

    expect($organization->organizationalRelatives($type)->get()->pluck('id')->all())
        ->toContain($relatedOrganization->id)
        ->and($organization->individualRelatives($type)->get()->pluck('id')->all())
        ->toContain($relatedPerson->id);
})->with([
    'customer' => [OrganizationRelativeType::Customer],
    'supplier' => [OrganizationRelativeType::Supplier],
    'vendor' => [OrganizationRelativeType::Vendor],
]);

test('subsidiaries must be organizations', function () {
    $organization = Organization::factory()->create();
    $subsidiary = Organization::factory()->create();

    $organization->subsidiaries()->attach($subsidiary, ['type' => OrganizationRelativeType::Subsidiary]);

    expect($organization->subsidiaries->first()->is($subsidiary))->toBeTrue();

    expect(fn () => $organization->individualRelatives(OrganizationRelativeType::Subsidiary)->attach(
        Person::factory()->create(),
        ['type' => OrganizationRelativeType::Subsidiary],
    ))->toThrow(InvalidArgumentException::class);
});

test('members must be people', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();

    $organization->addMember($person);

    expect($organization->members->first()->is($person))->toBeTrue();

    expect(fn () => $organization->organizationalRelatives()->attach(
        Organization::factory()->create(),
        ['type' => OrganizationRelativeType::Member],
    ))->toThrow(InvalidArgumentException::class);
});
