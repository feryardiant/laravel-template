<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Repositories\OrganizationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('builds the user membership rows', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->asMemberOf($organization, Role::Owner, true)->create();

    $rows = app(OrganizationRepository::class)->getUsers($user, $organization);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['organization']->is($organization))->toBeTrue()
        ->and($rows->first()['role'])->toBe(Role::Owner)
        ->and($rows->first()['isCurrent'])->toBeTrue()
        ->and($rows->first()['canManage'])->toBeTrue()
        ->and($rows->first()['isDefault'])->toBeTrue();
});

it('keeps the membership query count flat as organizations grow', function () {
    $single = User::factory()->asMemberOf(Organization::factory()->create(), Role::Owner)->create();
    $multi = User::factory()->asMemberOf(Organization::factory()->create(), Role::Owner)->create();

    Organization::factory()->count(3)->create()->each(
        fn (Organization $joined) => $joined->members()->attach($multi->person, [
            'type' => OrganizationRelationshipType::Member,
            'role' => Role::Member,
            'start_date' => now(),
        ]),
    );

    $queryCount = function (User $user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(OrganizationRepository::class)->getUsers($user->fresh(), null);

        DB::disableQueryLog();

        return \count(DB::getQueryLog());
    };

    expect($queryCount($multi))->toBe($queryCount($single));
});

it('lists organizations the owner can parent a subsidiary with', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->asMemberOf($organization, Role::Owner)->create();

    $parents = app(OrganizationRepository::class)->getCreatable($user, internal: false);

    expect($parents->contains($organization))->toBeTrue();
});

it('excludes organizations the user does not own from the parents', function () {
    $owned = Organization::factory()->create();
    $joined = Organization::factory()->create();
    $user = User::factory()->asMemberOf($owned, Role::Owner)->create();

    $joined->members()->attach($user->person, [
        'type' => OrganizationRelationshipType::Member,
        'role' => Role::Admin,
        'start_date' => now(),
    ]);

    expect(app(OrganizationRepository::class)->getCreatable($user, internal: false)->modelKeys())
        ->toBe([$owned->getKey()]);
});

it('lists the root subsidiary tree for internal creation', function () {
    $root = Organization::factory()->asRootTenant()->create(['name' => 'Root']);
    $child = Organization::factory()->asTenantOf($root)->create(['name' => 'Child']);
    $root->subsidiaries()->attach($child, [
        'type' => OrganizationRelationshipType::Subsidiary,
        'start_date' => now(),
    ]);

    $user = User::factory()->asMemberOf($root, Role::Owner)->create();

    $parents = app(OrganizationRepository::class)->getCreatable($user, internal: true);

    expect($parents->pluck('name')->all())->toBe(['Child']);
});
