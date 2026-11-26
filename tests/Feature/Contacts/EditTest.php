<?php

declare(strict_types=1);

use App\Actions\Contacts\Audit\ContactUpdatedPayload;
use App\Actions\Contacts\SaveContactDetails;
use App\Actions\Contacts\UpdateContact;
use App\Actions\Tenancies\ToggleFeature;
use App\Enums\AuditEvent;
use App\Enums\ContactDetailType;
use App\Enums\Feature;
use App\Enums\Gender;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\Contacts\Form;
use App\Livewire\Contacts\Index;
use App\Models\AuditLog;
use App\Models\ContactDetail;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('editing a contact updates its per-tenant and shared fields', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    app(ToggleFeature::class)->activate($organization, Feature::ContactGender);

    $contact = contactOrganization($organization, ['name' => 'Acme Corp']);
    $contactPerson = $contact->primaryContact;
    $relationshipId = contactRelationshipId($organization, $contact);

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->assertSet('name', 'Acme Corp')
        ->assertSet('type', 'organization')
        ->set('name', 'Acme Updated')
        ->set('summary', 'Updated summary')
        ->set('code', 'UPDATED-1')
        ->set('role', OrganizationRelationshipType::Supplier->value)
        ->set('startDate', '2026-01-01')
        ->set('finishDate', '2026-12-31')
        ->set('contactName', 'Nara Updated')
        ->set('contactGender', Gender::Male->value)
        ->set('contactSummary', 'Updated contact person')
        ->call('save')
        ->assertHasNoErrors();

    $contact->refresh();
    $contactPerson?->refresh();
    $relationship = OrganizationRelationship::query()->findOrFail($relationshipId);

    expect($contact->name)->toBe('Acme Updated');
    expect($contact->summary)->toBe('Updated summary');
    expect($contactPerson?->name)->toBe('Nara Updated');
    expect($contactPerson?->gender)->toBe(Gender::Male);
    expect($relationship->code)->toBe('UPDATED-1');
    expect($relationship->type)->toBe(OrganizationRelationshipType::Supplier);
    expect($relationship->start_date?->toDateString())->toBe('2026-01-01');
    expect($relationship->finish_date?->toDateString())->toBe('2026-12-31');
});

test('editing keeps the contact type even when the client changes it', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Typed Org']);
    $relationshipId = contactRelationshipId($organization, $contact);

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->set('type', 'individual')
        ->set('name', 'Typed Org')
        ->call('save')
        ->assertHasNoErrors();

    expect($contact->fresh())->toBeInstanceOf(Organization::class);
    expect($contact->fresh()?->name)->toBe('Typed Org');
});

test('a user-linked contact keeps its shared fields', function () {
    $organization = Organization::factory()->create();
    $owner = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $person = contactIndividual($organization, ['name' => 'User Linked Shared']);
    $person->update([
        'user_id' => User::factory()->withoutProfile()->create()->getKey(),
        'owner_organization_id' => $owner->getKey(),
    ]);
    $relationshipId = contactRelationshipId($organization, $person);

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->set('name', 'Tampered Linked Name')
        ->set('code', 'LINKED-1')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()?->name)->toBe('User Linked Shared');
    expect(OrganizationRelationship::query()->findOrFail($relationshipId)->code)->toBe('LINKED-1');
});

test('a shared contact keeps its shared fields and still updates per-tenant fields', function () {
    $owner = Organization::factory()->create();
    $other = Organization::factory()->create();

    actingAsMember($other, Role::Admin);

    $contact = contactOrganization($owner, ['name' => 'Shared Org', 'summary' => 'Original']);
    $contactPerson = $contact->primaryContact;

    $other->organizationCounterparties()->attach($contact, ['type' => OrganizationRelationshipType::Customer, 'start_date' => now()]);

    $relationshipId = contactRelationshipId($other, $contact);

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->set('name', 'Tampered Name')
        ->set('summary', 'Tampered summary')
        ->set('contactName', 'Tampered Person')
        ->set('code', 'SHARED-1')
        ->set('role', OrganizationRelationshipType::Vendor->value)
        ->call('save')
        ->assertHasNoErrors();

    $relationship = OrganizationRelationship::query()->findOrFail($relationshipId);

    expect($contact->fresh()?->name)->toBe('Shared Org');
    expect($contact->fresh()?->summary)->toBe('Original');
    expect($contactPerson?->fresh()?->name)->not->toBe('Tampered Person');
    expect($relationship->code)->toBe('SHARED-1');
    expect($relationship->type)->toBe(OrganizationRelationshipType::Vendor);
});

