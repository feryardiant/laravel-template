<?php

declare(strict_types=1);

use App\Actions\Approvals\MembershipLeave;
use App\Actions\Approvals\OwnershipTransfer;
use App\Actions\Memberships\ChangeMemberRole;
use App\Actions\Memberships\RemoveMember;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\Role;
use App\Livewire\Settings\Organizations;
use App\Livewire\Settings\Organizations\Leave;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MemberLeftNotification;
use App\Notifications\MembershipLeaveRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function requestAdminLeaveForTest(Organization $organization): ApprovalRequest
{
    Livewire::test(Leave::class)
        ->call('confirm', $organization->getKey(), $organization->name)
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

    Livewire::test(Leave::class)
        ->call('confirm', $organization->getKey(), $organization->name)
        ->call('leave')
        ->assertRedirect(route('dashboard'));

    expect($organization->members()->whereKey($member->person->getKey())->exists())->toBeFalse()
        ->and(ApprovalRequest::query()->exists())->toBeFalse();
});

test('the admin can cancel their own pending leave request', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = actingAsMember($organization, Role::Admin);

    $request = requestAdminLeaveForTest($organization);

    Livewire::test(Organizations::class)
        ->assertSee(__('Leave request pending'));

    Livewire::test(Leave::class)
        ->call('cancelRequest', $request->getKey());

    expect($request->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled)
        ->and($organization->members()->whereKey($admin->person->getKey())->exists())->toBeTrue();
});

test('leaving notifies the other owners in the database only', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    $leaver = actingAsMember($organization, Role::Member);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    Livewire::test(Leave::class)
        ->call('confirm', $organization->getKey(), $organization->name)
        ->call('leave');

    Notification::assertSentTo(
        $owner,
        MemberLeftNotification::class,
        fn (MemberLeftNotification $notification): bool => $notification->organization->is($organization)
            && $notification->memberName === $leaver->name,
    );

    Notification::assertNotSentTo($leaver, MemberLeftNotification::class);
    Notification::assertNotSentTo($admin, MemberLeftNotification::class);
    expect((new MemberLeftNotification($organization, 'Someone'))->via($owner))->toBe(['database']);
});

test('a pending leave request blocks an ownership transfer', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    app(MembershipLeave::class)->request($organization, $admin);

    expect(fn () => app(OwnershipTransfer::class)->request($organization, $owner, $admin->person))
        ->toThrow(InvalidArgumentException::class);

    expect(ApprovalRequest::query()->pending()->count())->toBe(1);
});

test('a pending transfer blocks an admin leave request', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    app(OwnershipTransfer::class)->request($organization, $owner, $admin->person);

    expect(fn () => app(MembershipLeave::class)->request($organization, $admin))
        ->toThrow(InvalidArgumentException::class);

    expect(ApprovalRequest::query()->pending()->count())->toBe(1);
});

test('removing the admin cancels their pending leave request', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);

    app(RemoveMember::class)->handle($organization, $admin->person, $owner);

    expect($request->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled)
        ->and($organization->members()->whereKey($admin->person->getKey())->exists())->toBeFalse();
});

test('demoting the admin cancels their pending leave request', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);

    app(ChangeMemberRole::class)->demote($organization, $admin->person, $owner);

    expect($request->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled)
        ->and($admin->person->roleIn($organization))->toBe(Role::Member);
});

test('an expired request can no longer be approved or declined', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);
    $request->update(['expires_at' => now()->subMinute()]);

    expect(app(MembershipLeave::class)->accept($request->fresh(), $owner))->toBeFalse()
        ->and(app(MembershipLeave::class)->decline($request->fresh(), $owner))->toBeFalse();

    expect($request->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($organization->members()->whereKey($admin->person->getKey())->exists())->toBeTrue();
});

test('the requester cannot respond to their own leave request', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);

    expect(fn () => app(MembershipLeave::class)->accept($request, $admin))
        ->toThrow(InvalidArgumentException::class);
});

test('the signed link lets the owner review but rejects the requester', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);

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
