<?php

declare(strict_types=1);

use App\Actions\Memberships\ChangeMemberRole;
use App\Actions\Memberships\CreateMember;
use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Events\Memberships\MemberRoleChanged;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\OrganizationRelationshipPeriod;
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

it('links the role audit to the member period when another relationship is open', function () {
    $organization = Organization::factory()->create();
    $actor = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $member = User::factory()->withProfile()->create();

    app(CreateMember::class)->attach($organization, $member->person, role: Role::Member);

    $organization->personCounterparties(OrganizationRelationshipType::Customer)->attach($member->person, [
        'type' => OrganizationRelationshipType::Customer,
        'start_date' => now(),
    ]);

    $customer = OrganizationRelationship::query()
        ->forOrganization($organization)
        ->whereStakeholder($member->person)
        ->where('type', OrganizationRelationshipType::Customer)
        ->firstOrFail();

    app(RelationshipTimeline::class)->open($customer);

    app(ChangeMemberRole::class)->promote($organization, $member->person, $actor);

    $log = AuditLog::query()->where('event', AuditEvent::MemberRoleChanged)->sole();

    $memberPeriod = OrganizationRelationshipPeriod::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_id', $member->person->getKey())
        ->where('role', Role::Admin)
        ->latest('id')
        ->firstOrFail();

    expect($log->organization_relationship_period_id)->toBe($memberPeriod->getKey());
});

it('does not change the role of an expired membership', function () {
    $organization = Organization::factory()->create();
    $actor = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $member = User::factory()->withProfile()->create();

    app(CreateMember::class)->attach(
        $organization,
        $member->person,
        startDate: now()->subDays(10),
        finishDate: now()->subDay(),
        role: Role::Member,
    );

    expect(app(ChangeMemberRole::class)->setRole($organization, $member->person, Role::Admin, $actor))
        ->toBeFalse()
        ->and($member->person->fresh()->roleIn($organization))->toBeNull();
});