test('editing an individual contact updates the gender', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    app(ToggleFeature::class)->activate($organization, Feature::ContactGender);

    $person = contactIndividual($organization, ['name' => 'Gender Person', 'gender' => null]);
    $relationshipId = contactRelationshipId($organization, $person);

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->assertSet('gender', null)
        ->set('gender', Gender::Female->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()?->gender)->toBe(Gender::Female);
});

test('an existing individual can keep a blank gender on update', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    app(ToggleFeature::class)->activate($organization, Feature::ContactGender);

    $person = contactIndividual($organization, ['name' => 'Blank Gender', 'gender' => null]);
    $relationshipId = contactRelationshipId($organization, $person);

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->assertSet('gender', null)
        ->set('code', 'BLANK-1')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()?->gender)->toBeNull();
    expect(OrganizationRelationship::query()->findOrFail($relationshipId)->code)->toBe('BLANK-1');
});

test('the gender field is hidden when the feature is off', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Form::class)
        ->call('openCreate')
        ->assertDontSee(__('Gender'));
});

test('the gender field is shown when the feature is on', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    app(ToggleFeature::class)->activate($organization, Feature::ContactGender);

    Livewire::test(Form::class)
        ->call('openCreate')
        ->assertSee(__('Gender'));
});

test('editing a contact preserves gender when the feature is off', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $person = contactIndividual($organization, ['name' => 'Preserved Gender', 'gender' => Gender::Female]);
    $relationshipId = contactRelationshipId($organization, $person);

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->set('name', 'Renamed Gender')
        ->set('summary', 'Updated')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()?->gender)->toBe(Gender::Female);
});

test('a hidden gender is not submitted when the feature is off', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Form::class)
        ->call('openCreate')
        ->set('name', 'No Gender Field')
        ->set('gender', Gender::Male->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(Person::query()->where('name', 'No Gender Field')->firstOrFail()->gender)->toBeNull();
});

