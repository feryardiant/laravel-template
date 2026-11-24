<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Repositories\OrganizationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns an organization the user belongs to', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->asMemberOf($organization)->create();

    $found = app(OrganizationRepository::class)->findUser($user, $organization->getKey());

    expect($found?->is($organization))->toBeTrue();
});

it('returns null for an organization the user does not belong to', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withProfile()->create();

    expect(app(OrganizationRepository::class)->findUser($user, $organization->getKey()))
        ->toBeNull();
});
