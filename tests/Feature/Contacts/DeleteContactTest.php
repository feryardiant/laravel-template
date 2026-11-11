<?php

declare(strict_types=1);

use App\Actions\Contacts\DeleteContact;
use App\Actions\Contacts\DeleteContacts;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

test('deleting an individual contact detaches it and soft-deletes the owned person', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $person = contactIndividual($organization, ['name' => 'Delete Me']);
    $relationshipId = contactRelationshipId($organization, $person);

    app(DeleteContact::class)->handle($organization, $relationshipId);

    expect(OrganizationRelationship::query()->find($relationshipId))->toBeNull()
        ->and(Person::withTrashed()->find($person->getKey())?->trashed())->toBeTrue();
});

test('deleting a shared contact only detaches the relationship', function () {
    $owner = Organization::factory()->create();
    $other = Organization::factory()->create();

    actingAsMember($owner, Role::Admin);

    $person = contactIndividual($owner, ['name' => 'Shared Delete']);
    $other->personCounterparties()->attach($person, ['type' => OrganizationRelationshipType::Customer]);

    $relationshipId = contactRelationshipId($owner, $person);

    app(DeleteContact::class)->handle($owner, $relationshipId);

    expect(OrganizationRelationship::query()->find($relationshipId))->toBeNull()
        ->and(Person::withTrashed()->find($person->getKey())?->trashed())->toBeFalse()
        ->and(OrganizationRelationship::query()
            ->where('organization_id', $other->getKey())
            ->where('stakeholder_id', $person->getKey())
            ->exists())->toBeTrue();
});

test('deleting an organization contact removes the organization and its contact person together', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Delete Org']);
    $contactPerson = $contact->primaryContact;
    $relationshipId = contactRelationshipId($organization, $contact);

    app(DeleteContact::class)->handle($organization, $relationshipId);

    expect(OrganizationRelationship::query()->find($relationshipId))->toBeNull()
        ->and(Organization::withTrashed()->find($contact->getKey())?->trashed())->toBeTrue()
        ->and(Person::withTrashed()->find($contactPerson?->getKey())?->trashed())->toBeTrue();
});

test('deleting a tenant organization contact keeps the organization', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Tenant Counterparty']);
    $contact->update(['is_tenant' => true]);

    $relationshipId = contactRelationshipId($organization, $contact);

    app(DeleteContact::class)->handle($organization, $relationshipId);

    expect(OrganizationRelationship::query()->find($relationshipId))->toBeNull()
        ->and(Organization::withTrashed()->find($contact->getKey())?->trashed())->toBeFalse();
});

test('deleting a contact removes its stored photo', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $person = contactIndividual($organization, ['name' => 'Photo Delete']);
    $person->update(['photo_url' => 'photos/delete.jpg']);
    Storage::disk()->put('photos/delete.jpg', 'photo');

    app(DeleteContact::class)->handle($organization, contactRelationshipId($organization, $person));

    Storage::disk()->assertMissing('photos/delete.jpg');
});

test('bulk deletion deduplicates the selected ids', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $first = contactIndividual($organization, ['name' => 'Bulk One']);
    $second = contactIndividual($organization, ['name' => 'Bulk Two']);

    $ids = [
        contactRelationshipId($organization, $first),
        contactRelationshipId($organization, $second),
    ];

    expect(app(DeleteContacts::class)->handle($organization, [...$ids, ...$ids]))->toBe(2)
        ->and(OrganizationRelationship::query()->whereKey($ids)->exists())->toBeFalse();
});

test('deleting an organization contact keeps it when its contact person is used elsewhere', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Referenced Person Org']);
    $contactPerson = $contact->primaryContact;

    Organization::factory()->create(['contact_id' => $contactPerson?->getKey()]);

    app(DeleteContact::class)->handle($organization, contactRelationshipId($organization, $contact));

    expect(Organization::withTrashed()->find($contact->getKey())?->trashed())->toBeFalse()
        ->and(Person::withTrashed()->find($contactPerson?->getKey())?->trashed())->toBeFalse();
});

test('deleting an organization contact keeps it when the organization owns relationships', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Relationship Owner']);
    $contact->members()->attach(Person::factory()->withoutCredential()->create(), [
        'type' => OrganizationRelationshipType::Member,
        'start_date' => now(),
    ]);

    app(DeleteContact::class)->handle($organization, contactRelationshipId($organization, $contact));

    expect(Organization::withTrashed()->find($contact->getKey())?->trashed())->toBeFalse();
});

test('bulk deletion rolls back when one contact cannot be resolved', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactIndividual($organization, ['name' => 'Rollback Contact']);
    $relationshipId = contactRelationshipId($organization, $contact);

    expect(fn () => app(DeleteContacts::class)->handle($organization, [$relationshipId, 999999]))
        ->toThrow(ModelNotFoundException::class);

    expect(OrganizationRelationship::query()->find($relationshipId))->not->toBeNull();
});
