<?php

declare(strict_types=1);

use App\Actions\Memberships\RemoveMember;
use App\Actions\Organizations\AcceptOwnershipTransfer;
use App\Actions\Organizations\CancelApprovalRequest;
use App\Actions\Organizations\DeclineOwnershipTransfer;
use App\Actions\Organizations\RequestOwnershipTransfer;
use App\Enums\ApprovalRequestStatus;
use App\Enums\Role;
use App\Livewire\OrgSettings\General;
use App\Livewire\Settings\Organizations;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\OwnershipTransferDeclinedNotification;
use App\Notifications\OwnershipTransferredNotification;
use App\Notifications\OwnershipTransferRequestedNotification;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function requestOwnershipTransferForTest(Organization $organization, string $confirmation = 'Acme', ?Person $recipient = null): ApprovalRequest
{
    $recipientId = (string) ($recipient?->getKey() ?? $organization->members()
        ->whereKeyNot(Auth::user()?->person?->getKey())
        ->whereHas('user')
        ->firstOrFail()
        ->getKey());

    Livewire::test(General::class)
        ->call('confirmTransfer')
        ->set('transferRecipientId', $recipientId)
        ->set('transferConfirmation', $confirmation)
        ->call('transfer')
        ->assertHasNoErrors();

    return ApprovalRequest::query()->latest('id')->firstOrFail();
}

function loginAsTransferRecipient(User $user, Organization $organization): void
{
    actingAs($user);
    session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);
}

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

    Livewire::test(General::class)
        ->call('confirmTransfer')
        ->set('transferRecipientId', (string) $credentialLess->getKey())
        ->set('transferConfirmation', 'Acme')
        ->call('transfer')
        ->assertHasErrors('transferRecipientId');

    expect(ApprovalRequest::query()->exists())->toBeFalse();
});

test('a pending request blocks a new transfer', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    actingAsMember($organization, Role::Owner);
    User::factory()->asMemberOf($organization, Role::Member)->create();
    $second = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $firstTransfer = requestOwnershipTransferForTest($organization);

    expect(fn () => app(RequestOwnershipTransfer::class)->handle($organization, Auth::user(), $second->person))
        ->toThrow(InvalidArgumentException::class);

    expect($firstTransfer->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and(ApprovalRequest::query()->pending()->count())->toBe(1);
});

test('the owner can cancel a pending transfer and the recipient row returns to normal', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    Livewire::test(General::class)
        ->assertSee(__('Pending transfer to :name', ['name' => $recipient->name]))
        ->call('cancelTransfer')
        ->assertDontSee(__('Pending transfer to :name', ['name' => $recipient->name]));

    expect($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled);

    loginAsTransferRecipient($recipient, $organization);

    Livewire::test(Organizations::class)
        ->assertDontSeeHtml('openApproval(')
        ->assertSee(__('Leave'));
});

test('the danger zone is visible only to the owner', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);

    actingAsMember($organization, Role::Owner);

    get(route('org-settings.general'))
        ->assertOk()
        ->assertSee(__('Danger Zone'))
        ->assertSee(__('Transfer Ownership'))
        ->assertSee(__('Delete Organization'));

    actingAsMember($organization, Role::Admin);

    get(route('org-settings.general'))
        ->assertOk()
        ->assertDontSee(__('Danger Zone'));
});

test('the root organization can be transferred', function () {
    Notification::fake();

    $root = Organization::factory()->asRootTenant()->create(['name' => 'Root Org']);
    $owner = actingAsMember($root, Role::Owner);
    $recipient = User::factory()->asMemberOf($root, Role::Admin)->create();

    $transfer = requestOwnershipTransferForTest($root, 'Root Org');

    loginAsTransferRecipient($recipient, $root);

    Livewire::test(Organizations::class)
        ->call('openApproval', $root->getKey())
        ->call('acceptApproval');

    expect($recipient->person->roleIn($root))->toBe(Role::Owner)
        ->and($owner->person->roleIn($root))->toBe(Role::Admin)
        ->and($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Accepted);
});

test('the recipient can accept and become the owner', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    loginAsTransferRecipient($recipient, $organization);

    Livewire::test(Organizations::class)
        ->assertSee(__('Ownership transfer requested'))
        ->assertSee(__('Respond'))
        ->call('openApproval', $organization->getKey())
        ->assertSet('showApprovalModal', true)
        ->call('acceptApproval')
        ->assertSet('showApprovalModal', false);

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

    Livewire::test(Organizations::class)
        ->call('openApproval', $organization->getKey())
        ->call('declineApproval')
        ->assertSet('showApprovalModal', false);

    expect($recipient->person->roleIn($organization))->toBe(Role::Member)
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner)
        ->and($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Declined);

    Notification::assertSentTo($owner, OwnershipTransferDeclinedNotification::class);
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
        ->assertDontSeeHtml('openApproval(')
        ->call('openApproval', $organization->getKey())
        ->assertNotFound();
});

