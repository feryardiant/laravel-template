<?php

declare(strict_types=1);

use App\Actions\Contacts\CreateContact;
use App\Enums\Gender;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\Contacts\Edit;
use App\Livewire\Contacts\Index;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('the index opens the create flyout', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Index::class)
        ->call('createContact')
        ->assertDispatched('create-contact');
});

test('typing a new name keeps the selected organization type', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('type', 'organization')
        ->set('selectedEntity', '')
        ->assertSet('type', 'organization');
});

test('selecting a candidate locks shared fields and typing unlocks them', function () {
    $source = Organization::factory()->create();
    $target = Organization::factory()->create();

    $person = contactIndividual($source, ['name' => 'Locked Candidate', 'summary' => 'Shared summary']);

    actingAsMember($target, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('selectedEntity', 'individual:'.$person->uuid)
        ->assertSet('name', 'Locked Candidate')
        ->assertSet('summary', 'Shared summary')
        ->set('name', 'New Name')
        ->assertSet('selectedEntity', null)
        ->assertSet('summary', '');
});

test('an invalid candidate reference is discarded', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('selectedEntity', 'individual:not-a-real-uuid')
        ->assertSet('selectedEntity', null);
});

test('an admin can create an individual contact', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->assertSet('type', 'individual')
        ->assertSet('role', OrganizationRelationshipType::Customer->value)
        ->assertSet('start_date', now()->format('Y-m-d'))
        ->set('name', 'Jane Doe')
        ->set('summary', 'An individual counterparty')
        ->set('gender', Gender::Male->value)
        ->set('code', 'C-001')
        ->call('save')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'Jane Doe')->firstOrFail();
    $relationship = OrganizationRelationship::query()
        ->where('stakeholder_type', Person::class)
        ->where('stakeholder_id', $person->getKey())
        ->firstOrFail();

    expect($person->user_id)->toBeNull()
        ->and($person->gender)->toBe(Gender::Male)
        ->and($relationship->type)->toBe(OrganizationRelationshipType::Customer)
        ->and($relationship->code)->toBe('C-001')
        ->and($relationship->start_date?->toDateString())->toBe(now()->toDateString());
});

test('an admin can create an organization contact with a contact person', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('type', 'organization')
        ->set('name', 'Acme Corp')
        ->set('summary', 'A company')
        ->set('contact_name', 'Nara Hubung')
        ->set('contact_gender', Gender::Female->value)
        ->set('contact_summary', 'Primary contact')
        ->call('save')
        ->assertHasNoErrors();

    $contact = Organization::query()->where('name', 'Acme Corp')->firstOrFail();
    $contactPerson = Person::query()->where('name', 'Nara Hubung')->firstOrFail();

    expect($contact->contact_id)->toBe($contactPerson->getKey())
        ->and($contactPerson->gender)->toBe(Gender::Female)
        ->and($contactPerson->summary)->toBe('Primary contact')
        ->and(OrganizationRelationship::query()
            ->where('stakeholder_type', Organization::class)
            ->where('stakeholder_id', $contact->getKey())
            ->exists())->toBeTrue();
});

test('contact creation validates its required fields', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('name', '')
        ->set('type', '')
        ->set('start_date', '')
        ->call('save')
        ->assertHasErrors(['name', 'type', 'start_date']);
});

test('a finish date before the start date is rejected', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('name', 'Invalid Date')
        ->set('gender', Gender::Male->value)
        ->set('start_date', '2026-05-10')
        ->set('finish_date', '2026-05-01')
        ->call('save')
        ->assertHasErrors('finish_date');
});

test('an individual contact requires a gender on create', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('name', 'No Gender')
        ->call('save')
        ->assertHasErrors('gender');
});

test('an organization contact requires contact person details', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('type', 'organization')
        ->set('name', 'No Contact Person')
        ->call('save')
        ->assertHasErrors(['contact_name', 'contact_gender']);
});

test('an invalid role is rejected', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('name', 'Bad Role')
        ->set('gender', Gender::Male->value)
        ->set('role', OrganizationRelationshipType::Member->value)
        ->call('save')
        ->assertHasErrors('role');
});

test('a contact code is unique per tenant', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactIndividual($organization, ['name' => 'Existing']);

    OrganizationRelationship::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_type', Person::class)
        ->where('stakeholder_id', $contact->getKey())
        ->update(['code' => 'DUP-1']);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('name', 'Duplicate Code')
        ->set('gender', Gender::Male->value)
        ->set('code', 'DUP-1')
        ->call('save')
        ->assertHasErrors('code');
});

test('a contact photo is stored', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('name', 'Photo Contact')
        ->set('gender', Gender::Male->value)
        ->set('photo', UploadedFile::fake()->image('photo.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'Photo Contact')->firstOrFail();
    $path = $person->getRawOriginal('photo_url');

    expect($path)->toBeString();
    Storage::disk()->assertExists($path);
});

test('an admin can attach an existing contact from another tenant', function () {
    $source = Organization::factory()->create();
    $target = Organization::factory()->create();

    $person = contactIndividual($source, ['name' => 'Shared Person', 'summary' => 'Shared summary']);
    $relationship = OrganizationRelationship::query()
        ->where('stakeholder_id', $person->getKey())
        ->value('id');

    actingAsMember($target, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('selectedEntity', 'individual:'.$person->uuid)
        ->assertSet('name', 'Shared Person')
        ->assertSet('type', 'individual')
        ->set('code', 'T-1')
        ->call('save')
        ->assertHasNoErrors();

    $attached = OrganizationRelationship::query()
        ->where('stakeholder_id', $person->getKey())
        ->where('stakeholder_type', Person::class)
        ->where('organization_id', $target->getKey())
        ->firstOrFail();

    expect($attached->code)->toBe('T-1')
        ->and(Person::query()->where('name', 'Shared Person')->count())->toBe(1)
        ->and($relationship)->not->toBeNull();
});

test('a failed create removes the uploaded photo', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $existing = contactIndividual($organization, ['name' => 'Existing Code']);

    OrganizationRelationship::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_type', $existing->getMorphClass())
        ->where('stakeholder_id', $existing->getKey())
        ->update(['code' => 'DUP-CREATE']);

    $data = [
        'name' => 'Rollback Create',
        'summary' => null,
        'code' => 'DUP-CREATE',
        'role' => OrganizationRelationshipType::Customer,
        'type' => 'individual',
        'start_date' => now()->toDateString(),
        'finish_date' => null,
        'contact_name' => null,
        'contact_gender' => null,
        'contact_summary' => null,
    ];

    expect(fn () => app(CreateContact::class)->handle(
        $organization,
        $data,
        UploadedFile::fake()->image('rollback.jpg'),
    ))->toThrow(QueryException::class);

    expect(Storage::disk()->allFiles('photos'))->toBe([]);
});

test('a contact already in the tenant list cannot be selected for reuse', function () {
    $source = Organization::factory()->create();
    $target = Organization::factory()->create();

    $person = contactIndividual($source, ['name' => 'Already Used']);

    contactIndividual($target, ['name' => 'Existing']);

    $target->personCounterparties()->attach($person, ['type' => OrganizationRelationshipType::Customer]);

    actingAsMember($target, Role::Admin);

    Livewire::test(Edit::class)
        ->call('openCreate')
        ->set('selectedEntity', 'individual:'.$person->uuid)
        ->assertSet('selectedEntity', null);
});
