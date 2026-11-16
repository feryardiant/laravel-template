<?php

declare(strict_types=1);

use App\Actions\Approvals\CancelApprovalRequest;
use App\Actions\Approvals\MembershipLeave;
use App\Actions\Approvals\OwnershipTransfer;
use App\Actions\FileStorage;
use App\Actions\Memberships\ChangeMemberRole;
use App\Actions\Memberships\RemoveMember;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Jobs\DeleteOrganization;
use App\Livewire\OrgSettings\Features;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('an ownership transfer request, acceptance, decline, and cancellation are recorded', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $request = app(OwnershipTransfer::class)->request($organization, $owner, $recipient->person);

    expect(app(OwnershipTransfer::class)->accept($request, $recipient))->toBeTrue();

    $requested = AuditLog::query()->where('event', AuditEvent::ApprovalRequested)->sole();
    $accepted = AuditLog::query()->where('event', AuditEvent::ApprovalAccepted)->sole();

    expect($requested->actor_id)->toBe($owner->getKey())
        ->and($requested->changes)->toMatchArray(['type' => ApprovalRequestType::OwnershipTransfer->value])
        ->and($accepted->actor_id)->toBe($recipient->getKey())
        ->and($accepted->subject_id)->toBe($request->getKey());

    $roleLogs = AuditLog::query()->where('event', AuditEvent::MemberRoleChanged)->get();

    expect($roleLogs)->toHaveCount(2)
        ->and($roleLogs->pluck('changes.to')->all())->toContain(Role::Admin->value, Role::Owner->value);

    $previousOwnerPeriod = OrganizationRelationshipPeriod::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_id', $owner->person->getKey())
        ->latest('id')
        ->firstOrFail();

    $newOwnerPeriod = OrganizationRelationshipPeriod::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_id', $recipient->person->getKey())
        ->latest('id')
        ->firstOrFail();

    expect($previousOwnerPeriod->role)->toBe(Role::Admin)
        ->and($previousOwnerPeriod->isOpen())->toBeTrue()
        ->and($newOwnerPeriod->role)->toBe(Role::Owner)
        ->and($newOwnerPeriod->isOpen())->toBeTrue();

    $requesterLog = $roleLogs->first(fn (AuditLog $log): bool => $log->subject_id === $owner->person->getKey());
    $approverLog = $roleLogs->first(fn (AuditLog $log): bool => $log->subject_id === $recipient->person->getKey());

    expect($requesterLog->changes['from'])->toBe(Role::Owner->value)
        ->and($requesterLog->organization_relationship_period_id)->toBe($previousOwnerPeriod->getKey())
        ->and($approverLog->changes['from'])->toBe(Role::Member->value)
        ->and($approverLog->organization_relationship_period_id)->toBe($newOwnerPeriod->getKey());

    $secondOwner = $accepted->subject->approver->user;
    \assert($secondOwner instanceof User);

    $declineRequest = app(OwnershipTransfer::class)->request($organization, $secondOwner, $owner->person);

    expect(app(OwnershipTransfer::class)->decline($declineRequest, $owner))->toBeTrue();

    $declined = AuditLog::query()->where('event', AuditEvent::ApprovalDeclined)->latest('id')->sole();

    expect($declined->actor_id)->toBe($owner->getKey())
        ->and($declined->subject_id)->toBe($declineRequest->getKey());

    $cancelRequest = app(OwnershipTransfer::class)->request($organization, $secondOwner, $owner->person);

    expect(app(CancelApprovalRequest::class)->handle($cancelRequest, $secondOwner))->toBeTrue();

    $cancelled = AuditLog::query()->where('event', AuditEvent::ApprovalCancelled)->sole();

    expect($cancelled->actor_id)->toBe($secondOwner->getKey())
        ->and($cancelled->subject_id)->toBe($cancelRequest->getKey());
});

test('cancelling a stale pending request does not overwrite a decision', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $request = app(OwnershipTransfer::class)->request($organization, $owner, $recipient->person);
    $stale = ApprovalRequest::query()->findOrFail($request->getKey());

    $request->update([
        'status' => ApprovalRequestStatus::Accepted,
        'accepted_at' => now(),
    ]);

    expect(app(CancelApprovalRequest::class)->handle($stale, $owner))->toBeFalse()
        ->and($request->fresh()->status)->toBe(ApprovalRequestStatus::Accepted);
});

test('an expired pending request does not block a new ownership transfer', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $old = app(OwnershipTransfer::class)->request($organization, $owner, $recipient->person);
    $old->update(['expires_at' => now()->subMinute()]);

    $new = app(OwnershipTransfer::class)->request($organization, $owner, $recipient->person);

    expect($new->getKey())->not->toBe($old->getKey())
        ->and($old->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled);
});

