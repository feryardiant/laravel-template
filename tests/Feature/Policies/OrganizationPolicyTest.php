<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganization;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('managers may update the organization and its features', function (Role $role) {
    $organization = Organization::factory()->create();
    $user = actingAsMember($organization, $role);

    expect(Gate::forUser($user)->allows('update', $organization))->toBeTrue()
        ->and(Gate::forUser($user)->allows('manageMembers', $organization))->toBeTrue()
        ->and(Gate::forUser($user)->allows('manageFeatures', $organization))->toBeTrue()
        ->and(Gate::forUser($user)->allows('viewAudit', $organization))->toBeTrue();
})->with([Role::Owner, Role::Admin]);

test('plain members may not manage the organization', function () {
    $organization = Organization::factory()->create();
    $user = actingAsMember($organization, Role::Member);

    expect(Gate::forUser($user)->allows('update', $organization))->toBeFalse()
        ->and(Gate::forUser($user)->allows('manageMembers', $organization))->toBeFalse()
        ->and(Gate::forUser($user)->allows('manageFeatures', $organization))->toBeFalse()
        ->and(Gate::forUser($user)->allows('viewAudit', $organization))->toBeFalse()
        ->and(Gate::forUser($user)->allows('viewApprovals', $organization))->toBeFalse()
        ->and(Gate::forUser($user)->allows('transferOwnership', $organization))->toBeFalse();
});

test('only owners may read approvals and transfer ownership', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);

    expect(Gate::forUser($owner)->allows('viewApprovals', $organization))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('transferOwnership', $organization))->toBeTrue();
});

test('subsidiaries are visible to the root tenant managers only', function (Role $role, bool $expected) {
    $root = Organization::factory()->asRootTenant()->create();
    $user = actingAsMember($root, $role);

    expect(Gate::forUser($user)->allows('viewSubsidiaries', $root))->toBe($expected);
})->with([
    [Role::Owner, true],
    [Role::Admin, true],
    [Role::Member, false],
]);

test('a non-root organization has no subsidiaries page', function () {
    $organization = Organization::factory()->create();
    $user = actingAsMember($organization, Role::Owner);

    expect(Gate::forUser($user)->allows('viewSubsidiaries', $organization))->toBeFalse();
});

test('only an owner may create a subsidiary', function () {
    $organization = Organization::factory()->create();

    $owner = actingAsMember($organization, Role::Owner);

    expect(Gate::forUser($owner)->allows('createSubsidiary', $organization))->toBeTrue();

    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    expect(Gate::forUser($admin)->allows('createSubsidiary', $organization))->toBeFalse();
});

test('a sole owner may delete their organization', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);

    expect(Gate::forUser($owner)->allows('delete', $organization))->toBeTrue();
});

test('the root tenant is never deletable', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $owner = actingAsMember($root, Role::Owner);

    expect(Gate::forUser($owner)->allows('delete', $root))->toBeFalse();
});

test('a root owner may delete a subsidiary in the tree', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $owner = actingAsMember($root, Role::Owner);

    $subsidiary = app(CreateOrganization::class)->handle($owner, 'Subsidiary', $root);

    expect(Gate::forUser($owner)->allows('delete', $subsidiary))->toBeTrue();
});

test('a user outside the organization may not read or manage it', function () {
    $organization = Organization::factory()->create();
    $foreign = Organization::factory()->create();
    $outsider = actingAsMember($foreign, Role::Owner);

    expect(Gate::forUser($outsider)->allows('update', $organization))->toBeFalse()
        ->and(Gate::forUser($outsider)->allows('manageMembers', $organization))->toBeFalse()
        ->and(Gate::forUser($outsider)->allows('delete', $organization))->toBeFalse();
});
