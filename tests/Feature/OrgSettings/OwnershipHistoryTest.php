<?php

declare(strict_types=1);

use App\Enums\OwnershipTransferStatus;
use App\Enums\Role;
use App\Livewire\OrgSettings\OwnershipHistory;
use App\Models\Organization;
use App\Models\OrganizationOwnershipTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function ownershipTransferForHistory(
    Organization $organization,
    User $from,
    User $to,
    OwnershipTransferStatus $status = OwnershipTransferStatus::Pending,
    ?Carbon $createdAt = null,
    ?Carbon $changedAt = null,
): OrganizationOwnershipTransfer {
    $transfer = OrganizationOwnershipTransfer::create([
        'organization_id' => $organization->getKey(),
        'from_person_id' => $from->person->getKey(),
        'to_person_id' => $to->person->getKey(),
        'status' => $status,
        'expires_at' => $changedAt ?? now()->addDay(),
        'accepted_at' => $status === OwnershipTransferStatus::Accepted ? $changedAt : null,
        'declined_at' => $status === OwnershipTransferStatus::Declined ? $changedAt : null,
    ]);

    if ($createdAt !== null) {
        $transfer->created_at = $createdAt;
        $transfer->saveQuietly();
    }

    return $transfer;
}

test('guests are redirected from the ownership history page', function () {
    get(route('org-settings.ownership-history'))->assertRedirect(route('login'));
});

test('only the owner can open the ownership history page', function (Role $role) {
    $organization = Organization::factory()->create();

    actingAsMember($organization, $role);

    get(route('org-settings.ownership-history'))->assertForbidden();
})->with([Role::Admin, Role::Member]);

test('the owner sees the current organization transfers, newest first', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $newer = ownershipTransferForHistory(
        $organization,
        $owner,
        $recipient,
        OwnershipTransferStatus::Declined,
        Carbon::parse('2026-01-03 10:00'),
        Carbon::parse('2026-01-03 11:00'),
    );

    $older = ownershipTransferForHistory(
        $organization,
        $owner,
        $recipient,
        OwnershipTransferStatus::Accepted,
        Carbon::parse('2026-01-02 10:00'),
        Carbon::parse('2026-01-02 11:00'),
    );

    $foreignOrganization = Organization::factory()->create();
    $foreignOwner = User::factory()->asMemberOf($foreignOrganization, Role::Owner)->create();
    $foreignRecipient = User::factory()->asMemberOf($foreignOrganization, Role::Member)->create();
    ownershipTransferForHistory($foreignOrganization, $foreignOwner, $foreignRecipient);

    $component = Livewire::test(OwnershipHistory::class);

    expect($component->instance()->transfers->pluck('id')->all())->toBe([$newer->getKey(), $older->getKey()]);

    $component
        ->assertSee(__('Transfer ownership from :from to :to', ['from' => $owner->person->name, 'to' => $recipient->person->name]))
        ->assertSee(__('Ditolak'))
        ->assertSee(__('Diterima'))
        ->assertDontSee(__('Transfer ownership from :from to :to', ['from' => $foreignOwner->person->name, 'to' => $foreignRecipient->person->name]));
});

test('a pending row shows the expiry timestamp', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $pending = ownershipTransferForHistory(
        $organization,
        $owner,
        $recipient,
        OwnershipTransferStatus::Pending,
        Carbon::parse('2026-02-01 08:00'),
        Carbon::parse('2026-02-08 09:30'),
    );

    Livewire::test(OwnershipHistory::class)
        ->assertSee(__('Tertunda'))
        ->assertSee(__('Dibuat pada'))
        ->assertSee($pending->created_at->translatedFormat('d M Y H:i'))
        ->assertSee(__('Kedaluwarsa pada'))
        ->assertSee($pending->expires_at->translatedFormat('d M Y H:i'));
});

test('accepted and declined rows show their handled timestamps', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $accepted = ownershipTransferForHistory($organization, $owner, $recipient, OwnershipTransferStatus::Accepted, changedAt: Carbon::parse('2026-03-02 10:00'));
    $declined = ownershipTransferForHistory($organization, $owner, $recipient, OwnershipTransferStatus::Declined, changedAt: Carbon::parse('2026-03-03 10:00'));

    Livewire::test(OwnershipHistory::class)
        ->assertSee(__('Diterima pada'))
        ->assertSee($accepted->accepted_at->translatedFormat('d M Y H:i'))
        ->assertSee(__('Ditolak pada'))
        ->assertSee($declined->declined_at->translatedFormat('d M Y H:i'));
});

test('a cancelled row shows only the created timestamp', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $cancelled = ownershipTransferForHistory($organization, $owner, $recipient, OwnershipTransferStatus::Cancelled);

    Livewire::test(OwnershipHistory::class)
        ->assertSee(__('Dibatalkan'))
        ->assertSee(__('Dibuat pada'))
        ->assertSee($cancelled->created_at->translatedFormat('d M Y H:i'))
        ->assertDontSee(__('Kedaluwarsa pada'))
        ->assertDontSee(__('Diterima pada'))
        ->assertDontSee(__('Ditolak pada'));
});

test('an organization without transfers shows the empty state', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    Livewire::test(OwnershipHistory::class)
        ->assertOk()
        ->assertSee(__('No ownership transfers yet.'));
});
