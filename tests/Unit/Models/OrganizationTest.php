<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Models\Address;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\QueryException;

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
        ->member->type->toBe(OrganizationRelationshipType::Member);
});

test('external counterparties can be organizations or people', function (OrganizationRelationshipType $type) {
    $organization = Organization::factory()->create();
    $relatedOrganization = Organization::factory()->create();
    $relatedPerson = Person::factory()->create();

    $organization->organizationCounterparties()->attach($relatedOrganization, ['type' => $type]);
    $organization->personCounterparties()->attach($relatedPerson, ['type' => $type]);

    expect($organization->organizationCounterparties($type)->get()->pluck('id')->all())
        ->toContain($relatedOrganization->id)
        ->and($organization->personCounterparties($type)->get()->pluck('id')->all())
        ->toContain($relatedPerson->id);
})->with([
    'customers' => [OrganizationRelationshipType::Customer],
    'suppliers' => [OrganizationRelationshipType::Supplier],
    'vendors' => [OrganizationRelationshipType::Vendor],
]);

it('allows counterparty codes to be reused by different organizations only', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $vendor = Organization::factory()->create();

    $organization->organizationCounterparties()->attach($vendor, [
        'type' => OrganizationRelationshipType::Vendor,
        'code' => 'V-001',
    ]);
    $otherOrganization->organizationCounterparties()->attach(Organization::factory()->create(), [
        'type' => OrganizationRelationshipType::Vendor,
        'code' => 'V-001',
    ]);

    expect(fn () => $organization->personCounterparties()->attach(Person::factory()->create(), [
        'type' => OrganizationRelationshipType::Customer,
        'code' => 'V-001',
    ]))->toThrow(QueryException::class);
});

it('moves a persons primary organization when they join another as primary', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $organization->addMember($person, isPrimary: true);
    $otherOrganization->addMember($person, isPrimary: true);

    $primaryOrganizations = $person->organizationRelationships()
        ->wherePivot('type', OrganizationRelationshipType::Member)
        ->wherePivot('is_primary', true)
        ->get();

    expect($primaryOrganizations)->toHaveCount(1)
        ->and($primaryOrganizations->first()->is($otherOrganization))->toBeTrue()
        ->and($organization->members()->first()->member->is_primary)->toBeNull();
});

test('subsidiaries must be organizations', function () {
    $organization = Organization::factory()->create();
    $subsidiary = Organization::factory()->create();

    $organization->subsidiaries()->attach($subsidiary, ['type' => OrganizationRelationshipType::Subsidiary]);

    expect($organization->subsidiaries->first()->is($subsidiary))->toBeTrue();

    expect(fn () => $organization->personCounterparties(OrganizationRelationshipType::Subsidiary)->attach(
        Person::factory()->create(),
        ['type' => OrganizationRelationshipType::Subsidiary],
    ))->toThrow(InvalidArgumentException::class);
});

test('members must be people', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();

    $organization->addMember($person);

    expect($organization->members->first()->is($person))->toBeTrue();

    expect(fn () => $organization->organizationCounterparties()->attach(
        Organization::factory()->create(),
        ['type' => OrganizationRelationshipType::Member],
    ))->toThrow(InvalidArgumentException::class);
});