test('a contact photo can be replaced and cleared', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $person = contactIndividual($organization, ['name' => 'Photo Person']);
    $person->update(['photo_url' => 'photos/old.jpg']);
    Storage::disk()->put('photos/old.jpg', 'old');

    $relationshipId = contactRelationshipId($organization, $person);

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->set('photo', UploadedFile::fake()->image('new.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $newPath = $person->fresh()?->getRawOriginal('photo_url');

    expect($newPath)->toBeString();
    expect($newPath)->not->toBe('photos/old.jpg');

    /** @disregard */
    Storage::disk()->assertMissing('photos/old.jpg');
    /** @disregard */
    Storage::disk()->assertExists($newPath);

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->call('clearPhoto')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()?->getRawOriginal('photo_url'))->toBeNull();

    /** @disregard */
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

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->set('code', 'CODE-1')
        ->call('save')
        ->assertHasErrors('code');

    Livewire::test(Form::class)
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

    Livewire::test(Form::class)
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

test('the owner can edit a shared contact profile', function () {
    $owner = Organization::factory()->create();
    $other = Organization::factory()->create();

    actingAsMember($owner, Role::Admin);

    $contact = contactOrganization($owner, ['name' => 'Owned Org', 'summary' => 'Original']);
    $relationshipId = contactRelationshipId($owner, $contact);

    $other->organizationCounterparties()->attach($contact, ['type' => OrganizationRelationshipType::Customer]);

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->set('name', 'Renamed Org')
        ->set('summary', 'Updated summary')
        ->call('save')
        ->assertHasNoErrors();

    expect($contact->fresh()?->name)->toBe('Renamed Org')
        ->and($contact->fresh()?->summary)->toBe('Updated summary');
});

test('a shared contact ignores empty shared fields on save', function () {
    $owner = Organization::factory()->create();
    $other = Organization::factory()->create();

    actingAsMember($other, Role::Admin);

    $contact = contactOrganization($owner, ['name' => 'Shared Empty', 'summary' => 'Kept']);
    $other->organizationCounterparties()->attach($contact, ['type' => OrganizationRelationshipType::Customer, 'start_date' => now()]);

    $relationshipId = contactRelationshipId($other, $contact);

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->set('name', '')
        ->set('summary', '')
        ->set('contactName', '')
        ->set('code', 'EMPTY-1')
        ->call('save')
        ->assertHasNoErrors();

    expect($contact->fresh()?->name)->toBe('Shared Empty');
    expect($contact->fresh()?->summary)->toBe('Kept');
    expect(OrganizationRelationship::query()->findOrFail($relationshipId)->code)->toBe('EMPTY-1');
});

test('a save is rejected when the contact stops being shared after validation', function () {
    $owner = Organization::factory()->create();
    $other = Organization::factory()->create();

    actingAsMember($owner, Role::Admin);

    $contact = contactOrganization($owner, ['name' => 'Race Org']);
    $relationshipId = contactRelationshipId($owner, $contact);
    $other->organizationCounterparties()->attach($contact, ['type' => OrganizationRelationshipType::Customer]);

    // The other tenant detaches after the form validated with locked fields.
    $other->organizationCounterparties()->detach($contact);

    expect(fn () => app(UpdateContact::class)->handle(
        $owner,
        $relationshipId,
        [
            'name' => '',
            'summary' => null,
            'code' => 'RACE-1',
            'role' => OrganizationRelationshipType::Customer,
            'start_date' => now()->toDateString(),
        ],
        validatedSharedFieldsLocked: true,
    ))->toThrow(ValidationException::class);

    expect($contact->fresh()?->name)->toBe('Race Org');
    expect(OrganizationRelationship::query()->findOrFail($relationshipId)->code)->toBeNull();
});

test('an individual edit ignores the contact person summary', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactIndividual($organization, ['name' => 'Individual Contact']);
    $relationshipId = contactRelationshipId($organization, $contact);

    Livewire::test(Form::class)
        ->call('openEdit', $relationshipId)
        ->set('contactSummary', str_repeat('x', 300))
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
        ->forOrganization($organization)
        ->forStakeholder($first)
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

    Livewire::test(Form::class)
        ->assertSeeHtml('data-flux-flyout')
        ->assertSeeHtml('ml-auto')
        ->assertSeeHtml('rounded-xl')
        ->assertSeeHtml('flex flex-1 flex-col')
        ->assertSeeHtml('mt-auto');
});

test('the gender flag is refreshed before saving', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    app(ToggleFeature::class)->activate($organization, Feature::ContactGender);

    $person = contactIndividual($organization, ['name' => 'Refresh Gender', 'gender' => Gender::Female]);
    $relationshipId = contactRelationshipId($organization, $person);

    $component = Livewire::test(Form::class)->call('openEdit', $relationshipId);

    app(ToggleFeature::class)->deactivate($organization, Feature::ContactGender);

    $component
        ->set('gender', Gender::Male->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($person->fresh()?->gender)->toBe(Gender::Female);
});

test('a shared contact still lets another tenant change its email and phone', function () {
    $owner = Organization::factory()->create();
    $other = Organization::factory()->create();

    $contact = contactOrganization($owner, ['name' => 'Shared Detail Org']);
    $contact->contactDetails()->create(['type' => ContactDetailType::Email, 'value' => 'old@acme.test']);

    $other->organizationCounterparties()->attach($contact, [
        'type' => OrganizationRelationshipType::Customer,
        'start_date' => now(),
    ]);

    actingAsMember($other, Role::Admin);

    Livewire::test(Form::class)
        ->call('openEdit', contactRelationshipId($other, $contact))
        ->assertSet('contactEmail', 'old@acme.test')
        ->set('contactEmail', 'new@acme.test')
        ->set('contactPhone', '0812')
        ->call('save')
        ->assertHasNoErrors();

    expect($contact->fresh()?->contactDetailValue(ContactDetailType::Email))->toBe('new@acme.test')
        ->and($contact->fresh()?->contactDetailValue(ContactDetailType::Phone))->toBe('0812');
});

test('a stale reuse reference cannot skip contact detail validation on edit', function () {
    $owner = Organization::factory()->create();
    $other = Organization::factory()->create();

    $contact = contactOrganization($owner, ['name' => 'Reuse Guard']);

    // An expired relationship keeps the contact reusable, so the reference resolves.
    $other->organizationCounterparties()->attach($contact, [
        'type' => OrganizationRelationshipType::Customer,
        'start_date' => now()->subMonths(2),
        'finish_date' => now()->subMonth(),
    ]);

    actingAsMember($other, Role::Admin);

    Livewire::test(Form::class)
        ->call('openEdit', contactRelationshipId($other, $contact))
        ->set('selectedEntity', 'organization:'.$contact->uuid)
        ->set('contactEmail', 'not-an-email')
        ->call('save')
        ->assertHasErrors('contactEmail');
});

test('saving an unrelated field keeps a contact detail saved by another session', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactIndividual($organization, ['name' => 'Stale Details']);
    $relationshipId = contactRelationshipId($organization, $contact);

    $form = Livewire::test(Form::class)->call('openEdit', $relationshipId);

    app(SaveContactDetails::class)->handle($contact, [
        ContactDetailType::Email->value => 'newer@acme.test',
    ]);

    $form->set('code', 'STALE-1')->call('save')->assertHasNoErrors();

    expect($contact->fresh()?->contactDetailValue(ContactDetailType::Email))->toBe('newer@acme.test')
        ->and(OrganizationRelationship::query()->whereKey($relationshipId)->value('code'))->toBe('STALE-1');
});

