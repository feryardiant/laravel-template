<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

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
    $person = contactIndividual($source, ['name' => 'Lookup Person', 'summary' => null]);
    $contact = contactOrganization($source, ['name' => 'Lookup Org', 'summary' => 'Vendor of record']);

    $response = getJson(route('contacts.lookup', ['q' => 'Lookup']))
        ->assertOk()
        ->assertJsonCount(2);

    $payload = $response->json();

    expect($payload)->toContain([
        'label' => 'Lookup Org — Vendor of record',
        'value' => 'organization:'.$contact->uuid,
    ])->toContain([
        'label' => 'Lookup Person — #'.Str::substr($person->uuid, 0, 8),
        'value' => 'individual:'.$person->uuid,
    ]);
});

test('the lookup disambiguates candidates that share a name', function () {
    $current = Organization::factory()->create();

    actingAsMember($current, Role::Admin);

    $source = Organization::factory()->create();

    contactOrganization($source, ['name' => 'Same Name', 'summary' => 'First summary']);
    contactOrganization($source, ['name' => 'Same Name', 'summary' => 'Second summary']);

    $labels = array_column(
        getJson(route('contacts.lookup', ['q' => 'Same Name']))->assertOk()->assertJsonCount(2)->json(),
        'label',
    );

    expect($labels)->toContain('Same Name — First summary')
        ->toContain('Same Name — Second summary');
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
