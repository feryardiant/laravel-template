<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Enums\Role;
use App\Livewire\Members\Edit;
use App\Models\Address;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('adding a member stores their address', function () {
    $organization = Organization::factory()->create();
    $fields = validAddress();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('name', 'Addressed Member')
        ->set('role', Role::Member->value)
        ->set('address', $fields)
        ->call('save')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'Addressed Member')->firstOrFail();
    $address = $person->addresses()->firstOrFail();

    expect($person->addresses()->count())->toBe(1)
        ->and($address->line)->toBe($fields['line'])
        ->and($address->village_code)->toBe($fields['village_code'])
        ->and($address->postal_code)->toBe((int) $fields['postal_code']);
});

test('editing a member updates their address in place', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();
    $member->person->addresses()->save(Address::factory()->make(['line' => 'Old line']));

    Livewire::test(Edit::class, ['person' => $member->person])
        ->assertSet('address.line', 'Old line')
        ->set('address.line', 'New line')
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->fresh()->addresses()->count())->toBe(1)
        ->and($member->person->fresh()->addresses()->value('line'))->toBe('New line');
});

test('clearing every address field removes the member address', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();
    $member->person->addresses()->save(Address::factory()->make());

    Livewire::test(Edit::class, ['person' => $member->person])
        ->set('address', array_fill_keys(array_keys(validAddress()), ''))
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->fresh()->addresses()->count())->toBe(0);
});

test('a self-owned member address is left untouched by managers', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->withProfile()->create();
    $member->person->addresses()->save(Address::factory()->make(['line' => 'Owner line']));

    app(CreateMember::class)
        ->attach($organization, $member->person, startDate: now(), role: Role::Member);

    Livewire::test(Edit::class, ['person' => $member->person])
        ->set('address.line', 'Tampered line')
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->fresh()->addresses()->count())->toBe(1)
        ->and($member->person->fresh()->addresses()->value('line'))->toBe('Owner line');
});
