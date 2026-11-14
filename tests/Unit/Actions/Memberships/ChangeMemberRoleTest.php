<?php

declare(strict_types=1);

use App\Actions\Memberships\ChangeMemberRole;
use App\Enums\Role;
use App\Events\MemberRoleChanged;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('refuses to assign the owner role directly', function () {
    Event::fake([MemberRoleChanged::class]);

    $organization = Organization::factory()->create();
    $actor = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    expect(app(ChangeMemberRole::class)->setRole($organization, $member->person, Role::Owner, $actor))
        ->toBeFalse()
        ->and($member->person->roleIn($organization))->toBe(Role::Member);

    Event::assertNotDispatched(MemberRoleChanged::class);
});

it('does not let an admin change an owner', function () {
    Event::fake([MemberRoleChanged::class]);

    $organization = Organization::factory()->create();
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    expect(app(ChangeMemberRole::class)->setRole($organization, $owner->person, Role::Member, $admin))
        ->toBeFalse()
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner);

    Event::assertNotDispatched(MemberRoleChanged::class);
});
