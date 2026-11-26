<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has no organization role without a membership', function () {
    $user = User::factory()->create();

    expect($user->roleIn(Organization::factory()->create()))->toBeNull();
});

it('reads the role from the persons membership', function () {
    $user = User::factory()->create();
    $person = $user->person;

    $ownerOrganization = Organization::factory()
        ->withMember($person, role: Role::Owner)
        ->create();

    $memberOrganization = Organization::factory()
        ->withMember($person, role: Role::Member)
        ->create();

    expect($user->roleIn($ownerOrganization))->toBe(Role::Owner);
    expect($user->roleIn($memberOrganization))->toBe(Role::Member);
});

it('has no role in an organization the person is not a member of', function () {
    $user = User::factory()->create();

    expect($user->roleIn(Organization::factory()->create()))->toBeNull();
});

it('ignores memberships that have finished or not started', function () {
    $user = User::factory()->create();
    $person = $user->person;

    $finished = Organization::factory()
        ->withMember($person, role: Role::Owner, finishDate: now()->subDay())
        ->create();

    $future = Organization::factory()
        ->withMember($person, role: Role::Owner, startDate: now()->addDay())
        ->create();

    expect($user->roleIn($finished))->toBeNull();
    expect($user->roleIn($future))->toBeNull();
});

it('reuses the loaded membership pivot for role checks', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()
        ->withMember($user->person, role: Role::Admin)
        ->create();

    $person = $user->person->fresh();
    $organization = $person->organizations()->firstOrFail();

    expect($person->roleIn($organization))->toBe(Role::Admin)
        ->and($person->membershipIn($organization))->toBe($organization->stakeholder);

    $member = $organization->members()->whereKey($person->getKey())->firstOrFail();

    expect($member->membershipIn($organization))->toBe($member->pivot);
});
