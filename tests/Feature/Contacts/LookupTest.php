<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Organization;

use function Pest\Laravel\getJson;

test('guests cannot use the contact lookup', function () {
    getJson(route('contacts.lookup', ['q' => 'acme']))->assertUnauthorized();
});

test('members cannot use the contact lookup', function () {
    $current = Organization::factory()->create();

    actingAsMember($current, Role::Member);

    getJson(route('contacts.lookup', ['q' => 'acme']))->assertForbidden();
});

test('the lookup returns contacts registered by other tenants', function () {
    $current = Organization::factory()->create();

    actingAsMember($current, Role::Admin);

    $source = Organization::factory()->create();
    $person = contactIndividual($source, ['name' => 'Lookup Person']);
    $contact = contactOrganization($source, ['name' => 'Lookup Org']);

    $response = getJson(route('contacts.lookup', ['q' => 'Lookup']))
        ->assertOk()
        ->assertJsonCount(2);

    $payload = $response->json();

    expect($payload)->toContain([
        'label' => 'Lookup Org',
        'value' => 'organization:'.$contact->uuid,
    ])->toContain([
        'label' => 'Lookup Person',
        'value' => 'individual:'.$person->uuid,
    ]);
});

test('the lookup excludes the current tenant its contacts and the tenant itself', function () {
    $current = Organization::factory()->create(['name' => 'Lookup Current']);

    actingAsMember($current, Role::Admin);

    contactIndividual($current, ['name' => 'Lookup Own']);

    $payload = getJson(route('contacts.lookup', ['q' => 'Lookup']))->assertOk()->json();

    expect($payload)->toBe([]);
});

test('the lookup requires a search term', function () {
    $current = Organization::factory()->create();

    actingAsMember($current, Role::Admin);

    getJson(route('contacts.lookup'))->assertOk()->assertJson([]);
});
