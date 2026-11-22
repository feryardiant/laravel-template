<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Enums\ContactDetailType;
use App\Enums\Gender;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\Contacts\View as ContactView;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Support\CurrentOrganization;
use App\Support\Feature\ContactGender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

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

    OrganizationRelationship::forOrganization($organization)
        ->whereStakeholder($contact)
        ->update(['code' => 'VIEW-1', 'start_date' => '2026-01-01']);

    get(route('contacts.view', ['stakeholder' => $contact]))
        ->assertOk()
        ->assertSee('Acme View')
        ->assertSee('VIEW-1')
        ->assertSee(OrganizationRelationshipType::Supplier->label())
        ->assertSee('View Contact Person')
        ->assertSee('Contact summary');
});

test('the contact view page shows an individual gender', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    ContactGender::activate($organization);

    $person = contactIndividual($organization, [
        'name' => 'Gendered Individual',
        'gender' => Gender::Female,
    ]);

    get(route('contacts.view', ['stakeholder' => $person]))
        ->assertOk()
        ->assertSee(__('Female'));
});

test('the contact view page hides the gender when the feature is off', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $person = contactIndividual($organization, [
        'name' => 'Ungendered Individual',
        'gender' => Gender::Female,
    ]);

    get(route('contacts.view', ['stakeholder' => $person]))
        ->assertOk()
        ->assertDontSee(__('Gender'))
        ->assertDontSee(__('Female'));
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

test('the contact view displays the email and phone', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $contact = contactIndividual($organization, ['name' => 'Detail Individual']);
    $contact->contactDetails()->createMany([
        ['type' => ContactDetailType::Email, 'value' => 'detail@acme.test'],
        ['type' => ContactDetailType::Phone, 'value' => '0812 999'],
    ]);

    get(route('contacts.view', ['stakeholder' => $contact]))
        ->assertOk()
        ->assertSee('detail@acme.test')
        ->assertSee('0812 999');
});

test('a removed member cannot refresh the contact view', function () {
    $organization = Organization::factory()->create();
    $member = actingAsMember($organization, Role::Member);
    $contact = contactIndividual($organization);

    $component = Livewire::test(ContactView::class, ['stakeholder' => $contact->uuid]);

    $organization->members()->detach($member->person->getKey());

    $component->call('$refresh')->assertForbidden();
});

test('a contact from another workspace is not found after switching organizations', function () {
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();

    $user = actingAsMember($first, Role::Member);
    app(CreateMember::class)->attach($second, $user->person, startDate: now(), role: Role::Member);

    $contact = Person::factory()->withoutCredential()->create();

    foreach ([$first, $second] as $organization) {
        $organization->personCounterparties(OrganizationRelationshipType::Customer)->attach($contact, [
            'type' => OrganizationRelationshipType::Customer,
            'start_date' => now(),
        ]);
    }

    $component = Livewire::test(ContactView::class, ['stakeholder' => $contact->uuid]);

    session([CurrentOrganization::SESSION_KEY => $second->getKey()]);

    $component->call('$refresh')->assertNotFound();
});
