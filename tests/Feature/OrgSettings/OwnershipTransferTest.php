<?php

declare(strict_types=1);

use App\Enums\OwnershipTransferStatus;
use App\Enums\Role;
use App\Livewire\OrgSettings\General;
use App\Livewire\Settings\Organizations;
use App\Models\Organization;
use App\Models\OrganizationOwnershipTransfer;
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

function requestOwnershipTransferForTest(Organization $organization, string $confirmation = 'Acme', ?Person $recipient = null): OrganizationOwnershipTransfer
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

    return OrganizationOwnershipTransfer::query()->latest('id')->firstOrFail();
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

    expect($transfer->status)->toBe(OwnershipTransferStatus::Pending)
        ->and($transfer->from_person_id)->toBe($owner->person->getKey())
        ->and($transfer->to_person_id)->toBe($recipient->person->getKey())
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

    expect(OrganizationOwnershipTransfer::query()->exists())->toBeFalse();
});

test('a new request supersedes the previous pending transfer', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    actingAsMember($organization, Role::Owner);
    $first = User::factory()->asMemberOf($organization, Role::Member)->create();
    $second = User::factory()->asMemberOf($organization, Role::Admin)->create();

    $firstTransfer = requestOwnershipTransferForTest($organization);
    $secondTransfer = requestOwnershipTransferForTest($organization, 'Acme', $second->person);

    expect($firstTransfer->fresh()->status)->toBe(OwnershipTransferStatus::Cancelled)
        ->and($secondTransfer->fresh()->status)->toBe(OwnershipTransferStatus::Pending);
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

    expect($transfer->fresh()->status)->toBe(OwnershipTransferStatus::Cancelled);

    loginAsTransferRecipient($recipient, $organization);

    Livewire::test(Organizations::class)
        ->assertDontSeeHtml('openTransfer(')
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
        ->call('openTransfer', $root->getKey())
        ->call('acceptTransfer');

    expect($recipient->person->roleIn($root))->toBe(Role::Owner)
        ->and($owner->person->roleIn($root))->toBe(Role::Admin)
        ->and($transfer->fresh()->status)->toBe(OwnershipTransferStatus::Accepted);
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
        ->call('openTransfer', $organization->getKey())
        ->assertSet('showTransferModal', true)
        ->call('acceptTransfer')
        ->assertSet('showTransferModal', false);

    expect($recipient->person->roleIn($organization))->toBe(Role::Owner)
        ->and($owner->person->roleIn($organization))->toBe(Role::Admin)
        ->and($transfer->fresh()->status)->toBe(OwnershipTransferStatus::Accepted);

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
        ->call('openTransfer', $organization->getKey())
        ->call('declineTransfer')
        ->assertSet('showTransferModal', false);

    expect($recipient->person->roleIn($organization))->toBe(Role::Member)
        ->and($owner->person->roleIn($organization))->toBe(Role::Owner)
        ->and($transfer->fresh()->status)->toBe(OwnershipTransferStatus::Declined);

    Notification::assertSentTo($owner, OwnershipTransferDeclinedNotification::class);
});

test('the signed email link redirects the recipient and rejects everyone else', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    $url = URL::temporarySignedRoute(
        'organizations.transfer.link',
        $transfer->expires_at,
        ['transfer' => $transfer->uuid],
    );

    actingAs($recipient);

    get($url)->assertRedirect(route('organizations.index', ['transfer' => $transfer->uuid]));

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

    get(route('organizations.index', ['transfer' => $transfer->uuid]))
        ->assertOk()
        ->assertSee(__('Ownership transfer request'))
        ->assertSee(__('Accept'))
        ->assertSee(__('Decline'));

    $transfer->update(['expires_at' => now()->subMinute()]);

    Livewire::test(Organizations::class)
        ->assertDontSeeHtml('openTransfer(')
        ->call('openTransfer', $organization->getKey())
        ->assertNotFound();
});

test('the transfer modal opens from the email query', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = requestOwnershipTransferForTest($organization);

    loginAsTransferRecipient($recipient, $organization);

    Livewire::withQueryParams(['transfer' => $transfer->uuid])
        ->test(Organizations::class)
        ->assertSet('showTransferModal', true);
});
