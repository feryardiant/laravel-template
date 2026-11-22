<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Support\Feature\MemberDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function memberTargetForPolicy(Organization $organization, Role $role = Role::Member): Person
{
    return User::factory()->asMemberOf($organization, $role)->create()->person;
}

test('managers may act on members and only owners may act on owners', function (Role $actorRole, Role $targetRole, bool $expected) {
    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, $actorRole);
    $target = memberTargetForPolicy($organization, $targetRole);

    expect(Gate::forUser($actor)->allows('manageMember', [$target, $organization]))->toBe($expected);
})->with([
    [Role::Owner, Role::Member, true],
    [Role::Owner, Role::Admin, true],
    [Role::Admin, Role::Member, true],
    [Role::Admin, Role::Admin, true],
    [Role::Admin, Role::Owner, false],
    [Role::Member, Role::Member, false],
]);

test('a manager may manage their own member profile', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);

    expect(Gate::forUser($owner)->allows('manageMember', [$owner->person, $organization]))->toBeTrue();
});

test('a person outside the organization is not a manageable member', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $outsider = Person::factory()->create();

    expect(Gate::forUser($owner)->allows('manageMember', [$outsider, $organization]))->toBeFalse();
});

test('role changes follow the member rules and never assign ownership', function () {
    $organization = Organization::factory()->create();

    $owner = actingAsMember($organization, Role::Owner);
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();
    $target = memberTargetForPolicy($organization);

    expect(Gate::forUser($owner)->allows('changeRole', [$target, $organization, Role::Admin]))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('changeRole', [$target, $organization, Role::Admin]))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('changeRole', [$target, $organization, Role::Owner]))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('changeRole', [$owner->person, $organization, Role::Admin]))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('changeRole', [$owner->person, $organization, Role::Member]))->toBeFalse();
});

test('member removal requires a manager, a target other than self, and the feature flag', function () {
    $organization = Organization::factory()->create();

    $owner = actingAsMember($organization, Role::Owner);
    $target = memberTargetForPolicy($organization);

    expect(Gate::forUser($owner)->allows('removeMember', [$target, $organization]))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('removeMember', [$owner->person, $organization]))->toBeFalse();

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    expect(Gate::forUser($member)->allows('removeMember', [$target, $organization]))->toBeFalse();

    MemberDeletion::deactivate($organization);

    expect(Gate::forUser($owner)->allows('removeMember', [$target, $organization]))->toBeFalse();
});
