<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Members\Index;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('removing a member keeps the person and credential', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();
    $person = $member->person;

    Livewire::test(Index::class)
        ->call('removeMember', $person->getKey())
        ->assertHasNoErrors();

    expect($organization->members()->whereKey($person->getKey())->exists())->toBeFalse();
    expect($person->fresh())->not->toBeNull();
    expect($person->fresh()->user)->not->toBeNull();
});

test('a plain member cannot remove another member', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(Index::class)
        ->call('removeMember', $member->person->getKey())
        ->assertForbidden();

    expect($organization->members()->whereKey($member->person->getKey())->exists())->toBeTrue();
});

test('an admin cannot remove an owner', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();

    Livewire::test(Index::class)
        ->call('removeMember', $owner->person->getKey())
        ->assertForbidden();

    expect($organization->members()->whereKey($owner->person->getKey())->exists())->toBeTrue();
});

test('a user cannot remove their own membership', function () {
    $organization = Organization::factory()->create();

    $actor = actingAsMember($organization, Role::Owner);

    Livewire::test(Index::class)
        ->call('removeMember', $actor->person->getKey())
        ->assertForbidden();
});

test('an admin can remove selected members', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $members = User::factory()->count(3)->asMemberOf($organization)->create();

    Livewire::test(Index::class)
        ->set('selected', $members->map(fn (User $user) => $user->person->getKey())->all())
        ->call('deleteSelected')
        ->assertHasNoErrors();

    expect($organization->members()->whereKey($members->map(fn (User $user) => $user->person->getKey()))->count())->toBe(0);
    expect(Person::query()->whereKey($members->map(fn (User $user) => $user->person->getKey()))->count())->toBe(3);
});
