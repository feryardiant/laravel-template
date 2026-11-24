<?php

declare(strict_types=1);

use App\Actions\Memberships\LeaveWorkspace;
use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('requests approval when an admin leaves', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    expect(app(LeaveWorkspace::class)->handle($organization, $admin))->toBe('requested')
        ->and(ApprovalRequest::query()->where('requester_person_id', $admin->person->getKey())->exists())->toBeTrue();
});

it('lets a member leave directly', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    User::factory()->asMemberOf($organization, Role::Owner)->create();
    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    expect(app(LeaveWorkspace::class)->handle($organization, $member))->toBe('left')
        ->and($organization->members()->whereKey($member->person->getKey())->exists())->toBeFalse();
});

it('refuses to leave as the last owner', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();

    expect(app(LeaveWorkspace::class)->handle($organization, $owner))->toBe('last_owner');
});
