<?php

declare(strict_types=1);

use App\Actions\Contacts\UpdateContact;
use App\Enums\Gender;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\Contacts\Edit;
use App\Livewire\Contacts\Index;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('editing a contact updates its per-tenant and shared fields', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Acme Corp']);
    $contactPerson = $contact->primaryContact;
    $relationshipId = contactRelationshipId($organization, $contact);

    Livewire::test(Edit::class)
        ->call('openEdit', $relationshipId)
        ->assertSet('name', 'Acme Corp')
        ->assertSet('type', 'organization')
        ->set('name', 'Acme Updated')
        ->set('summary', 'Updated summary')
        ->set('code', 'UPDATED-1')
        ->set('role', OrganizationRelationshipType::Supplier->value)
        ->set('start_date', '2026-01-01')
        ->set('finish_date', '2026-12-31')
        ->set('contact_name', 'Nara Updated')
        ->set('contact_gender', Gender::Male->value)
        ->set('contact_summary', 'Updated contact person')
        ->call('save')
        ->assertHasNoErrors();

    $contact->refresh();
    $contactPerson?->refresh();
    $relationship = OrganizationRelationship::query()->findOrFail($relationshipId);

    expect($contact->name)->toBe('Acme Updated')
        ->and($contact->summary)->toBe('Updated summary')
        ->and($contactPerson?->name)->toBe('Nara Updated')
        ->and($contactPerson?->gender)->toBe(Gender::Male)
        ->and($relationship->code)->toBe('UPDATED-1')
        ->and($relationship->type)->toBe(OrganizationRelationshipType::Supplier)
        ->and($relationship->start_date?->toDateString())->toBe('2026-01-01')
        ->and($relationship->finish_date?->toDateString())->toBe('2026-12-31');
});

test('editing keeps the contact type even when the client changes it', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Typed Org']);
    $relationshipId = contactRelationshipId($organization, $contact);

    Livewire::test(Edit::class)
        ->call('openEdit', $relationshipId)
        ->set('type', 'individual')
        ->set('name', 'Typed Org')
        ->call('save')
        ->assertHasNoErrors();

    expect($contact->fresh())->toBeInstanceOf(Organization::class)
        ->and($contact->fresh()?->name)->toBe('Typed Org');
});

test('a user-linked contact keeps its shared fields', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $person = contactIndividual($organization, ['name' => 'User Linked Shared']);
    $person->update(['user_id' => User::factory()->create()->getKey()]);
    $relationshipId = contactRelationshipId($organization, $person);

    Livewire::test(Edit::class)
        ->call('openEdit', $relationshipId)
        ->set('name', 'Tampered Linked Name')
        ->set('code', 'LINKED-1')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()?->name)->toBe('User Linked Shared')
        ->and(OrganizationRelationship::query()->findOrFail($relationshipId)->code)->toBe('LINKED-1');
});

test('a shared contact keeps its shared fields and still updates per-tenant fields', function () {
    $owner = Organization::factory()->create();
    $other = Organization::factory()->create();

    actingAsMember($owner, Role::Admin);

    $contact = contactOrganization($owner, ['name' => 'Shared Org', 'summary' => 'Original']);
    $contactPerson = $contact->primaryContact;
    $relationshipId = contactRelationshipId($owner, $contact);

    $other->organizationCounterparties()->attach($contact, ['type' => OrganizationRelationshipType::Customer]);

    Livewire::test(Edit::class)
        ->call('openEdit', $relationshipId)
        ->set('name', 'Tampered Name')
        ->set('summary', 'Tampered summary')
        ->set('contact_name', 'Tampered Person')
        ->set('code', 'SHARED-1')
        ->set('role', OrganizationRelationshipType::Vendor->value)
        ->call('save')
        ->assertHasNoErrors();

    $relationship = OrganizationRelationship::query()->findOrFail($relationshipId);

    expect($contact->fresh()?->name)->toBe('Shared Org')
        ->and($contact->fresh()?->summary)->toBe('Original')
        ->and($contactPerson?->fresh()?->name)->not->toBe('Tampered Person')
        ->and($relationship->code)->toBe('SHARED-1')
        ->and($relationship->type)->toBe(OrganizationRelationshipType::Vendor);
});

test('a contact photo can be replaced and cleared', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $person = contactIndividual($organization, ['name' => 'Photo Person']);
    $person->update(['photo_url' => 'photos/old.jpg']);
    Storage::disk()->put('photos/old.jpg', 'old');

    $relationshipId = contactRelationshipId($organization, $person);

    Livewire::test(Edit::class)
        ->call('openEdit', $relationshipId)
        ->set('photo', UploadedFile::fake()->image('new.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $newPath = $person->fresh()?->getRawOriginal('photo_url');

    expect($newPath)->toBeString()
        ->and($newPath)->not->toBe('photos/old.jpg');

    Storage::disk()->assertMissing('photos/old.jpg');
    Storage::disk()->assertExists($newPath);

    Livewire::test(Edit::class)
        ->call('openEdit', $relationshipId)
        ->call('clearPhoto')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()?->getRawOriginal('photo_url'))->toBeNull();
    Storage::disk()->assertMissing($newPath);
});

