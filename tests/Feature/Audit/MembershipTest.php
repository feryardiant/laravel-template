<?php

declare(strict_types=1);

use App\Actions\Approvals\MembershipLeave;
use App\Actions\Memberships\Audit\MemberAddedPayload;
use App\Actions\Memberships\Audit\MemberLeftPayload;
use App\Actions\Memberships\Audit\MemberRemovedPayload;
use App\Actions\Memberships\Audit\MemberRoleChangedPayload;
use App\Actions\Memberships\ChangeMemberRole;
use App\Actions\Memberships\CreateMember;
use App\Actions\Memberships\LeaveOrganization;
use App\Actions\Memberships\RemoveMember;
use App\Enums\ApprovalRequestStatus;
use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('adding a member records the opened period', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);

    app(CreateMember::class)->handle($organization, 'Invited Member', Role::Member, actor: $owner);

    $log = AuditLog::query()->where('event', AuditEvent::MemberAdded)->sole();

    expect($log->actor_id)->toBe($owner->getKey())
        ->and($log->changes?->toArray())->toBe((new MemberAddedPayload(Role::Member))->toArray())
        ->and($log->organization_relationship_period_id)->not->toBeNull();
});

test('a role change records the actor and both roles', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    app(ChangeMemberRole::class)->promote($organization, $member->person, $owner);

    $log = AuditLog::query()->where('event', AuditEvent::MemberRoleChanged)->sole();

    expect($log->organization_id)->toBe($organization->getKey())
        ->and($log->actor_id)->toBe($owner->getKey())
        ->and($log->subject_id)->toBe($member->person->getKey())
        ->and($log->changes?->toArray())->toBe((new MemberRoleChangedPayload(Role::Member, Role::Admin))->toArray())
        ->and($log->organization_relationship_period_id)->not->toBeNull();
});

test('a repeated role change writes no second entry', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    app(ChangeMemberRole::class)->promote($organization, $member->person, $owner);
    app(ChangeMemberRole::class)->promote($organization, $member->person, $owner);

    expect(AuditLog::query()->where('event', AuditEvent::MemberRoleChanged)->count())->toBe(1);
});

test('a manager removal records the acting manager', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    app(RemoveMember::class)->handle($organization, $member->person, $owner);

    $log = AuditLog::query()->where('event', AuditEvent::MemberRemoved)->sole();

    expect($log->actor_id)->toBe($owner->getKey())
        ->and($log->subject_id)->toBe($member->person->getKey())
        ->and($log->changes?->toArray())->toBe((new MemberRemovedPayload('manager', true))->toArray())
        ->and($log->organization_relationship_period_id)->not->toBeNull();
});

test('a repeated removal writes no second entry', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    app(RemoveMember::class)->handle($organization, $member->person, $owner);
    app(RemoveMember::class)->handle($organization, $member->person, $owner);

    expect(AuditLog::query()->where('event', AuditEvent::MemberRemoved)->count())->toBe(1);
});

test('a system removal with a pending approval keeps a null actor', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    actingAsMember($organization, Role::Owner);
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);

    app(RemoveMember::class)->handle($organization, $admin->person, null);

    $log = AuditLog::query()->where('event', AuditEvent::ApprovalCanceled)->sole();

    expect($log->actor_id)->toBeNull()
        ->and($request->fresh()->status)->toBe(ApprovalRequestStatus::Canceled);
});

test('a self leave records the user as the actor', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    expect(app(LeaveOrganization::class)->handle($member, $organization))->toBeTrue();

    $log = AuditLog::query()->where('event', AuditEvent::MemberLeft)->sole();
    $period = OrganizationRelationshipPeriod::query()->findOrFail($log->organization_relationship_period_id);

    expect($log->actor_id)->toBe($member->getKey())
        ->and($log->subject_id)->toBe($member->person->getKey())
        ->and($log->changes?->toArray())->toBe((new MemberLeftPayload('self'))->toArray())
        ->and($period->actor_id)->toBe($member->getKey());
});

test('an accepted admin leave records the approval and the leave', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);

    expect(app(MembershipLeave::class)->accept($request, $owner))->toBeTrue();

    $accepted = AuditLog::query()->where('event', AuditEvent::ApprovalAccepted)->sole();
    $left = AuditLog::query()->where('event', AuditEvent::MemberLeft)->sole();
    $requested = AuditLog::query()->where('event', AuditEvent::ApprovalRequested)->sole();
    $period = OrganizationRelationshipPeriod::query()->findOrFail($left->organization_relationship_period_id);

    expect($requested->actor_id)->toBe($admin->getKey())
        ->and($accepted->actor_id)->toBe($owner->getKey())
        ->and($accepted->subject_id)->toBe($request->getKey())
        ->and($left->actor_id)->toBe($owner->getKey())
        ->and($left->subject_id)->toBe($admin->person->getKey())
        ->and($left->changes?->toArray())->toBe((new MemberLeftPayload('approval', $request->getKey()))->toArray())
        ->and($period->actor_id)->toBe($owner->getKey());
});

test('a bulk removal writes one entry per detached member', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $first = User::factory()->asMemberOf($organization, Role::Member)->create();
    $second = User::factory()->asMemberOf($organization, Role::Member)->create();

    $persons = $organization->members()
        ->whereKey([$first->person->getKey(), $second->person->getKey()])
        ->get();

    app(RemoveMember::class)->handleMany($organization, $persons, $owner);

    expect(AuditLog::query()->where('event', AuditEvent::MemberRemoved)->count())->toBe(2);
});
