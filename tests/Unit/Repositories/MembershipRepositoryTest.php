<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Repositories\MembershipRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('paginates the organization members', function () {
    $organization = Organization::factory()->create();

    User::factory()->count(12)->asMemberOf($organization)->create();

    $members = app(MembershipRepository::class)->get($organization);

    expect($members->total())->toBe(12)
        ->and($members->count())->toBe(10);
});

it('searches members by name and email', function () {
    $organization = Organization::factory()->create();

    User::factory()->asMemberOf($organization)->create([
        'name' => 'Alice Wonder',
        'email' => 'alice@example.com',
    ]);
    User::factory()->asMemberOf($organization)->create([
        'name' => 'Bob Builder',
        'email' => 'bob@example.com',
    ]);

    $members = app(MembershipRepository::class);

    expect($members->get($organization, 'alice')->pluck('name')->all())
        ->toBe(['Alice Wonder'])
        ->and($members->get($organization, 'bob@example')->pluck('name')->all())
        ->toBe(['Bob Builder']);
});

it('orders members by the membership start date', function () {
    $organization = Organization::factory()->create();

    $older = User::factory()->asMemberOf($organization)->create(['name' => 'Older']);
    $newer = User::factory()->asMemberOf($organization)->create(['name' => 'Newer']);

    $organization->members()->updateExistingPivot($older->person->getKey(), [
        'start_date' => now()->subDay(),
    ]);
    $organization->members()->updateExistingPivot($newer->person->getKey(), [
        'start_date' => now(),
    ]);

    $members = app(MembershipRepository::class);

    expect($members->get($organization, sortDirection: 'asc')->pluck('name')->all())
        ->toBe(['Older', 'Newer'])
        ->and($members->get($organization, sortDirection: 'desc')->pluck('name')->all())
        ->toBe(['Newer', 'Older']);
});
