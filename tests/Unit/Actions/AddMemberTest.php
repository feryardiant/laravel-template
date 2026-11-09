<?php

declare(strict_types=1);

use App\Actions\Organizations\AddMember;
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
        ->member->type->toBe(OrganizationRelationshipType::Member)
        ->member->role->toBe(Role::Member);
});

it('assigns the membership role', function () {
    $organization = Organization::factory()
        ->withMember($person = Person::factory()->create(), role: Role::Admin)
        ->create();

    expect($organization->members()->first()->member->role)->toBe(Role::Admin)
        ->and($person->roleIn($organization))->toBe(Role::Admin);
});

it('updates the membership role when an existing member is added again', function () {
    $organization = Organization::factory()
        ->withMember($person = Person::factory()->create(), role: Role::Member)
        ->create();

    app(AddMember::class)->handle($organization, $person, role: Role::Owner);

    expect($organization->members()->count())->toBe(1)
        ->and($person->roleIn($organization))->toBe(Role::Owner);
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

    app(AddMember::class)->handle($organization, $person);
    app(AddMember::class)->handle($organization, $person);

    expect($organization->members()->count())->toBe(1);
});

it('keeps an existing counterparty relationship when adding a member', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()
        ->withCounterparty($person, OrganizationRelationshipType::Customer)
        ->create();

    app(AddMember::class)->handle($organization, $person);

    expect($organization->members()->whereKey($person->getKey())->exists())->toBeTrue()
        ->and($organization->personCounterparties(OrganizationRelationshipType::Customer)
            ->whereKey($person->getKey())->exists())->toBeTrue();
});

it('promotes an existing member without duplicating them', function () {
    $organization = Organization::factory()
        ->withMember($person = Person::factory()->create())
        ->create();

    app(AddMember::class)->handle($organization, $person, isPrimary: true);

    $members = $organization->members()->get();

    expect($members)->toHaveCount(1)
        ->and($members->first()->member->is_primary)->toBeTrue();
});

it('moves a persons primary organization when they join another as primary', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    app(AddMember::class)->handle($organization, $person, isPrimary: true);
    app(AddMember::class)->handle($otherOrganization, $person, isPrimary: true);

    $primaryOrganizations = $person->organizationRelationships()
        ->wherePivot('type', OrganizationRelationshipType::Member)
        ->wherePivot('is_primary', true)
        ->get();

    expect($primaryOrganizations)->toHaveCount(1)
        ->and($primaryOrganizations->first()->is($otherOrganization))->toBeTrue()
        ->and($organization->members()->first()->member->is_primary)->toBeNull();
});

it('can join many organizations with only one primary organization', function () {
    $person = Person::factory()->create();
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();
    $third = Organization::factory()->create();

    app(AddMember::class)->handle($first, $person);
    app(AddMember::class)->handle($second, $person, isPrimary: true);
    app(AddMember::class)->handle($third, $person);

    $primaries = $person->organizationRelationships()
        ->wherePivot('type', OrganizationRelationshipType::Member)
        ->wherePivot('is_primary', true)
        ->get();

    expect($person->organizationRelationships()->wherePivot('type', OrganizationRelationshipType::Member)->count())->toBe(3)
        ->and($primaries)->toHaveCount(1)
        ->and($primaries->first()->is($second))->toBeTrue();

    app(AddMember::class)->handle($second, Person::factory()->create(), isPrimary: true);
    app(AddMember::class)->handle($second, Person::factory()->create(), isPrimary: true);

    expect($second->members()->wherePivot('is_primary', true)->count())->toBe(3);
});

test('members must be people', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();

    app(AddMember::class)->handle($organization, $person);

    expect($organization->members->first()->is($person))->toBeTrue();

    expect(fn () => $organization->organizationCounterparties()->attach(
        Organization::factory()->create(),
        ['type' => OrganizationRelationshipType::Member],
    ))->toThrow(InvalidArgumentException::class);
});
