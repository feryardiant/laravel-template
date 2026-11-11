<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;

use function Pest\Laravel\get;

test('the contact view page renders the contact information', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $contactPerson = Person::factory()->withoutCredential()->create([
        'name' => 'View Contact Person',
        'gender' => 'F',
        'summary' => 'Person summary',
    ]);
    $contact = Organization::factory()->create([
        'contact_id' => $contactPerson->getKey(),
        'name' => 'Acme View',
        'summary' => 'Contact summary',
    ]);
    $organization->organizationCounterparties()->attach($contact, ['type' => OrganizationRelationshipType::Supplier]);

    OrganizationRelationship::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_type', $contact->getMorphClass())
        ->where('stakeholder_id', $contact->getKey())
        ->update(['code' => 'VIEW-1', 'start_date' => '2026-01-01']);

    get(route('contacts.view', ['stakeholder' => $contact]))
        ->assertOk()
        ->assertSee('Acme View')
        ->assertSee('VIEW-1')
        ->assertSee(OrganizationRelationshipType::Supplier->label())
        ->assertSee('View Contact Person')
        ->assertSee('Contact summary');
});

test('a contact from another organization is not found', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $foreign = contactIndividual(Organization::factory()->create(), ['name' => 'Foreign Contact']);

    get(route('contacts.view', ['stakeholder' => $foreign]))->assertNotFound();
});

test('a non-external relationship is not found', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = Person::factory()->withoutCredential()->create(['name' => 'Member Person']);
    $organization->members()->attach($member, [
        'type' => OrganizationRelationshipType::Member,
        'start_date' => now(),
    ]);

    get(route('contacts.view', ['stakeholder' => $member]))->assertNotFound();
});

test('a contact whose stakeholder is soft-deleted is not found', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $contact = contactIndividual($organization, ['name' => 'Deleted View']);
    $contact->delete();

    get(route('contacts.view', ['stakeholder' => $contact]))->assertNotFound();
});
