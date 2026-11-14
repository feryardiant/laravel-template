<?php

declare(strict_types=1);

use App\Actions\Contacts\FindContact;
use App\Actions\Contacts\ListContacts;
use App\Actions\Contacts\SearchContact;
use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('list contacts searches and orders the tenant contacts', function () {
    $organization = Organization::factory()->create();

    $older = contactIndividual($organization, ['name' => 'Older Match']);
    $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();
    contactIndividual($organization, ['name' => 'Newer Match']);
    contactIndividual($organization, ['name' => 'Unrelated']);

    $contacts = app(ListContacts::class)->handle($organization, 'Match');

    expect($contacts->total())->toBe(2);
    expect($contacts->getCollection()
        ->map(fn ($contact): ?string => $contact->stakeholder?->name)
        ->all())->toBe(['Newer Match', 'Older Match']);
});

test('find contact is scoped to the tenant and external relationships', function () {
    $organization = Organization::factory()->create();

    $person = contactIndividual($organization, ['name' => 'Findable']);
    $contact = app(FindContact::class)->byUuid($organization, $person->uuid);

    expect($contact->stakeholder?->is($person))->toBeTrue();

    $foreign = contactIndividual(Organization::factory()->create(), ['name' => 'Foreign']);

    expect(fn () => app(FindContact::class)->byUuid($organization, $foreign->uuid))
        ->toThrow(ModelNotFoundException::class);
});

test('a current tenant contact cannot be resolved as a reusable candidate', function () {
    $current = Organization::factory()->create();

    $person = contactIndividual($current, ['name' => 'Already Contact']);

    expect(fn () => app(FindContact::class)->byReference($current, 'individual:'.$person->uuid))
        ->toThrow(ModelNotFoundException::class);
});

test('an expired current tenant contact resolves as a reusable candidate', function () {
    $current = Organization::factory()->create();

    $person = contactIndividual($current, ['name' => 'Expired Candidate']);
    OrganizationRelationship::query()
        ->whereKey(contactRelationshipId($current, $person))
        ->update(['finish_date' => now()->subDay()]);

    $candidate = app(FindContact::class)->byReference($current, 'individual:'.$person->uuid);

    expect($candidate->is($person))->toBeTrue();
});

test('a scheduled current tenant contact cannot be resolved as a reusable candidate', function () {
    $current = Organization::factory()->create();

    $person = contactIndividual($current, ['name' => 'Scheduled Candidate']);
    OrganizationRelationship::query()
        ->whereKey(contactRelationshipId($current, $person))
        ->update(['start_date' => now()->addWeek(), 'finish_date' => null]);

    expect(fn () => app(FindContact::class)->byReference($current, 'individual:'.$person->uuid))
        ->toThrow(ModelNotFoundException::class);
});

test('a foreign candidate resolves by reference', function () {
    $current = Organization::factory()->create();
    $source = Organization::factory()->create();

    $person = contactIndividual($source, ['name' => 'Reusable']);

    $candidate = app(FindContact::class)->byReference($current, 'individual:'.$person->uuid);

    expect($candidate)->toBeInstanceOf(Person::class);
    expect($candidate->is($person))->toBeTrue();
});

test('the candidate search excludes the current tenant and its contacts', function () {
    $current = Organization::factory()->create();
    $source = Organization::factory()->create();

    contactIndividual($current, ['name' => 'Candidate Own']);
    $candidate = contactIndividual($source, ['name' => 'Candidate External']);

    $results = app(SearchContact::class)->handle($current, 'Candidate');

    expect($results->pluck('name')->all())->toBe(['Candidate External']);
    expect($results->first()?->is($candidate))->toBeTrue();
});

test('the candidate search excludes entities already in the current tenant', function () {
    $current = Organization::factory()->create();
    $source = Organization::factory()->create();

    $person = contactIndividual($source, ['name' => 'Shared Candidate']);
    $current->personCounterparties()->attach($person, ['type' => OrganizationRelationshipType::Customer]);

    $results = app(SearchContact::class)->handle($current, 'Shared Candidate');

    expect($results)->toBeEmpty();
});

test('the candidate search includes the tenant own expired contacts', function () {
    $current = Organization::factory()->create();

    $person = contactIndividual($current, ['name' => 'Expired Candidate']);
    OrganizationRelationship::query()
        ->whereKey(contactRelationshipId($current, $person))
        ->update(['finish_date' => now()->subDay()]);

    $results = app(SearchContact::class)->handle($current, 'Expired Candidate');

    expect($results->pluck('name')->all())->toBe(['Expired Candidate']);
});

test('the candidate search excludes the tenant own scheduled contacts', function () {
    $current = Organization::factory()->create();

    $person = contactIndividual($current, ['name' => 'Scheduled Candidate']);
    OrganizationRelationship::query()
        ->whereKey(contactRelationshipId($current, $person))
        ->update(['start_date' => now()->addWeek(), 'finish_date' => null]);

    expect(app(SearchContact::class)->handle($current, 'Scheduled Candidate'))->toBeEmpty();
});

test('the candidate search is ordered and limited before merging', function () {
    $current = Organization::factory()->create();
    $source = Organization::factory()->create();

    foreach (range(1, 12) as $index) {
        contactIndividual($source, ['name' => \sprintf('Candidate %02d', $index)]);
    }

    $results = app(SearchContact::class)->handle($current, 'Candidate', 5);

    expect($results->pluck('name')->all())->toBe([
        'Candidate 01',
        'Candidate 02',
        'Candidate 03',
        'Candidate 04',
        'Candidate 05',
    ]);
});