test('a stale contact save keeps a photo saved by another session', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Photo Guard']);

    $form = Livewire::test(Form::class)
        ->call('openEdit', contactRelationshipId($organization, $contact));

    Storage::disk()->put('logos/new.png', 'image');
    $contact->update(['logo_url' => 'logos/new.png']);

    $form->set('code', 'PHOTO-1')->call('save')->assertHasNoErrors();

    expect($contact->fresh()?->getRawOriginal('logo_url'))->toBe('logos/new.png')
        ->and(Storage::disk()->exists('logos/new.png'))->toBeTrue();
});

test('a stale contact save keeps an address saved by another session', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactIndividual($organization, ['name' => 'Address Guard']);

    $form = Livewire::test(Form::class)
        ->call('openEdit', contactRelationshipId($organization, $contact));

    $contact->addresses()->create(validAddress());

    $form->set('code', 'ADDR-1')->call('save')->assertHasNoErrors();

    expect($contact->addresses()->exists())->toBeTrue();
});

test('a stale contact save keeps the code and role saved by another session', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactIndividual($organization, ['name' => 'Terms Guard']);
    $relationshipId = contactRelationshipId($organization, $contact);

    $form = Livewire::test(Form::class)->call('openEdit', $relationshipId);

    OrganizationRelationship::query()->whereKey($relationshipId)->update([
        'code' => 'NEW-CODE',
        'type' => OrganizationRelationshipType::Supplier->value,
    ]);

    $form->set('name', 'Renamed Contact')->call('save')->assertHasNoErrors();

    $relationship = OrganizationRelationship::query()->whereKey($relationshipId)->firstOrFail();

    expect($relationship->code)->toBe('NEW-CODE')
        ->and($relationship->type)->toBe(OrganizationRelationshipType::Supplier);
});

test('editing an organization contact works when the contact person is deleted', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Deleted Person Org']);
    $contact->primaryContact?->delete();

    Livewire::test(Form::class)
        ->call('openEdit', contactRelationshipId($organization, $contact))
        ->set('contactPersonEmail', 'kept@acme.test')
        ->call('save')
        ->assertHasNoErrors();

    expect($contact->primaryContact?->contactDetailValue(ContactDetailType::Email))->toBe('kept@acme.test');
});

test('a stale finish date cannot invert the relationship dates', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactIndividual($organization, ['name' => 'Date Guard']);
    $relationshipId = contactRelationshipId($organization, $contact);

    OrganizationRelationship::query()->whereKey($relationshipId)->update(['start_date' => '2026-01-01']);

    $form = Livewire::test(Form::class)->call('openEdit', $relationshipId);

    OrganizationRelationship::query()->whereKey($relationshipId)->update(['start_date' => '2026-06-01']);

    $form->set('finishDate', '2026-03-01')->call('save')->assertHasErrors('finishDate');

    expect(OrganizationRelationship::query()->whereKey($relationshipId)->value('finish_date'))->toBeNull();
});

