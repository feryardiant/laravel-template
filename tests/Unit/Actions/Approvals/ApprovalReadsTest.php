<?php

declare(strict_types=1);

use App\Actions\Approvals\FindApprovalRequest;
use App\Actions\Approvals\ListApprovals;
use App\Actions\Approvals\ListOwnershipTransferCandidates;
use App\Actions\Approvals\ListPendingApprovalsForPerson;
use App\Actions\Memberships\CreateMember;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function approvalForReads(
    Organization $organization,
    Person $requester,
    Person $approver,
    ApprovalRequestType $type = ApprovalRequestType::OwnershipTransfer,
    ApprovalRequestStatus $status = ApprovalRequestStatus::Pending,
): ApprovalRequest {
    return ApprovalRequest::create([
        'organization_id' => $organization->getKey(),
        'type' => $type,
        'requester_person_id' => $requester->getKey(),
        'approver_person_id' => $approver->getKey(),
        'status' => $status,
        'expires_at' => now()->addDay(),
    ]);
}

it('lists the organization approvals', function () {
    $organization = Organization::factory()->create();
    $requester = Person::factory()->withoutCredential()->create();
    $approver = Person::factory()->withoutCredential()->create();
    $approval = approvalForReads($organization, $requester, $approver);

    $approvals = app(ListApprovals::class)->handle($organization);

    expect($approvals)->toHaveCount(1)
        ->and($approvals->first()?->is($approval))->toBeTrue();
});

it('lists pending approvals for the requester and approver', function () {
    $organization = Organization::factory()->create();
    $requester = Person::factory()->withoutCredential()->create();
    $approver = Person::factory()->withoutCredential()->create();
    $approval = approvalForReads($organization, $requester, $approver);

    expect(app(ListPendingApprovalsForPerson::class)->handle($requester)->pluck('id')->all())
        ->toBe([$approval->getKey()])
        ->and(app(ListPendingApprovalsForPerson::class)->handle($approver)->pluck('id')->all())
        ->toBe([$approval->getKey()]);
});

it('finds approvals by link, organization, approver, and requester', function () {
    $organization = Organization::factory()->create();
    $requester = Person::factory()->withoutCredential()->create();
    $approver = User::factory()->asMemberOf($organization)->create();
    $approval = approvalForReads($organization, $requester, $approver->person);

    $find = app(FindApprovalRequest::class);

    expect($find->openForApproverByUuid($approver, $approval->uuid)?->is($approval))->toBeTrue()
        ->and($find->openForApproverByOrganization($approver, $organization->getKey())?->is($approval))->toBeTrue()
        ->and($find->forApprover($approver, $approval->getKey())?->is($approval))->toBeTrue()
        ->and($find->pendingForOrganization($organization)?->is($approval))->toBeTrue()
        ->and($find->forRequester($approver, $approval->getKey(), ApprovalRequestType::OwnershipTransfer))->toBeNull();
});

it('lists active credentialed members other than the owner', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $member = User::factory()->asMemberOf($organization, Role::Member)->create();
    $credentialless = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $credentialless, startDate: now(), role: Role::Member);

    $candidates = app(ListOwnershipTransferCandidates::class)->handle($organization, $owner->person);

    expect($candidates->pluck('id')->all())->toBe([$member->person->getKey()])
        ->and($candidates->contains($credentialless))->toBeFalse();
});
