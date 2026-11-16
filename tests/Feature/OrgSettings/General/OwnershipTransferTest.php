<?php

declare(strict_types=1);

use App\Actions\Approvals\CancelApprovalRequest;
use App\Actions\Approvals\OwnershipTransfer;
use App\Actions\Memberships\RemoveMember;
use App\Enums\ApprovalRequestStatus;
use App\Enums\Role;
use App\Livewire\OrgSettings\General\TransferOwnership;
use App\Livewire\Settings\Organizations;
use App\Livewire\Settings\Organizations\TransferApproval;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\OwnershipTransferDeclinedNotification;
use App\Notifications\OwnershipTransferredNotification;
use App\Notifications\OwnershipTransferRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('the owner can request a transfer to a credentialed member', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    expect($transfer->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($transfer->requester_person_id)->toBe($owner->person->getKey())
        ->and($transfer->approver_person_id)->toBe($recipient->person->getKey())
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner)
        ->and($recipient->person->roleIn($organization))->toBe(Role::Admin);

    Notification::assertSentTo($recipient, OwnershipTransferRequestedNotification::class);
    Notification::assertNotSentTo($owner, OwnershipTransferRequestedNotification::class);
});

test('only active credentialed members can receive ownership', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);

    actingAsMember($organization, Role::Owner);

    $credentialLess = Person::factory()->withoutCredential()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(TransferOwnership::class)
        ->call('confirmTransfer')
        ->set('recipientId', (string) $credentialLess->getKey())
        ->set('confirmation', 'Acme')
        ->call('transfer')
        ->assertHasErrors('recipientId');

    expect(ApprovalRequest::query()->exists())->toBeFalse();
});

test('a pending request blocks a new transfer', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    actingAsMember($organization, Role::Owner);
    User::factory()->asMemberOf($organization, Role::Member)->create();
    $second = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $firstTransfer = requestOwnershipTransferForTest($organization);

    expect(fn () => app(OwnershipTransfer::class)->request($organization, Auth::user(), $second->person))
        ->toThrow(InvalidArgumentException::class);

    expect($firstTransfer->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and(ApprovalRequest::query()->pending()->count())->toBe(1);
});

test('the owner can cancel a pending transfer and the recipient row returns to normal', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    Livewire::test(TransferOwnership::class)
        ->assertSee(__('Pending transfer to :name', ['name' => $recipient->name]))
        ->call('cancelTransfer')
        ->assertDontSee(__('Pending transfer to :name', ['name' => $recipient->name]));

    expect($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled);

    loginAsTransferRecipient($recipient, $organization);

    Livewire::test(Organizations::class)
        ->assertDontSeeHtml("dispatch('respond'")
        ->assertSee(__('Leave'));
});

test('the root organization can be transferred', function () {
    Notification::fake();

    $root = Organization::factory()->asRootTenant()->create(['name' => 'Root Org']);
    $owner = actingAsMember($root, Role::Owner);
    $recipient = User::factory()->asMemberOf($root, Role::Admin)->create();

    $transfer = requestOwnershipTransferForTest($root, 'Root Org');

    loginAsTransferRecipient($recipient, $root);

    Livewire::test(TransferApproval::class)
        ->call('respond', $root->getKey())
        ->call('accept');

    expect($recipient->person->roleIn($root))->toBe(Role::Owner)
        ->and($owner->person->roleIn($root))->toBe(Role::Admin)
        ->and($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Accepted);
});

test('the signed email link redirects the recipient and rejects everyone else', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    $url = URL::temporarySignedRoute(
        'organizations.approval.link',
        $transfer->expires_at,
        ['approval' => $transfer->uuid],
    );

    actingAs($recipient);

    get($url)->assertRedirect(route('organizations.index', ['approval' => $transfer->uuid]));

    actingAs(User::factory()->create());

    get($url)->assertForbidden();
});

test('an expired request can no longer be accepted or declined', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);
    $transfer->update(['expires_at' => now()->subMinute()]);

    expect(app(OwnershipTransfer::class)->accept($transfer->fresh(), $recipient))->toBeFalse()
        ->and(app(OwnershipTransfer::class)->decline($transfer->fresh(), $recipient))->toBeFalse();

    expect($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner)
        ->and($recipient->person->roleIn($organization))->toBe(Role::Member);

    Notification::assertNotSentTo(
        [$owner, $recipient],
        OwnershipTransferredNotification::class,
    );
});

test('a member cannot be removed after they become the owner', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    requestOwnershipTransferForTest($organization);

    loginAsTransferRecipient($recipient, $organization);

    Livewire::test(TransferApproval::class)
        ->call('respond', $organization->getKey())
        ->call('accept');

    app(RemoveMember::class)->handle($organization, $recipient->person, $owner);

    expect($recipient->person->fresh()->roleIn($organization))->toBe(Role::Owner)
        ->and($organization->activeOwnerCount())->toBe(1);
});

test('only an owner can request an ownership transfer', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $admin = actingAsMember($organization, Role::Admin);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    expect(fn () => app(OwnershipTransfer::class)->request($organization, $admin, $recipient->person))
        ->toThrow(InvalidArgumentException::class);

    expect(ApprovalRequest::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('the recipient must be another active member with a credential', function (string $recipient) {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);

    $target = match ($recipient) {
        'self' => $owner->person,
        'no credential' => Person::factory()->withoutCredential()->asMemberOf($organization, Role::Member)->create(),
        default => Person::factory()->create(),
    };

    expect(fn () => app(OwnershipTransfer::class)->request($organization, $owner, $target))
        ->toThrow(InvalidArgumentException::class);

    expect(ApprovalRequest::query()->count())->toBe(0);
    Notification::assertNothingSent();
})->with(['self', 'no credential', 'no membership']);

test('the wrong person cannot accept, decline, or cancel a transfer', function (string $action) {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);
    $outsider = User::factory()->create();

    $attempt = match ($action) {
        'accept' => fn () => app(OwnershipTransfer::class)->accept($transfer, $outsider),
        'decline' => fn () => app(OwnershipTransfer::class)->decline($transfer, $outsider),
        'cancel' => fn () => app(CancelApprovalRequest::class)->handle($transfer, $recipient),
    };

    expect($attempt)->toThrow(InvalidArgumentException::class);

    expect($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner)
        ->and($recipient->person->roleIn($organization))->toBe(Role::Member);

    Notification::assertSentToTimes($recipient, OwnershipTransferRequestedNotification::class, 1);
    Notification::assertNotSentTo($owner, OwnershipTransferredNotification::class);
    Notification::assertNotSentTo($owner, OwnershipTransferDeclinedNotification::class);
})->with(['accept', 'decline', 'cancel']);

test('a handled transfer cannot be accepted, declined, or cancelled again', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    expect(app(OwnershipTransfer::class)->accept($transfer, $recipient))->toBeTrue();

    $handled = $transfer->fresh();

    expect(app(OwnershipTransfer::class)->accept($handled, $recipient))->toBeFalse()
        ->and(app(OwnershipTransfer::class)->decline($handled, $recipient))->toBeFalse()
        ->and(app(CancelApprovalRequest::class)->handle($handled, $owner))->toBeFalse();

    expect($recipient->person->roleIn($organization))->toBe(Role::Owner)
        ->and($owner->person->roleIn($organization))->toBe(Role::Admin)
        ->and($handled->status)->toBe(ApprovalRequestStatus::Accepted);

    Notification::assertSentToTimes($owner, OwnershipTransferredNotification::class, 1);
});
