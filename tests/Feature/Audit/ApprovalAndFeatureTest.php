<?php

declare(strict_types=1);

use App\Actions\Organizations\AcceptOwnershipTransfer;
use App\Actions\Organizations\CancelApprovalRequest;
use App\Actions\Organizations\DeclineMembershipLeave;
use App\Actions\Organizations\DeclineOwnershipTransfer;
use App\Actions\Organizations\RequestMembershipLeave;
use App\Actions\Organizations\RequestOwnershipTransfer;
use App\Enums\ApprovalRequestType;
use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Livewire\OrgSettings\Features;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('an ownership transfer request, acceptance, decline, and cancellation are recorded', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $request = app(RequestOwnershipTransfer::class)->handle($organization, $owner, $recipient->person);

    expect(app(AcceptOwnershipTransfer::class)->handle($request, $recipient))->toBeTrue();

    $requested = AuditLog::query()->where('event', AuditEvent::ApprovalRequested)->sole();
    $accepted = AuditLog::query()->where('event', AuditEvent::ApprovalAccepted)->sole();

    expect($requested->actor_id)->toBe($owner->getKey())
        ->and($requested->changes)->toMatchArray(['type' => ApprovalRequestType::OwnershipTransfer->value])
        ->and($accepted->actor_id)->toBe($recipient->getKey())
        ->and($accepted->subject_id)->toBe($request->getKey());

    $secondOwner = $accepted->subject->approver->user;
    \assert($secondOwner instanceof User);

    $declineRequest = app(RequestOwnershipTransfer::class)->handle($organization, $secondOwner, $owner->person);

    expect(app(DeclineOwnershipTransfer::class)->handle($declineRequest, $owner))->toBeTrue();

    $declined = AuditLog::query()->where('event', AuditEvent::ApprovalDeclined)->latest('id')->sole();

    expect($declined->actor_id)->toBe($owner->getKey())
        ->and($declined->subject_id)->toBe($declineRequest->getKey());

    $cancelRequest = app(RequestOwnershipTransfer::class)->handle($organization, $secondOwner, $owner->person);

    expect(app(CancelApprovalRequest::class)->handle($cancelRequest, $secondOwner))->toBeTrue();

    $cancelled = AuditLog::query()->where('event', AuditEvent::ApprovalCancelled)->sole();

    expect($cancelled->actor_id)->toBe($secondOwner->getKey())
        ->and($cancelled->subject_id)->toBe($cancelRequest->getKey());
});

test('a membership leave decline and cancellation are recorded', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(RequestMembershipLeave::class)->handle($organization, $admin);

    expect(app(DeclineMembershipLeave::class)->handle($request, $owner))->toBeTrue();

    $declined = AuditLog::query()->where('event', AuditEvent::ApprovalDeclined)->sole();

    expect($declined->actor_id)->toBe($owner->getKey())
        ->and($declined->changes)->toMatchArray(['type' => ApprovalRequestType::MembershipLeave->value]);

    $cancelRequest = app(RequestMembershipLeave::class)->handle($organization, $admin);

    expect(app(CancelApprovalRequest::class)->handle($cancelRequest, $admin))->toBeTrue();

    $cancelled = AuditLog::query()->where('event', AuditEvent::ApprovalCancelled)->sole();

    expect($cancelled->actor_id)->toBe($admin->getKey())
        ->and($cancelled->subject_id)->toBe($cancelRequest->getKey());
});

test('a handled approval does not write a second decision entry', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $request = app(RequestOwnershipTransfer::class)->handle($organization, $owner, $recipient->person);

    expect(app(AcceptOwnershipTransfer::class)->handle($request, $recipient))->toBeTrue()
        ->and(app(DeclineOwnershipTransfer::class)->handle($request->fresh(), $recipient))->toBeFalse()
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
