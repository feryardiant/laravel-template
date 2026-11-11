<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * AddMember action tests.
 */
it('can have members', function () {
    $organization = Organization::factory()
        ->withMember($person = Person::factory()->create())
        ->create();

    $member = $organization->members()->first();

    expect($member)
        ->is($person)->toBeTrue()
        ->pivot->type->toBe(OrganizationRelationshipType::Member)
        ->pivot->role->toBe(Role::Member);
});

it('assigns the membership role', function () {
    $organization = Organization::factory()
        ->withMember($person = Person::factory()->create(), role: Role::Admin)
        ->create();

    expect($organization->members()->first()->pivot->role)->toBe(Role::Admin);
    expect($person->roleIn($organization))->toBe(Role::Admin);
});

it('updates the membership role when an existing member is added again', function () {
    $organization = Organization::factory()
        ->withMember($person = Person::factory()->create(), role: Role::Member)
        ->create();

    app(CreateMember::class)->attach($organization, $person, role: Role::Owner);

    expect($organization->members()->count())->toBe(1);
    expect($person->roleIn($organization))->toBe(Role::Owner);
});

it('does not assign a role to non-member relationships', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()
        ->withCounterparty($person, OrganizationRelationshipType::Customer)
        ->create();

    $relationship = $organization->personCounterparties(OrganizationRelationshipType::Customer)
        ->whereKey($person->getKey())
        ->first();

    expect($relationship?->stakeholder->role)->toBeNull();
});

it('does not duplicate a membership when added twice', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();

    app(CreateMember::class)->attach($organization, $person);
    app(CreateMember::class)->attach($organization, $person);

    expect($organization->members()->count())->toBe(1);
});

it('keeps an existing counterparty relationship when adding a member', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()
        ->withCounterparty($person, OrganizationRelationshipType::Customer)
        ->create();

    app(CreateMember::class)->attach($organization, $person);

    expect($organization->members()->whereKey($person->getKey())->exists())->toBeTrue();
    expect($organization->personCounterparties(OrganizationRelationshipType::Customer)
        ->whereKey($person->getKey())->exists())->toBeTrue();
});

it('promotes an existing member without duplicating them', function () {
    $organization = Organization::factory()
        ->withMember($person = Person::factory()->create())
        ->create();

    app(CreateMember::class)->attach($organization, $person, isPrimary: true);

    $members = $organization->members()->get();

    expect($members)->toHaveCount(1);
    expect($members->first()->pivot->is_primary)->toBeTrue();
});

it('moves a persons primary organization when they join another as primary', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    app(CreateMember::class)->attach($organization, $person, isPrimary: true);
    app(CreateMember::class)->attach($otherOrganization, $person, isPrimary: true);

    $primaryOrganizations = $person->organizationRelationships()
        ->wherePivot('type', OrganizationRelationshipType::Member)
        ->wherePivot('is_primary', true)
        ->get();

    expect($primaryOrganizations)->toHaveCount(1);
    expect($primaryOrganizations->first()->is($otherOrganization))->toBeTrue();
    expect($organization->members()->first()->pivot->is_primary)->toBeNull();
});

it('can join many organizations with only one primary organization', function () {
    $person = Person::factory()->create();
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();
    $third = Organization::factory()->create();

    app(CreateMember::class)->attach($first, $person);
    app(CreateMember::class)->attach($second, $person, isPrimary: true);
    app(CreateMember::class)->attach($third, $person);

    $primaries = $person->organizationRelationships()
        ->wherePivot('type', OrganizationRelationshipType::Member)
        ->wherePivot('is_primary', true)
        ->get();

    expect($person->organizationRelationships()->wherePivot('type', OrganizationRelationshipType::Member)->count())->toBe(3);
    expect($primaries)->toHaveCount(1);
    expect($primaries->first()->is($second))->toBeTrue();

    app(CreateMember::class)->attach($second, Person::factory()->create(), isPrimary: true);
    app(CreateMember::class)->attach($second, Person::factory()->create(), isPrimary: true);

    expect($second->members()->wherePivot('is_primary', true)->count())->toBe(3);
});

test('members must be people', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();

    app(CreateMember::class)->attach($organization, $person);

    expect($organization->members->first()->is($person))->toBeTrue();

    expect(fn () => $organization->organizationCounterparties()->attach(
        Organization::factory()->create(),
        ['type' => OrganizationRelationshipType::Member],
    ))->toThrow(InvalidArgumentException::class);
});
