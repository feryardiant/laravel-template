<?php

declare(strict_types=1);

use App\Actions\Memberships\LeaveOrganization;
use App\Enums\Role;
use App\Events\Memberships\MemberLeft;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;

uses(RefreshDatabase::class);

it('rolls back the leave when a MemberLeft listener fails', function () {
    $organization = Organization::factory()->create();

    $user = User::factory()->asMemberOf($organization, Role::Member)->create();

    Event::listen(MemberLeft::class, function (): void {
        throw new RuntimeException('Notification publication failed.');
    });

    expect(fn () => app(LeaveOrganization::class)->handle($user, $organization))
        ->toThrow(RuntimeException::class);

    expect($organization->members()->whereKey($user->person->getKey())->exists())->toBeTrue();
});

it('does not detach an admin without approval', function () {
    $organization = Organization::factory()->create();

    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    expect(app(LeaveOrganization::class)->handle($admin, $organization))->toBeFalse()
        ->and($organization->members()->whereKey($admin->person->getKey())->exists())->toBeTrue();
});