test('a stale contact save keeps the name and summary saved by another session', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactIndividual($organization, ['name' => 'Profile Guard']);

    $form = Livewire::test(Form::class)
        ->call('openEdit', contactRelationshipId($organization, $contact));

    $contact->update(['name' => 'Newer Name', 'summary' => 'Newer summary']);

    $form->set('code', 'PROFILE-1')->call('save')->assertHasNoErrors();

    $contact->refresh();

    expect($contact->name)->toBe('Newer Name')
        ->and($contact->summary)->toBe('Newer summary');
});

test('a stale contact save keeps the contact person name saved by another session', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Person Guard']);

    $form = Livewire::test(Form::class)
        ->call('openEdit', contactRelationshipId($organization, $contact));

    $contact->primaryContact?->update(['name' => 'Newer Person Name']);

    $form->set('code', 'PERSON-1')->call('save')->assertHasNoErrors();

    expect($contact->primaryContact?->fresh()->name)->toBe('Newer Person Name');
});

test('a stale contact save keeps a gender saved by another session', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    app(ToggleFeature::class)->activate($organization, Feature::ContactGender);

    $contact = contactIndividual($organization, ['name' => 'Gender Guard']);

    $form = Livewire::test(Form::class)
        ->call('openEdit', contactRelationshipId($organization, $contact));

    $contact->update(['gender' => Gender::Female]);

    $form->set('code', 'GENDER-1')->call('save')->assertHasNoErrors();

    expect($contact->fresh()?->gender)->toBe(Gender::Female);
});

test('an organization contact person email and phone are editable and clearable', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactOrganization($organization, ['name' => 'Contact Person Details']);
    $contactPerson = $contact->primaryContact;

    $form = Livewire::test(Form::class)
        ->call('openEdit', contactRelationshipId($organization, $contact))
        ->set('contactPersonEmail', 'Person@Acme.Test')
        ->set('contactPersonPhone', '0812 444')
        ->call('save')
        ->assertHasNoErrors();

    /** @disregard P1006 */
    expect($contactPerson?->fresh()?->contactDetailValue(ContactDetailType::Email))->toBe('person@acme.test')
        ->and($contactPerson?->fresh()?->contactDetailValue(ContactDetailType::Phone))->toBe('0812 444')
        ->and(AuditLog::query()->where('event', AuditEvent::ContactUpdated)->sole()->changes?->toArray())
        ->toBe((new ContactUpdatedPayload(['contact_email', 'contact_phone']))->toArray());

    $form->set('contactPersonEmail', '')
        ->set('contactPersonPhone', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($contactPerson?->fresh()?->contactDetailValue(ContactDetailType::Email))->toBeNull()
        ->and($contactPerson?->fresh()?->contactDetailValue(ContactDetailType::Phone))->toBeNull();
});

test('clearing a contact record deletes it', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactIndividual($organization, ['name' => 'Clearable']);
    $contact->contactDetails()->create(['type' => ContactDetailType::Email, 'value' => 'clear@acme.test']);

    Livewire::test(Form::class)
        ->call('openEdit', contactRelationshipId($organization, $contact))
        ->set('contactEmail', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($contact->fresh()?->contactDetailValue(ContactDetailType::Email))->toBeNull()
        ->and(ContactDetail::query()->count())->toBe(0);
});

test('contact detail audit records field names without values', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $contact = contactIndividual($organization, ['name' => 'Audited']);
    $contact->contactDetails()->create(['type' => ContactDetailType::Email, 'value' => 'old@acme.test']);

    Livewire::test(Form::class)
        ->call('openEdit', contactRelationshipId($organization, $contact))
        ->set('contactEmail', 'new@acme.test')
        ->set('contactPhone', '0812 777')
        ->call('save')
        ->assertHasNoErrors();

    /** @var AuditLog $log */
    $log = AuditLog::query()->where('event', AuditEvent::ContactUpdated)->sole();
    $encoded = $log->getRawOriginal('changes');

    expect($log->changes?->toArray())->toBe((new ContactUpdatedPayload(['email', 'phone']))->toArray())
        ->and($encoded)->not->toContain('new@acme.test')
        ->and($encoded)->not->toContain('0812 777');
});