test('a contact code stays unique on update', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $first = contactIndividual($organization, ['name' => 'First']);
    $second = contactIndividual($organization, ['name' => 'Second']);

    OrganizationRelationship::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_type', $first->getMorphClass())
        ->where('stakeholder_id', $first->getKey())
        ->update(['code' => 'CODE-1']);

    $relationshipId = contactRelationshipId($organization, $second);

    Livewire::test(Edit::class)
        ->call('openEdit', $relationshipId)
        ->set('code', 'CODE-1')
        ->call('save')
        ->assertHasErrors('code');

    Livewire::test(Edit::class)
        ->call('openEdit', $relationshipId)
        ->set('code', 'CODE-2')
        ->call('save')
        ->assertHasNoErrors();
});

test('a plain member cannot open the edit flyout', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $contact = contactIndividual($organization, ['name' => 'Read Only']);
    $relationshipId = contactRelationshipId($organization, $contact);

    Livewire::test(Edit::class)
        ->call('openEdit', $relationshipId)
        ->assertForbidden();
});

test('the actions menu can open the edit flyout with the keyboard', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactIndividual($organization, ['name' => 'Keyboard Edit']);
    $relationshipId = contactRelationshipId($organization, $contact);

    Livewire::test(Index::class)
        ->call('editContact', $relationshipId)
        ->assertDispatched('edit-contact', contactId: $relationshipId);
});

test('a shared contact ignores empty shared fields on save', function () {
    $owner = Organization::factory()->create();
    $other = Organization::factory()->create();

    actingAsMember($owner, Role::Admin);

    $contact = contactOrganization($owner, ['name' => 'Shared Empty', 'summary' => 'Kept']);
    $relationshipId = contactRelationshipId($owner, $contact);
    $other->organizationCounterparties()->attach($contact, ['type' => OrganizationRelationshipType::Customer]);

    Livewire::test(Edit::class)
        ->call('openEdit', $relationshipId)
        ->set('name', '')
        ->set('summary', '')
        ->set('contact_name', '')
        ->set('code', 'EMPTY-1')
        ->call('save')
        ->assertHasNoErrors();

    expect($contact->fresh()?->name)->toBe('Shared Empty')
        ->and($contact->fresh()?->summary)->toBe('Kept')
        ->and(OrganizationRelationship::query()->findOrFail($relationshipId)->code)->toBe('EMPTY-1');
});

test('an individual edit ignores the contact person summary', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactIndividual($organization, ['name' => 'Individual Contact']);
    $relationshipId = contactRelationshipId($organization, $contact);

    Livewire::test(Edit::class)
        ->call('openEdit', $relationshipId)
        ->set('contact_summary', str_repeat('x', 300))
        ->call('save')
        ->assertHasNoErrors();
});

test('a failed update removes the new photo and keeps the previous one', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $first = contactIndividual($organization, ['name' => 'First Photo']);
    $first->update(['photo_url' => 'photos/first.jpg']);
    OrganizationRelationship::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_type', $first->getMorphClass())
        ->where('stakeholder_id', $first->getKey())
        ->update(['code' => 'DUP-UPDATE']);

    $second = contactIndividual($organization, ['name' => 'Second Photo']);
    $second->update(['photo_url' => 'photos/second.jpg']);
    $secondRelationshipId = contactRelationshipId($organization, $second);

    Storage::disk()->put('photos/first.jpg', 'first');
    Storage::disk()->put('photos/second.jpg', 'second');

    $data = [
        'name' => 'Second Photo',
        'summary' => null,
        'code' => 'DUP-UPDATE',
        'role' => OrganizationRelationshipType::Customer,
        'start_date' => now()->toDateString(),
        'finish_date' => null,
        'contact_name' => null,
        'contact_gender' => null,
        'contact_summary' => null,
    ];

    expect(fn () => app(UpdateContact::class)->handle(
        $organization,
        $secondRelationshipId,
        $data,
        UploadedFile::fake()->image('rollback.jpg'),
    ))->toThrow(QueryException::class);

    expect(Storage::disk()->allFiles('photos'))->toEqualCanonicalizing([
        'photos/first.jpg',
        'photos/second.jpg',
    ]);
});

test('the edit flyout is a floating right-side flyout with a bottom footer', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Edit::class)
        ->assertSeeHtml('data-flux-flyout')
        ->assertSeeHtml('ml-auto')
        ->assertSeeHtml('rounded-xl')
        ->assertSeeHtml('min-h-[calc(100dvh-5rem)]')
        ->assertSeeHtml('mt-auto');
});