test('an expired pending request does not block a new leave request', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    actingAsMember($organization, Role::Owner);
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $old = app(MembershipLeave::class)->request($organization, $admin);
    $old->update(['expires_at' => now()->subMinute()]);

    $new = app(MembershipLeave::class)->request($organization, $admin);

    expect($new->getKey())->not->toBe($old->getKey())
        ->and($old->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled);
});

test('membership leave requires an active owner', function () {
    $organization = Organization::factory()->create();
    $admin = actingAsMember($organization, Role::Admin);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();

    $organization->members()->updateExistingPivot($owner->person->getKey(), [
        'finish_date' => now()->subDay(),
    ]);

    expect(fn () => app(MembershipLeave::class)->request($organization, $admin))
        ->toThrow(InvalidArgumentException::class);
});

test('a membership leave decline and cancellation are recorded', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);

    expect(app(MembershipLeave::class)->decline($request, $owner))->toBeTrue();

    $declined = AuditLog::query()->where('event', AuditEvent::ApprovalDeclined)->sole();

    expect($declined->actor_id)->toBe($owner->getKey())
        ->and($declined->changes)->toMatchArray(['type' => ApprovalRequestType::MembershipLeave->value]);

    $cancelRequest = app(MembershipLeave::class)->request($organization, $admin);

    expect(app(CancelApprovalRequest::class)->handle($cancelRequest, $admin))->toBeTrue();

    $cancelled = AuditLog::query()->where('event', AuditEvent::ApprovalCancelled)->sole();

    expect($cancelled->actor_id)->toBe($admin->getKey())
        ->and($cancelled->subject_id)->toBe($cancelRequest->getKey());
});

test('demoting an admin audits the cancelled leave request', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);

    app(ChangeMemberRole::class)->demote($organization, $admin->person, $owner);

    $cancelled = AuditLog::query()->where('event', AuditEvent::ApprovalCancelled)->sole();

    expect($cancelled->actor_id)->toBe($owner->getKey())
        ->and($cancelled->changes)->toMatchArray([
            'type' => ApprovalRequestType::MembershipLeave->value,
            'reason' => 'role_changed',
        ])
        ->and($request->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled);
});

test('removing an admin audits the cancelled leave request', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);

    app(RemoveMember::class)->handle($organization, $admin->person, $owner);

    $cancelled = AuditLog::query()->where('event', AuditEvent::ApprovalCancelled)->sole();

    expect($cancelled->actor_id)->toBe($owner->getKey())
        ->and($cancelled->changes)->toMatchArray([
            'type' => ApprovalRequestType::MembershipLeave->value,
            'reason' => 'member_removed',
        ])
        ->and($request->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled);
});

test('deleting an organization prunes its audit log and approvals', function () {
    Notification::fake();

    $root = Organization::factory()->asRootTenant()->create();
    $rootOwner = actingAsMember($root, Role::Owner);
    $subsidiary = Organization::factory()->asTenantOf($root)->create();
    $root->subsidiaries()->attach($subsidiary, ['type' => OrganizationRelationshipType::Subsidiary]);

    User::factory()->asMemberOf($subsidiary, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($subsidiary, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($subsidiary, $admin);

    (new DeleteOrganization($subsidiary, $rootOwner->getKey()))
        ->handle(app(FileStorage::class));

    expect(AuditLog::query()->where('organization_id', $subsidiary->getKey())->count())->toBe(0)
        ->and(AuditLog::query()->where('event', AuditEvent::ApprovalCancelled)->count())->toBe(0)
        ->and(ApprovalRequest::query()->whereKey($request->getKey())->exists())->toBeFalse();
});

test('a handled approval does not write a second decision entry', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $request = app(OwnershipTransfer::class)->request($organization, $owner, $recipient->person);

    expect(app(OwnershipTransfer::class)->accept($request, $recipient))->toBeTrue()
        ->and(app(OwnershipTransfer::class)->decline($request->fresh(), $recipient))->toBeFalse()
        ->and(AuditLog::query()->where('event', AuditEvent::ApprovalDeclined)->count())->toBe(0);
});

test('each feature toggle records the feature and its state', function (string $property, string $feature, bool $enabled) {
    $root = Organization::factory()->asRootTenant()->create();
    $owner = actingAsMember($root, Role::Owner);

    Livewire::test(Features::class)
        ->set($property, $enabled)
        ->assertHasNoErrors();

    $log = AuditLog::query()->where('event', AuditEvent::FeatureToggled)->sole();

    expect($log->organization_id)->toBe($root->getKey())
        ->and($log->actor_id)->toBe($owner->getKey())
        ->and($log->subject_id)->toBe($root->getKey())
        ->and($log->changes)->toMatchArray([
            'feature' => $feature,
            'enabled' => $enabled,
        ]);
})->with([
    ['contactGender', 'contact-gender', true],
    ['memberDeletion', 'member-deletion', false],
    ['contactDeletion', 'contact-deletion', false],
    ['profileSelfEdit', 'profile-self-edit', true],
    ['registration', 'registration', true],
]);
