<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Models\Address;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

it('can only have one root tenant', function () {
    Organization::factory()->asRootTenant()->create();

    expect(fn () => Organization::factory()->asRootTenant()->create())
        ->toThrow(QueryException::class);
});

it('reports a null relationship type as neither internal nor external', function () {
    $relationship = new OrganizationRelationship(['type' => null]);

    expect($relationship->is_internal)->toBeFalse();
    expect($relationship->is_external)->toBeFalse();
});

it('removes inbound relationships when an organization is force-deleted', function () {
    $organization = Organization::factory()->create();
    $counterparty = Organization::factory()->create();

    $counterparty->organizationCounterparties()->attach($organization, [
        'type' => OrganizationRelationshipType::Customer,
    ]);

    $organization->forceDelete();

    expect(OrganizationRelationship::whereStakeholder($organization)->exists())->toBeFalse();
});

it('cannot force-delete a root tenant that still has tenant children', function () {
    $root = Organization::factory()->asRootTenant()->create();
    Organization::factory()->asTenantOf($root)->create();
    Organization::factory()->asTenantOf($root)->create();

    $counterparty = Organization::factory()->create();

    $counterparty->organizationCounterparties()->attach($root, [
        'type' => OrganizationRelationshipType::Customer,
    ]);

    expect(fn () => $root->forceDelete())->toThrow(QueryException::class);

    expect(Organization::query()->whereKey($root->getKey())->exists())->toBeTrue();
    expect(OrganizationRelationship::whereStakeholder($root)->exists())->toBeTrue();
});

it('force-deletes a childless root tenant and its inbound relationships', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $counterparty = Organization::factory()->create();

    $counterparty->organizationCounterparties()->attach($root, [
        'type' => OrganizationRelationshipType::Customer,
    ]);

    $root->forceDelete();

    expect(Organization::query()->whereKey($root->getKey())->exists())->toBeFalse();
    expect(OrganizationRelationship::whereStakeholder($root)->exists())->toBeFalse();
});

it('keeps inbound relationships when an organization is soft-deleted', function () {
    $organization = Organization::factory()->create();
    $counterparty = Organization::factory()->create();

    $counterparty->organizationCounterparties()->attach($organization, [
        'type' => OrganizationRelationshipType::Customer,
    ]);

    $organization->delete();

    expect(OrganizationRelationship::whereStakeholder($organization)->exists())->toBeTrue();
});

test('external counterparties can be organizations or people', function (OrganizationRelationshipType $type) {
    $organization = Organization::factory()->create();
    $relatedOrganization = Organization::factory()->create();
    $relatedPerson = Person::factory()->create();

    $organization->organizationCounterparties()->attach($relatedOrganization, ['type' => $type]);
    $organization->personCounterparties()->attach($relatedPerson, ['type' => $type]);

    expect($organization->organizationCounterparties($type)->get()->pluck('id')->all())
        ->toContain($relatedOrganization->id);
    expect($organization->personCounterparties($type)->get()->pluck('id')->all())
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

    expect($counterparties)->toHaveCount(3);
    expect($counterparties->get(0)->is($relatedOrganization))->toBeTrue();
    expect($counterparties->get(1)->is($relatedPerson))->toBeTrue();
    expect($customers)->toHaveCount(2);
    expect($customers->get(0)->is($relatedOrganization))->toBeTrue();
    expect($customers->get(1)->is($relatedPerson))->toBeTrue();
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

test('subsidiary relationships default to the subsidiary kind', function () {
    $parent = Organization::factory()->create();
    $child = Organization::factory()->create();

    $parent->subsidiaries()->attach($child, ['type' => OrganizationRelationshipType::Subsidiary]);

    $relationship = OrganizationRelationship::query()->whereStakeholder($child)->firstOrFail();

    expect($relationship->kind)->toBe(SubsidiaryKind::Subsidiary);
});

test('non-subsidiary relationships have no kind', function () {
    $organization = Organization::factory()->create();
    $customer = Organization::factory()->create();

    $organization->organizationCounterparties()->attach($customer, [
        'type' => OrganizationRelationshipType::Customer,
        'kind' => SubsidiaryKind::Branch,
    ]);

    $relationship = OrganizationRelationship::query()->whereStakeholder($customer)->firstOrFail();

    expect($relationship->kind)->toBeNull();
});

test('a child organization can only have one parent', function () {
    $firstParent = Organization::factory()->create();
    $secondParent = Organization::factory()->create();
    $child = Organization::factory()->create();

    $firstParent->subsidiaries()->attach($child, ['type' => OrganizationRelationshipType::Subsidiary]);

    expect(fn () => $secondParent->subsidiaries()->attach($child, [
        'type' => OrganizationRelationshipType::Subsidiary,
    ]))->toThrow(QueryException::class);
});

test('an organization can only have one owner', function () {
    $organization = Organization::factory()->create();

    $organization->members()->attach(Person::factory()->create(), [
        'type' => OrganizationRelationshipType::Member,
        'role' => Role::Owner,
        'start_date' => now(),
    ]);

    expect(fn () => $organization->members()->attach(Person::factory()->create(), [
        'type' => OrganizationRelationshipType::Member,
        'role' => Role::Owner,
        'start_date' => now(),
    ]))->toThrow(QueryException::class);
});

test('an organization factory state attaches a counterparty to the given organization', function () {
    $root = Organization::factory()->create();

    $customer = Organization::factory()
        ->asCustomerOf($root)
        ->create(['name' => 'Customer Org']);

    expect($root->organizationCounterparties()->whereKey($customer->getKey())->exists())->toBeTrue();
    expect($customer->organizationCounterparties()->whereKey($root->getKey())->exists())->toBeFalse();
});
