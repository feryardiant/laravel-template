<?php

declare(strict_types=1);

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\DeleteContact;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('deleting an individual contact detaches it and soft-deletes the owned person', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $person = contactIndividual($organization, ['name' => 'Delete Me']);
    $relationshipId = contactRelationshipId($organization, $person);

    app(DeleteContact::class)->handle($organization, $relationshipId);

    expect(OrganizationRelationship::query()->find($relationshipId))->toBeNull();
    expect(Person::withTrashed()->find($person->getKey())?->trashed())->toBeTrue();
});

test('deleting a shared contact only detaches the relationship', function () {
    $owner = Organization::factory()->create();
    $other = Organization::factory()->create();

    actingAsMember($owner, Role::Admin);

    $person = contactIndividual($owner, ['name' => 'Shared Delete']);
    $other->personCounterparties()->attach($person, ['type' => OrganizationRelationshipType::Customer]);

    $relationshipId = contactRelationshipId($owner, $person);

    app(DeleteContact::class)->handle($owner, $relationshipId);

    expect(OrganizationRelationship::query()->find($relationshipId))->toBeNull();
    expect(Person::withTrashed()->find($person->getKey())?->trashed())->toBeFalse();
    expect(OrganizationRelationship::forOrganization($other)
        ->whereStakeholder($person)
        ->exists())->toBeTrue();
});

test('deleting an organization contact removes the organization and its contact person together', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Delete Org']);
    $contactPerson = $contact->primaryContact;
    $relationshipId = contactRelationshipId($organization, $contact);

    app(DeleteContact::class)->handle($organization, $relationshipId);

    expect(OrganizationRelationship::query()->find($relationshipId))->toBeNull();
    expect(Organization::withTrashed()->find($contact->getKey())?->trashed())->toBeTrue();
    expect(Person::withTrashed()->find($contactPerson?->getKey())?->trashed())->toBeTrue();
});

test('deleting a tenant organization contact keeps the organization', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Tenant Counterparty']);
    $contact->update(['is_tenant' => true]);

    $relationshipId = contactRelationshipId($organization, $contact);

    app(DeleteContact::class)->handle($organization, $relationshipId);

    expect(OrganizationRelationship::query()->find($relationshipId))->toBeNull();
    expect(Organization::withTrashed()->find($contact->getKey())?->trashed())->toBeFalse();
});

test('deleting a contact removes its stored photo', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $person = contactIndividual($organization, ['name' => 'Photo Delete']);
    $person->update(['photo_url' => 'photos/delete.jpg']);
    Storage::disk()->put('photos/delete.jpg', 'photo');

    app(DeleteContact::class)->handle($organization, contactRelationshipId($organization, $person));

    /** @disregard */
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

    expect(app(DeleteContact::class)->handleBulk($organization, [...$ids, ...$ids]))->toBe(2);
    expect(OrganizationRelationship::query()->whereKey($ids)->exists())->toBeFalse();
});

test('deleting one imported duplicate keeps the other contact and the entity', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $person = contactIndividual($organization, ['name' => 'Duplicate Import']);
    $organization->personCounterparties(OrganizationRelationshipType::Supplier)->attach($person, [
        'type' => OrganizationRelationshipType::Supplier,
    ]);

    $customerRelationshipId = (int) OrganizationRelationship::forOrganization($organization)
        ->whereStakeholder($person, OrganizationRelationshipType::Customer)
        ->value('id');

    app(DeleteContact::class)->handle($organization, $customerRelationshipId);

    expect(Person::withTrashed()->find($person->getKey())?->trashed())->toBeFalse();
    expect(OrganizationRelationship::forOrganization($organization)
        ->whereStakeholder($person, OrganizationRelationshipType::Supplier)
        ->exists())->toBeTrue();
});

test('deleting an individual contact keeps a user-linked person', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $person = contactIndividual($organization, ['name' => 'User Linked']);
    $person->update(['user_id' => User::factory()->create()->getKey()]);

    app(DeleteContact::class)->handle($organization, contactRelationshipId($organization, $person));

    expect(Person::withTrashed()->find($person->getKey())?->trashed())->toBeFalse();
});

test('deleting an individual contact keeps a person the tenant uses as its primary contact', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $person = contactIndividual($organization, ['name' => 'Tenant Primary Contact']);
    $organization->update(['contact_id' => $person->getKey()]);

    app(DeleteContact::class)->handle($organization, contactRelationshipId($organization, $person));

    expect(Person::withTrashed()->find($person->getKey())?->trashed())->toBeFalse();
    expect($organization->fresh()?->contact_id)->toBe($person->getKey());
});

test('deleting an organization contact keeps it when its contact person is used elsewhere', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Referenced Person Org']);
    $contactPerson = $contact->primaryContact;

    Organization::factory()->create(['contact_id' => $contactPerson?->getKey()]);

    app(DeleteContact::class)->handle($organization, contactRelationshipId($organization, $contact));

    expect(Organization::withTrashed()->find($contact->getKey())?->trashed())->toBeFalse();
    expect(Person::withTrashed()->find($contactPerson?->getKey())?->trashed())->toBeFalse();
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

    expect(fn () => app(DeleteContact::class)->handleBulk($organization, [$relationshipId, 999999]))
        ->toThrow(ModelNotFoundException::class);

    expect(OrganizationRelationship::query()->find($relationshipId))->not->toBeNull();
});

test('deleting an organization contact created from the contact form removes the organization', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = app(CreateContact::class)->handle($organization, [
        'type' => 'organization',
        'name' => 'Form Created Org',
        'contact_name' => 'Form Contact',
        'role' => OrganizationRelationshipType::Customer,
    ]);

    app(DeleteContact::class)->handle($organization, contactRelationshipId($organization, $contact));

    expect(Organization::withTrashed()->find($contact->getKey())?->trashed())->toBeTrue();
});
