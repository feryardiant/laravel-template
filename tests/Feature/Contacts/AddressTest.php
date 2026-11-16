<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\Contacts\Edit;
use App\Models\Address;
use App\Models\Organization;
use App\Models\Person;
use Creasi\Nusa\Models\Regency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('creating an individual contact stores the address', function () {
    $organization = Organization::factory()->create();
    $fields = validAddress();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('name', 'Addressed Person')
        ->set('address', $fields)
        ->call('save')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'Addressed Person')->firstOrFail();
    $address = $person->addresses()->firstOrFail();

    expect($person->addresses()->count())->toBe(1)
        ->and($address->line)->toBe($fields['line'])
        ->and($address->village_code)->toBe($fields['village_code'])
        ->and($address->postal_code)->toBe((int) $fields['postal_code']);
});

test('creating a contact without address fields stores nothing', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('name', 'No Address Person')
        ->call('save')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'No Address Person')->firstOrFail();

    expect($person->addresses()->count())->toBe(0);
});

test('an address line is required once another address field is filled', function () {
    $organization = Organization::factory()->create();
    $fields = validAddress();

    actingAsMember($organization, Role::Admin);

    $component = Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('name', 'Partial Address')
        ->set('address.postal_code', $fields['postal_code'])
        ->call('save')
        ->assertHasErrors('address.line');

    // The child component keeps its own error bag, so the parent renders the message.
    $component->assertSee($component->errors()->first('address.line'));
});

test('every rejected address field shows its message', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $component = Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('name', 'Bad Postal')
        ->set('address', validAddress(['postal_code' => '123']))
        ->call('save')
        ->assertHasErrors('address.postal_code');

    $component->assertSee($component->errors()->first('address.postal_code'));
});

test('a regency outside the selected province is rejected', function () {
    $organization = Organization::factory()->create();
    $fields = validAddress();
    $otherRegency = Regency::query()
        ->where('province_code', '!=', $fields['province_code'])
        ->value('code');

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('name', 'Mismatched Region')
        ->set('address', validAddress(['regency_code' => $otherRegency]))
        ->call('save')
        ->assertHasErrors('address.regency_code');
});

test('editing a contact updates its address in place', function () {
    $organization = Organization::factory()->create();
    $person = contactIndividual($organization, ['name' => 'Address Owner']);
    $person->addresses()->save(Address::factory()->make(['line' => 'Old line']));

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openEdit', contactRelationshipId($organization, $person))
        ->assertSet('address.line', 'Old line')
        ->set('address.line', 'New line')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()->addresses()->count())->toBe(1)
        ->and($person->fresh()->addresses()->value('line'))->toBe('New line');
});

test('clearing every address field removes the address', function () {
    $organization = Organization::factory()->create();
    $person = contactIndividual($organization, ['name' => 'Cleared Address']);
    $person->addresses()->save(Address::factory()->make());

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openEdit', contactRelationshipId($organization, $person))
        ->set('address', array_fill_keys(array_keys(validAddress()), ''))
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()->addresses()->count())->toBe(0);
});

test('a profile-locked contact keeps its address', function () {
    $owner = Organization::factory()->create();
    $other = Organization::factory()->create();
    $person = contactIndividual($owner, ['name' => 'Shared Address']);
    $person->addresses()->save(Address::factory()->make(['line' => 'Owner line']));

    $other->personCounterparties()->attach($person, [
        'type' => OrganizationRelationshipType::Customer,
        'start_date' => now(),
    ]);

    actingAsMember($other, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openEdit', contactRelationshipId($other, $person))
        ->set('address.line', 'Tampered line')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()->addresses()->count())->toBe(1)
        ->and($person->fresh()->addresses()->value('line'))->toBe('Owner line');
});
