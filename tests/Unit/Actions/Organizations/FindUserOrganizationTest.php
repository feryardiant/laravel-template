<?php

declare(strict_types=1);

use App\Actions\Organizations\FindUserOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns an organization the user belongs to', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->asMemberOf($organization)->create();

    $found = app(FindUserOrganization::class)->handle($user, $organization->getKey());

    expect($found->is($organization))->toBeTrue();
});

it('rejects an organization the user does not belong to', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withProfile()->create();

    expect(fn () => app(FindUserOrganization::class)->handle($user, $organization->getKey()))
        ->toThrow(ModelNotFoundException::class);
});
