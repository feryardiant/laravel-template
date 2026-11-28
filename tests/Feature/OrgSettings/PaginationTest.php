<?php

declare(strict_types=1);

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Livewire\OrgSettings\ApprovalHistory;
use App\Livewire\OrgSettings\Subsidiaries;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('paginates the approval history', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $approver = Person::factory()->withoutCredential()->create();

    foreach (range(1, 25) as $index) {
        $status = $index === 1 ? ApprovalRequestStatus::Pending : ApprovalRequestStatus::Accepted;

        ApprovalRequest::create([
            'organization_id' => $organization->getKey(),
            'type' => ApprovalRequestType::OwnershipTransfer,
            'requester_person_id' => $owner->person->getKey(),
            'approver_person_id' => $approver->getKey(),
            'status' => $status,
            'expires_at' => now()->addDay(),
            'accepted_at' => $status === ApprovalRequestStatus::Accepted ? now() : null,
        ]);
    }

    $component = Livewire::test(ApprovalHistory::class);

    expect($component->instance()->approvals->count())->toBe(20)
        ->and($component->instance()->approvals->total())->toBe(25);

    $component->call('gotoPage', 2);

    expect($component->instance()->approvals->count())->toBe(5);
});

it('paginates the subsidiary tree', function () {
    $root = Organization::factory()->asRootTenant()->create();
    actingAsMember($root, Role::Owner);

    foreach (range(1, 60) as $index) {
        $subsidiary = Organization::factory()->asTenantOf($root)->create(['name' => "Subsidiary {$index}"]);

        $root->subsidiaries()->attach($subsidiary, [
            'type' => OrganizationRelationshipType::Subsidiary,
            'kind' => SubsidiaryKind::Subsidiary,
        ]);
    }

    $component = Livewire::test(Subsidiaries::class);

    expect($component->instance()->subsidiaries->count())->toBe(50)
        ->and($component->instance()->subsidiaries->total())->toBe(60);

    $component->call('gotoPage', 2);

    expect($component->instance()->subsidiaries->count())->toBe(10);
});