test('the transfer modal opens from the email query', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    loginAsTransferRecipient($recipient, $organization);

    Livewire::withQueryParams(['approval' => $transfer->uuid])
        ->test(Organizations::class)
        ->assertSet('showApprovalModal', true);
});

test('an expired request can no longer be accepted or declined', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);
    $transfer->update(['expires_at' => now()->subMinute()]);

    expect(app(AcceptOwnershipTransfer::class)->handle($transfer->fresh(), $recipient))->toBeFalse()
        ->and(app(DeclineOwnershipTransfer::class)->handle($transfer->fresh(), $recipient))->toBeFalse();

    expect($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner)
        ->and($recipient->person->roleIn($organization))->toBe(Role::Member);

    Notification::assertNotSentTo(
        [$owner, $recipient],
        OwnershipTransferredNotification::class,
    );
});

test('accepting after the recipient lost their membership keeps the owner in place', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    $organization->members()->detach($recipient->person->getKey());

    loginAsTransferRecipient($recipient, $organization);

    Livewire::test(Organizations::class)
        ->call('openApproval', $organization->getKey())
        ->call('acceptApproval')
        ->assertSet('showApprovalModal', false)
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

    $component = Livewire::test(Organizations::class)
        ->call('openApproval', $organization->getKey())
        ->assertSet('showApprovalModal', true);

    $transfer->update(['expires_at' => now()->subMinute()]);

    $component->call('acceptApproval')
        ->assertSet('showApprovalModal', false)
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('This ownership transfer is no longer valid.'),
        );

    expect($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner)
        ->and($recipient->person->roleIn($organization))->toBe(Role::Member);
});

test('a member cannot be removed after they become the owner', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    requestOwnershipTransferForTest($organization);

    loginAsTransferRecipient($recipient, $organization);

    Livewire::test(Organizations::class)
        ->call('openApproval', $organization->getKey())
        ->call('acceptApproval');

    app(RemoveMember::class)->handle($organization, $recipient->person, $owner);

    expect($recipient->person->fresh()->roleIn($organization))->toBe(Role::Owner)
        ->and($organization->activeOwnerCount())->toBe(1);
});

test('only an owner can request an ownership transfer', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $admin = actingAsMember($organization, Role::Admin);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    expect(fn () => app(RequestOwnershipTransfer::class)->handle($organization, $admin, $recipient->person))
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

    expect(fn () => app(RequestOwnershipTransfer::class)->handle($organization, $owner, $target))
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
        'accept' => fn () => app(AcceptOwnershipTransfer::class)->handle($transfer, $outsider),
        'decline' => fn () => app(DeclineOwnershipTransfer::class)->handle($transfer, $outsider),
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

    expect(app(AcceptOwnershipTransfer::class)->handle($transfer, $recipient))->toBeTrue();

    $handled = $transfer->fresh();

    expect(app(AcceptOwnershipTransfer::class)->handle($handled, $recipient))->toBeFalse()
        ->and(app(DeclineOwnershipTransfer::class)->handle($handled, $recipient))->toBeFalse()
        ->and(app(CancelApprovalRequest::class)->handle($handled, $owner))->toBeFalse();

    expect($recipient->person->roleIn($organization))->toBe(Role::Owner)
        ->and($owner->person->roleIn($organization))->toBe(Role::Admin)
        ->and($handled->status)->toBe(ApprovalRequestStatus::Accepted);

    Notification::assertSentToTimes($owner, OwnershipTransferredNotification::class, 1);
});

test('accepting or declining without an open transfer reports it as invalid', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    loginAsTransferRecipient($recipient, $organization);

    $component = Livewire::test(Organizations::class);

    $component->call('acceptApproval')
        ->assertSet('showApprovalModal', false)
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('This request is no longer valid.'),
        );

    $component->call('declineApproval')
        ->assertSet('showApprovalModal', false)
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

    Livewire::test(Organizations::class)
        ->set('approvalId', $transfer->getKey())
        ->set('approvalType', 'ownership_transfer')
        ->call('acceptApproval')
        ->assertSet('showApprovalModal', false)
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('This ownership transfer is no longer valid.'),
        );

    expect($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner)
        ->and($target->person->roleIn($organization))->toBe(Role::Member);
});
