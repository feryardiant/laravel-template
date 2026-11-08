<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Models\Address;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
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

    expect(fn () => $organization->address()->create(Address::factory()->raw()))
        ->toThrow(QueryException::class);
});

it('reports a null relationship type as neither internal nor external', function () {
    $relationship = new OrganizationRelationship(['type' => null]);

    expect($relationship->is_internal)->toBeFalse()
        ->and($relationship->is_external)->toBeFalse();
});

it('removes inbound relationships when an organization is force-deleted', function () {
    $organization = Organization::factory()->create();
    $counterparty = Organization::factory()->create();

    $counterparty->organizationCounterparties()->attach($organization, [
        'type' => OrganizationRelationshipType::Customer,
    ]);

    $organization->forceDelete();

    expect(OrganizationRelationship::query()
        ->where('stakeholder_type', Organization::class)
        ->where('stakeholder_id', $organization->id)
        ->exists())->toBeFalse();
});

it('keeps inbound relationships when an organization is soft-deleted', function () {
    $organization = Organization::factory()->create();
    $counterparty = Organization::factory()->create();

    $counterparty->organizationCounterparties()->attach($organization, [
        'type' => OrganizationRelationshipType::Customer,
    ]);

    $organization->delete();

    expect(OrganizationRelationship::query()
        ->where('stakeholder_type', Organization::class)
        ->where('stakeholder_id', $organization->id)
        ->exists())->toBeTrue();
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

it('does not duplicate a membership when added twice', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();

    $organization->addMember($person);
    $organization->addMember($person);

    expect($organization->members()->count())->toBe(1);
});

it('promotes an existing member without duplicating them', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();

    $organization->addMember($person);
    $organization->addMember($person, isPrimary: true);

    $members = $organization->members()->get();

    expect($members)->toHaveCount(1)
        ->and($members->first()->member->is_primary)->toBeTrue();
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

it('returns organization and person counterparties together', function () {
    $organization = Organization::factory()->create();
    $relatedOrganization = Organization::factory()->create();
    $relatedPerson = Person::factory()->create();
    $supplier = Person::factory()->create();

    $organization->organizationCounterparties()->attach($relatedOrganization, [
        'type' => OrganizationRelationshipType::Customer,
    ]);
    $organization->personCounterparties()->attach($relatedPerson, [
        'type' => OrganizationRelationshipType::Customer,
    ]);
    $organization->personCounterparties()->attach($supplier, [
        'type' => OrganizationRelationshipType::Supplier,
    ]);

    $counterparties = $organization->counterparties();
    $customers = $organization->counterparties(OrganizationRelationshipType::Customer);

    expect($counterparties)->toHaveCount(3)
        ->and($counterparties->get(0)->is($relatedOrganization))->toBeTrue()
        ->and($counterparties->get(1)->is($relatedPerson))->toBeTrue()
        ->and($customers)->toHaveCount(2)
        ->and($customers->get(0)->is($relatedOrganization))->toBeTrue()
        ->and($customers->get(1)->is($relatedPerson))->toBeTrue();
});

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
