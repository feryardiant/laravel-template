<?php

declare(strict_types=1);

use App\Actions\Organizations\RequestOwnershipTransfer;
use App\Enums\OwnershipTransferStatus;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lets the recipient dismiss, respond, and accept an ownership transfer', function () {
    $owner = browserMember(Role::Owner);
    $organization = Organization::query()->firstOrFail();
    $recipient = User::factory()->asMemberOf($organization, Role::Member)->create();

    $transfer = app(RequestOwnershipTransfer::class)->handle($organization, $owner, $recipient->person);

    actingAs($recipient);

    visit('/user/confirm-password')
        ->fill('password', 'password')
        ->click(__('Confirm'))
        ->wait(0.5);

    $page = visit("/settings/organizations?transfer={$transfer->uuid}");

    $page->assertSee(__('Ownership transfer request'))
        ->assertSee(__('Accept'))
        ->assertSee(__('Decline'))
        ->click(__('Dismiss'))
        ->wait(0.5)
        ->assertDontSee(__('Decline'))
        ->assertSee(__('Ownership transfer requested'));

    expect($transfer->fresh()->status)->toBe(OwnershipTransferStatus::Pending);

    $page->click(__('Respond'))
        ->wait(0.5)
        ->click(__('Accept'))
        ->assertSee(__('You are now the owner of :organization.', ['organization' => $organization->name]));

    expect($recipient->person->fresh()->roleIn($organization))->toBe(Role::Owner)
        ->and($owner->person->fresh()->roleIn($organization))->toBe(Role::Admin)
        ->and($transfer->fresh()->status)->toBe(OwnershipTransferStatus::Accepted);
});
