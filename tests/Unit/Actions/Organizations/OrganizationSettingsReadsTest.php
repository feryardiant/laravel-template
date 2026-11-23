<?php

declare(strict_types=1);

use App\Actions\Organizations\ListCreatableParents;
use App\Actions\Organizations\ListUserMemberships;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds the user membership rows', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->asMemberOf($organization, Role::Owner)->create();

    $rows = app(ListUserMemberships::class)->handle($user, $organization);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['organization']->is($organization))->toBeTrue()
        ->and($rows->first()['role'])->toBe(Role::Owner)
        ->and($rows->first()['isCurrent'])->toBeTrue()
        ->and($rows->first()['canManage'])->toBeTrue();
});

it('lists organizations the owner can parent a subsidiary with', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->asMemberOf($organization, Role::Owner)->create();

    $parents = app(ListCreatableParents::class)->handle($user, internal: false);

    expect($parents->contains($organization))->toBeTrue();
});

it('lists the root subsidiary tree for internal creation', function () {
    $root = Organization::factory()->asRootTenant()->create(['name' => 'Root']);
    $child = Organization::factory()->asTenantOf($root)->create(['name' => 'Child']);
    $root->subsidiaries()->attach($child, [
        'type' => OrganizationRelationshipType::Subsidiary,
        'start_date' => now(),
    ]);

    $user = User::factory()->asMemberOf($root, Role::Owner)->create();

    $parents = app(ListCreatableParents::class)->handle($user, internal: true);

    expect($parents->pluck('name')->all())->toBe(['Child']);
});
