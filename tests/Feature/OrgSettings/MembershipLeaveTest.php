<?php

declare(strict_types=1);

use App\Actions\Memberships\ChangeMemberRole;
use App\Actions\Memberships\RemoveMember;
use App\Actions\Organizations\AcceptMembershipLeave;
use App\Actions\Organizations\DeclineMembershipLeave;
use App\Actions\Organizations\RequestMembershipLeave;
use App\Actions\Organizations\RequestOwnershipTransfer;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\Role;
use App\Livewire\Settings\Organizations;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MembershipLeaveDeclinedNotification;
use App\Notifications\MembershipLeaveRequestedNotification;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function requestAdminLeaveForTest(Organization $organization): ApprovalRequest
{
    Livewire::test(Organizations::class)
        ->call('confirmLeave', $organization->getKey(), $organization->name)
        ->call('leave')
        ->assertHasNoErrors();

    return ApprovalRequest::query()->latest('id')->firstOrFail();
}

test('an admin leaving creates a pending request and keeps their membership', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = actingAsMember($organization, Role::Admin);

    $request = requestAdminLeaveForTest($organization);

    expect($request->type)->toBe(ApprovalRequestType::MembershipLeave)
        ->and($request->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($request->requester_person_id)->toBe($admin->person->getKey())
        ->and($request->approver_person_id)->toBe($owner->person->getKey())
        ->and($organization->members()->whereKey($admin->person->getKey())->exists())->toBeTrue();

    Notification::assertSentTo($owner, MembershipLeaveRequestedNotification::class);
    Notification::assertNotSentTo($admin, MembershipLeaveRequestedNotification::class);
});

test('a member leaves immediately without an approval request', function () {
    $organization = Organization::factory()->create();
    $member = actingAsMember($organization, Role::Member);

    Livewire::test(Organizations::class)
        ->call('confirmLeave', $organization->getKey(), $organization->name)
        ->call('leave')
        ->assertRedirect(route('dashboard'));

    expect($organization->members()->whereKey($member->person->getKey())->exists())->toBeFalse()
        ->and(ApprovalRequest::query()->exists())->toBeFalse();
});

test('the owner can approve an admin leave request', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(RequestMembershipLeave::class)->handle($organization, $admin);

    actingAs($owner);

    Livewire::test(Organizations::class)
        ->assertSee(__('Leave request from :name', ['name' => $admin->name]))
        ->call('openApproval', $organization->getKey())
        ->assertSet('showApprovalModal', true)
        ->call('acceptApproval')
        ->assertSet('showApprovalModal', false);

    expect($request->fresh()->status)->toBe(ApprovalRequestStatus::Accepted)
        ->and($organization->members()->whereKey($admin->person->getKey())->exists())->toBeFalse()
        ->and($admin->fresh())->not->toBeNull();
});

test('the owner can decline an admin leave request', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(RequestMembershipLeave::class)->handle($organization, $admin);

    actingAs($owner);

    Livewire::test(Organizations::class)
        ->call('openApproval', $organization->getKey())
        ->call('declineApproval');

    expect($request->fresh()->status)->toBe(ApprovalRequestStatus::Declined)
        ->and($organization->members()->whereKey($admin->person->getKey())->exists())->toBeTrue();

    Notification::assertSentTo($admin, MembershipLeaveDeclinedNotification::class);
});

test('the admin can cancel their own pending leave request', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = actingAsMember($organization, Role::Admin);

    $request = requestAdminLeaveForTest($organization);

    Livewire::test(Organizations::class)
        ->assertSee(__('Leave request pending'))
        ->call('cancelLeaveRequest', $request->getKey());

    expect($request->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled)
        ->and($organization->members()->whereKey($admin->person->getKey())->exists())->toBeTrue();
});

test('a pending leave request blocks an ownership transfer', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    app(RequestMembershipLeave::class)->handle($organization, $admin);

    expect(fn () => app(RequestOwnershipTransfer::class)->handle($organization, $owner, $admin->person))
        ->toThrow(InvalidArgumentException::class);

    expect(ApprovalRequest::query()->pending()->count())->toBe(1);
});

test('a pending transfer blocks an admin leave request', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    app(RequestOwnershipTransfer::class)->handle($organization, $owner, $admin->person);

    expect(fn () => app(RequestMembershipLeave::class)->handle($organization, $admin))
        ->toThrow(InvalidArgumentException::class);

    expect(ApprovalRequest::query()->pending()->count())->toBe(1);
});

test('removing the admin cancels their pending leave request', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(RequestMembershipLeave::class)->handle($organization, $admin);

    app(RemoveMember::class)->handle($organization, $admin->person, $owner);

    expect($request->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled)
        ->and($organization->members()->whereKey($admin->person->getKey())->exists())->toBeFalse();
});

test('demoting the admin cancels their pending leave request', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(RequestMembershipLeave::class)->handle($organization, $admin);

    app(ChangeMemberRole::class)->demote($organization, $admin->person, $owner);

    expect($request->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled)
        ->and($admin->person->roleIn($organization))->toBe(Role::Member);
});

test('an expired request can no longer be approved or declined', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(RequestMembershipLeave::class)->handle($organization, $admin);
    $request->update(['expires_at' => now()->subMinute()]);

    expect(app(AcceptMembershipLeave::class)->handle($request->fresh(), $owner))->toBeFalse()
        ->and(app(DeclineMembershipLeave::class)->handle($request->fresh(), $owner))->toBeFalse();

    expect($request->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($organization->members()->whereKey($admin->person->getKey())->exists())->toBeTrue();
});

test('the requester cannot respond to their own leave request', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(RequestMembershipLeave::class)->handle($organization, $admin);

    expect(fn () => app(AcceptMembershipLeave::class)->handle($request, $admin))
        ->toThrow(InvalidArgumentException::class);
});

test('the signed link lets the owner review but rejects the requester', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(RequestMembershipLeave::class)->handle($organization, $admin);

    $url = URL::temporarySignedRoute(
        'organizations.approval.link',
        $request->expires_at,
        ['approval' => $request->uuid],
    );

    actingAs($admin);

    get($url)->assertForbidden();

    actingAs($owner);

    get($url)->assertRedirect(route('organizations.index', ['approval' => $request->uuid]));
});

test('an approved leave request opens the review from the email query', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(RequestMembershipLeave::class)->handle($organization, $admin);

    actingAs($owner);
    session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);
    test()->withSession(['auth.password_confirmed_at' => time()]);

    get(route('organizations.index', ['approval' => $request->uuid]))
        ->assertOk()
        ->assertSee(__('Leave request'))
        ->assertSee(__('Approve'))
        ->assertSee(__('Decline'));
});
