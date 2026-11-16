<?php

declare(strict_types=1);

use App\Actions\Approvals\MembershipLeave;
use App\Enums\ApprovalRequestStatus;
use App\Enums\Role;
use App\Livewire\Settings\Organizations;
use App\Livewire\Settings\Organizations\TransferApproval;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MembershipLeaveDeclinedNotification;
use App\Notifications\OwnershipTransferDeclinedNotification;
use App\Notifications\OwnershipTransferredNotification;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('the recipient can accept and become the owner', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    loginAsTransferRecipient($recipient, $organization);

    Livewire::test(Organizations::class)
        ->assertSee(__('Ownership transfer requested'))
        ->assertSee(__('Respond'));

    Livewire::test(TransferApproval::class)
        ->call('respond', $organization->getKey())
        ->assertSet('showModal', true)
        ->call('accept')
        ->assertSet('showModal', false);

    expect($recipient->person->roleIn($organization))->toBe(Role::Owner)
        ->and($owner->person->roleIn($organization))->toBe(Role::Admin)
        ->and($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Accepted);

    Notification::assertSentTo($owner, OwnershipTransferredNotification::class);
});

test('the recipient can decline and the owner remains in place', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    loginAsTransferRecipient($recipient, $organization);

    Livewire::test(TransferApproval::class)
        ->call('respond', $organization->getKey())
        ->call('decline')
        ->assertSet('showModal', false);

    expect($recipient->person->roleIn($organization))->toBe(Role::Member)
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner)
        ->and($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Declined);

    Notification::assertSentTo($owner, OwnershipTransferDeclinedNotification::class);
});

test('the transfer modal opens from the email query and an expired request cannot be opened', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    loginAsTransferRecipient($recipient, $organization);

    test()->withSession(['auth.password_confirmed_at' => time()]);

    get(route('organizations.index', ['approval' => $transfer->uuid]))
        ->assertOk()
        ->assertSee(__('Ownership transfer request'))
        ->assertSee(__('Accept'))
        ->assertSee(__('Decline'));

    $transfer->update(['expires_at' => now()->subMinute()]);

    Livewire::test(Organizations::class)
        ->assertDontSeeHtml("dispatch('review-approval'");

    Livewire::test(TransferApproval::class)
        ->call('respond', $organization->getKey())
        ->assertNotFound();
});

test('the transfer modal opens from the email query', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    loginAsTransferRecipient($recipient, $organization);

    Livewire::withQueryParams(['approval' => $transfer->uuid])
        ->test(TransferApproval::class)
        ->assertSet('showModal', true);
});

test('accepting after the recipient lost their membership keeps the owner in place', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    $organization->members()->detach($recipient->person->getKey());

    loginAsTransferRecipient($recipient, $organization);

    Livewire::test(TransferApproval::class)
        ->call('respond', $organization->getKey())
        ->call('accept')
        ->assertSet('showModal', false)
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('This ownership transfer is no longer valid.'),
        );

    expect($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner)
        ->and($organization->activeOwnerCount())->toBe(1);

    Notification::assertNotSentTo($owner, OwnershipTransferredNotification::class);
});

test('a modal left open on a stale request reports it as invalid', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    loginAsTransferRecipient($recipient, $organization);

    $component = Livewire::test(TransferApproval::class)
        ->call('respond', $organization->getKey())
        ->assertSet('showModal', true);

    $transfer->update(['expires_at' => now()->subMinute()]);

    $component->call('accept')
        ->assertSet('showModal', false)
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('This ownership transfer is no longer valid.'),
        );

    expect($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner)
        ->and($recipient->person->roleIn($organization))->toBe(Role::Member);
});

test('accepting or declining without an open transfer reports it as invalid', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    loginAsTransferRecipient($recipient, $organization);

    $component = Livewire::test(TransferApproval::class);

    $component->call('accept')
        ->assertSet('showModal', false)
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('This request is no longer valid.'),
        );

    $component->call('decline')
        ->assertSet('showModal', false)
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('This request is no longer valid.'),
        );
});

test('a transfer opened by another recipient is treated as invalid', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();
    $target = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization, recipient: $target->person);

    loginAsTransferRecipient($recipient, $organization);

    Livewire::test(TransferApproval::class)
        ->set('approvalId', $transfer->getKey())
        ->set('approvalType', 'ownership_transfer')
        ->call('accept')
        ->assertSet('showModal', false)
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('This ownership transfer is no longer valid.'),
        );

    expect($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner)
        ->and($target->person->roleIn($organization))->toBe(Role::Member);
});

test('the owner can approve an admin leave request', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);

    actingAs($owner);

    Livewire::test(Organizations::class)
        ->assertSee(__('Leave request from :name', ['name' => $admin->name]));

    Livewire::test(TransferApproval::class)
        ->call('respond', $organization->getKey())
        ->assertSet('showModal', true)
        ->call('accept')
        ->assertSet('showModal', false);

    expect($request->fresh()->status)->toBe(ApprovalRequestStatus::Accepted)
        ->and($organization->members()->whereKey($admin->person->getKey())->exists())->toBeFalse()
        ->and($admin->fresh())->not->toBeNull();
});

test('the owner can decline an admin leave request', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);

    actingAs($owner);

    Livewire::test(TransferApproval::class)
        ->call('respond', $organization->getKey())
        ->call('decline');

    expect($request->fresh()->status)->toBe(ApprovalRequestStatus::Declined)
        ->and($organization->members()->whereKey($admin->person->getKey())->exists())->toBeTrue();

    Notification::assertSentTo($admin, MembershipLeaveDeclinedNotification::class);
});

test('an approved leave request opens the review from the email query', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $request = app(MembershipLeave::class)->request($organization, $admin);

    actingAs($owner);
    session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);
    test()->withSession(['auth.password_confirmed_at' => time()]);

    get(route('organizations.index', ['approval' => $request->uuid]))
        ->assertOk()
        ->assertSee(__('Leave request'))
        ->assertSee(__('Approve'))
        ->assertSee(__('Decline'));
});
