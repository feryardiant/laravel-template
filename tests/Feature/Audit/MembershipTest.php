<?php

declare(strict_types=1);

use App\Actions\Memberships\ChangeMemberRole;
use App\Actions\Memberships\CreateMember;
use App\Actions\Memberships\LeaveOrganization;
use App\Actions\Memberships\RemoveMember;
use App\Actions\Organizations\AcceptMembershipLeave;
use App\Actions\Organizations\RequestMembershipLeave;
use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Organization;
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
        ->and($log->changes)->toMatchArray(['role' => Role::Member->value])
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
        ->and($log->changes)->toMatchArray([
            'from' => Role::Member->value,
            'to' => Role::Admin->value,
        ])
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
        ->and($log->changes)->toMatchArray([
            'via' => 'manager',
            'last_membership' => true,
        ])
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

test('a self leave records a null actor', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    expect(app(LeaveOrganization::class)->handle($member, $organization))->toBeTrue();

    $log = AuditLog::query()->where('event', AuditEvent::MemberLeft)->sole();

    expect($log->actor_id)->toBeNull()
        ->and($log->subject_id)->toBe($member->person->getKey())
        ->and($log->changes)->toMatchArray(['via' => 'self'])
        ->and($log->organization_relationship_period_id)->not->toBeNull();
});

test('an accepted admin leave records the approval and the leave', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(RequestMembershipLeave::class)->handle($organization, $admin);

    expect(app(AcceptMembershipLeave::class)->handle($request, $owner))->toBeTrue();

    $accepted = AuditLog::query()->where('event', AuditEvent::ApprovalAccepted)->sole();
    $left = AuditLog::query()->where('event', AuditEvent::MemberLeft)->sole();
    $requested = AuditLog::query()->where('event', AuditEvent::ApprovalRequested)->sole();

    expect($requested->actor_id)->toBe($admin->getKey())
        ->and($accepted->actor_id)->toBe($owner->getKey())
        ->and($accepted->subject_id)->toBe($request->getKey())
        ->and($left->actor_id)->toBe($owner->getKey())
        ->and($left->subject_id)->toBe($admin->person->getKey())
        ->and($left->changes)->toMatchArray([
            'via' => 'approval',
            'approval_id' => $request->getKey(),
        ])
        ->and($left->organization_relationship_period_id)->not->toBeNull();
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
