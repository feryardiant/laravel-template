<?php

declare(strict_types=1);

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\Role;
use App\Livewire\OrgSettings\ApprovalHistory;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function approvalForHistory(
    Organization $organization,
    Person $requester,
    Person $approver,
    ApprovalRequestType $type = ApprovalRequestType::OwnershipTransfer,
    ApprovalRequestStatus $status = ApprovalRequestStatus::Pending,
    ?Carbon $createdAt = null,
    ?Carbon $changedAt = null,
): ApprovalRequest {
    $approval = ApprovalRequest::create([
        'organization_id' => $organization->getKey(),
        'type' => $type,
        'requester_person_id' => $requester->getKey(),
        'approver_person_id' => $approver->getKey(),
        'status' => $status,
        'expires_at' => $changedAt ?? now()->addDay(),
        'accepted_at' => $status === ApprovalRequestStatus::Accepted ? $changedAt : null,
        'declined_at' => $status === ApprovalRequestStatus::Declined ? $changedAt : null,
    ]);

    if ($createdAt !== null) {
        $approval->created_at = $createdAt;
        $approval->saveQuietly();
    }

    return $approval;
}

test('guests are redirected from the approvals history page', function () {
    get(route('org-settings.approval-history'))->assertRedirect(route('login'));
});

test('only the owner can open the approvals history page', function (Role $role) {
    $organization = Organization::factory()->create();

    actingAsMember($organization, $role);

    get(route('org-settings.approval-history'))->assertForbidden();
})->with([Role::Admin, Role::Member]);

test('the owner sees both request types newest first', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $newer = approvalForHistory(
        $organization,
        $owner->person,
        $recipient->person,
        ApprovalRequestType::MembershipLeave,
        ApprovalRequestStatus::Declined,
        Carbon::parse('2026-01-03 10:00'),
        Carbon::parse('2026-01-03 11:00'),
    );

    $older = approvalForHistory(
        $organization,
        $owner->person,
        $recipient->person,
        ApprovalRequestType::OwnershipTransfer,
        ApprovalRequestStatus::Accepted,
        Carbon::parse('2026-01-02 10:00'),
        Carbon::parse('2026-01-02 11:00'),
    );

    $foreignOrganization = Organization::factory()->create();
    $foreignOwner = User::factory()->asMemberOf($foreignOrganization, Role::Owner)->create();
    $foreignRecipient = User::factory()->asMemberOf($foreignOrganization, Role::Member)->create();
    approvalForHistory($foreignOrganization, $foreignOwner->person, $foreignRecipient->person);

    $component = Livewire::test(ApprovalHistory::class);

    expect($component->instance()->approvals->pluck('id')->all())->toBe([$newer->getKey(), $older->getKey()]);

    $component
        ->assertSee(__(':name requested to leave', ['name' => $owner->person->name]))
        ->assertSee(__('Transfer ownership from :from to :to', ['from' => $owner->person->name, 'to' => $recipient->person->name]))
        ->assertSee(__('Ditolak'))
        ->assertSee(__('Diterima'))
        ->assertDontSee(__('Transfer ownership from :from to :to', ['from' => $foreignOwner->person->name, 'to' => $foreignRecipient->person->name]));
});

test('a pending row shows the expiry timestamp', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $pending = approvalForHistory(
        $organization,
        $owner->person,
        $recipient->person,
        status: ApprovalRequestStatus::Pending,
        createdAt: Carbon::parse('2026-02-01 08:00'),
        changedAt: Carbon::parse('2026-02-08 09:30'),
    );

    Livewire::test(ApprovalHistory::class)
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

    $accepted = approvalForHistory($organization, $owner->person, $recipient->person, status: ApprovalRequestStatus::Accepted, changedAt: Carbon::parse('2026-03-02 10:00'));
    $declined = approvalForHistory($organization, $owner->person, $recipient->person, status: ApprovalRequestStatus::Declined, changedAt: Carbon::parse('2026-03-03 10:00'));

    Livewire::test(ApprovalHistory::class)
        ->assertSee(__('Diterima pada'))
        ->assertSee($accepted->accepted_at->translatedFormat('d M Y H:i'))
        ->assertSee(__('Ditolak pada'))
        ->assertSee($declined->declined_at->translatedFormat('d M Y H:i'));
});

test('a cancelled row shows only the created timestamp', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $cancelled = approvalForHistory($organization, $owner->person, $recipient->person, status: ApprovalRequestStatus::Cancelled);

    Livewire::test(ApprovalHistory::class)
        ->assertSee(__('Dibatalkan'))
        ->assertSee(__('Dibuat pada'))
        ->assertSee($cancelled->created_at->translatedFormat('d M Y H:i'))
        ->assertDontSee(__('Kedaluwarsa pada'))
        ->assertDontSee(__('Diterima pada'))
        ->assertDontSee(__('Ditolak pada'));
});

test('an organization without requests shows the empty state', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    Livewire::test(ApprovalHistory::class)
        ->assertOk()
        ->assertSee(__('No approval requests yet.'));
});
