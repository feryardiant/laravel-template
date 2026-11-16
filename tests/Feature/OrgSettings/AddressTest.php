<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\OrgSettings\General;
use App\Models\Address;
use App\Models\Organization;
use Creasi\Nusa\Models\Regency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('saving the organization stores its address', function () {
    $organization = Organization::factory()->create();
    $fields = validAddress();

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->set('address', $fields)
        ->call('update')
        ->assertHasNoErrors();

    $address = $organization->fresh()->address()->firstOrFail();

    expect($address->line)->toBe($fields['line'])
        ->and($address->village_code)->toBe($fields['village_code'])
        ->and($address->postal_code)->toBe((int) $fields['postal_code']);
});

test('saving again replaces the single organization address', function () {
    $organization = Organization::factory()->create();
    $organization->address()->save(Address::factory()->make(['line' => 'Old line']));

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->assertSet('address.line', 'Old line')
        ->set('address.line', 'New line')
        ->call('update')
        ->assertHasNoErrors();

    $addresses = $organization->fresh()->address()->get();

    expect($addresses)->toHaveCount(1)
        ->and($addresses->first()->line)->toBe('New line');
});

test('clearing every organization address field removes the address', function () {
    $organization = Organization::factory()->create();
    $organization->address()->save(Address::factory()->make());

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->set('address', array_fill_keys(array_keys(validAddress()), ''))
        ->call('update')
        ->assertHasNoErrors();

    expect($organization->fresh()->address()->count())->toBe(0);
});

test('a regency outside the selected province is rejected', function () {
    $organization = Organization::factory()->create();
    $fields = validAddress();
    $otherRegency = Regency::query()
        ->where('province_code', '!=', $fields['province_code'])
        ->value('code');

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->set('address', validAddress(['regency_code' => $otherRegency]))
        ->call('update')
        ->assertHasErrors('address.regency_code');

    expect($organization->fresh()->address()->count())->toBe(0);
});
