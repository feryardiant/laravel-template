<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\Contacts\Index;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('guests are redirected from the contacts page', function () {
    get(route('contacts.index'))->assertRedirect(route('login'));
});

test('the contacts page lists only the current organization external contacts', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    contactOrganization($organization, ['name' => 'Inside Org']);
    contactIndividual($organization, ['name' => 'Inside Person']);
    contactOrganization(Organization::factory()->create(), ['name' => 'Outside Org']);
    User::factory()->asMemberOf($organization)->create(['name' => 'Inside Member']);

    $subsidiary = Organization::factory()->create(['name' => 'Inside Subsidiary']);
    $organization->subsidiaries()->attach($subsidiary, ['type' => OrganizationRelationshipType::Subsidiary]);

    get(route('contacts.index'))
        ->assertOk()
        ->assertSee('Inside Org')
        ->assertSee('Inside Person')
        ->assertDontSee('Outside Org')
        ->assertDontSee('Inside Member')
        ->assertDontSee('Inside Subsidiary');
});

test('a contact whose stakeholder is soft-deleted is not listed', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $contact = contactIndividual($organization, ['name' => 'Deleted Contact']);
    $contact->delete();

    Livewire::test(Index::class)
        ->assertDontSee('Deleted Contact');
});

test('organization rows show their contact person and individuals show a dash', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $contactPerson = Person::factory()->withoutCredential()->create(['name' => 'Nara Hubung']);
    $contact = Organization::factory()->create([
        'contact_id' => $contactPerson->getKey(),
        'name' => 'Acme Org',
    ]);
    $organization->organizationCounterparties()->attach($contact, ['type' => OrganizationRelationshipType::Customer]);

    contactIndividual($organization, ['name' => 'Solo Person']);

    Livewire::test(Index::class)
        ->assertSee('Nara Hubung')
        ->assertSee('Acme Org')
        ->assertSee('Solo Person');
});

test('the contacts table shows the tenant code', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $contact = contactIndividual($organization, ['name' => 'Coded Contact']);
    $relationshipId = contactRelationshipId($organization, $contact);

    OrganizationRelationship::query()->whereKey($relationshipId)->update(['code' => 'CT-42']);

    Livewire::test(Index::class)
        ->assertSee('Coded Contact')
        ->assertSee('CT-42');
});

test('contacts are paginated', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    Person::factory()->count(11)->withoutCredential()->create();

    foreach (Person::query()->latest('id')->take(11)->get() as $person) {
        $organization->personCounterparties()->attach($person, ['type' => OrganizationRelationshipType::Customer]);
    }

    Livewire::test(Index::class)
        ->assertViewHas('contacts', fn ($contacts) => $contacts->count() === 10 && $contacts->total() === 11)
        ->call('gotoPage', 2)
        ->assertViewHas('contacts', fn ($contacts) => $contacts->count() === 1);
});

test('contacts are ordered by the stakeholder created_at date descending', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $older = contactIndividual($organization, ['name' => 'Older Contact']);
    $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();

    contactIndividual($organization, ['name' => 'Newer Contact']);

    Livewire::test(Index::class)
        ->assertViewHas('contacts', function ($contacts): bool {
            $names = $contacts->getCollection()
                ->map(fn (OrganizationRelationship $contact): ?string => $contact->stakeholder?->name)
                ->all();

            return $names === ['Newer Contact', 'Older Contact'];
        });
});

test('the contacts search matches contact and contact person names', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $contactPerson = Person::factory()->withoutCredential()->create(['name' => 'Unique Nara']);
    $acme = Organization::factory()->create([
        'contact_id' => $contactPerson->getKey(),
        'name' => 'Acme Corp',
    ]);
    $organization->organizationCounterparties()->attach($acme, ['type' => OrganizationRelationshipType::Customer]);
    contactIndividual($organization, ['name' => 'Beta Person']);

    Livewire::test(Index::class)
        ->set('search', 'acme')
        ->assertSee('Acme Corp')
        ->assertDontSee('Beta Person')
        ->set('search', 'Beta')
        ->assertSee('Beta Person')
        ->assertDontSee('Acme Corp')
        ->set('search', 'Unique Nara')
        ->assertSee('Acme Corp')
        ->assertDontSee('Beta Person')
        ->set('search', '')
        ->assertSee('Acme Corp')
        ->assertSee('Beta Person');
});

test('the contacts search stays within the current tenant', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    contactIndividual($organization, ['name' => 'Tenant Contact']);
    contactIndividual(Organization::factory()->create(), ['name' => 'Foreign Contact']);

    Livewire::test(Index::class)
        ->set('search', 'Contact')
        ->assertSee('Tenant Contact')
        ->assertDontSee('Foreign Contact');
});

test('changing the search resets the page and selection', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    contactIndividual($organization, ['name' => 'One']);
    contactIndividual($organization, ['name' => 'Two']);

    Livewire::test(Index::class)
        ->set('selected', [1])
        ->set('search', 'One')
        ->assertSet('selected', [])
        ->assertSet('allSelected', false)
        ->assertSet('someSelected', false);
});

test('a plain member cannot create, update or delete contacts', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $contact = contactIndividual($organization, ['name' => 'Protected Contact']);
    $contactId = OrganizationRelationship::query()
        ->forOrganization($organization)
        ->external()
        ->whereStakeholder($contact)
        ->value('id');

    Livewire::test(Index::class)
        ->call('createContact')
        ->assertForbidden();

    Livewire::test(Index::class)
        ->call('confirmDelete', $contactId, $contact->name)
        ->assertSet('deletingContactId', $contactId)
        ->call('deleteConfirmed')
        ->assertForbidden();

    Livewire::test(Index::class)
        ->call('deleteSelectedConfirmed')
        ->assertForbidden();

    Livewire::test(Index::class)
        ->assertDontSee(__('Select all contacts'));

    expect(OrganizationRelationship::whereStakeholder($contact)->exists())->toBeTrue();
});

test('a plain member can open a contact view page', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $contact = contactIndividual($organization, ['name' => 'Readable Contact']);

    get(route('contacts.view', ['stakeholder' => $contact]))
        ->assertOk()
        ->assertSee('Readable Contact');
});

test('a user without an organization sees the empty state', function () {
    actingAs(User::factory()->create());

    get(route('contacts.index'))
        ->assertOk()
        ->assertSee(__('You are not a member of any organization.'));
});

test('refreshing after an edit clears a stale selection', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $contact = contactIndividual($organization, ['name' => 'Selected Contact']);
    $relationshipId = contactRelationshipId($organization, $contact);

    $component = Livewire::test(Index::class)
        ->set('search', 'Selected')
        ->set('selected', [$relationshipId]);

    $contact->update(['name' => 'Renamed Contact']);

    $component
        ->call('refreshContacts')
        ->assertSet('selected', [])
        ->assertSet('allSelected', false)
        ->assertSet('someSelected', false);
});

test('select all toggles the current page rows', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    contactIndividual($organization, ['name' => 'First']);
    contactIndividual($organization, ['name' => 'Second']);
    $ids = OrganizationRelationship::query()
        ->forOrganization($organization)
        ->external()
        ->pluck('id')
        ->all();

    Livewire::test(Index::class)
        ->call('toggleSelectAll')
        ->assertSet('allSelected', true)
        ->assertSet('someSelected', false)
        ->assertSet('selected', fn ($selected): bool => collect($selected)->sort()->values()->all() === collect($ids)->sort()->values()->all())
        ->call('toggleSelectAll')
        ->assertSet('selected', [])
        ->assertSet('allSelected', false);
});
