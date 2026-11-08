<?php

declare(strict_types=1);

use App\Enums\OrganizationRelativeType;
use App\Models\Address;
use App\Models\Organization;
use App\Models\Person;

/**
 * Organization model tests.
 */
it('gets a stable uuid', function () {
    $org = Organization::factory()->create();
    $uuid = $org->uuid;

    $org->update(['name' => 'Renamed']);

    expect($uuid)
        ->not->toBeEmpty()
        ->toBe($org->fresh()->uuid);
});

it('can only have one address', function () {
    $organization = Organization::factory()->withAddress()->create();

    expect($organization->address)->toBeInstanceOf(Address::class);
});

it('can have members', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();

    $organization->addMember($person);

    $member = $organization->members()->first();

    expect($member)
        ->is($person)->toBeTrue()
        ->member->type->toBe(OrganizationRelativeType::Member);
});

test('external :dataset can be organizations or people', function (OrganizationRelativeType $type) {
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
    'customers' => [OrganizationRelativeType::Customer],
    'suppliers' => [OrganizationRelativeType::Supplier],
    'vendors' => [OrganizationRelativeType::Vendor],
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
