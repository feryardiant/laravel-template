<?php

declare(strict_types=1);

use App\Actions\FileStorage;
use App\Actions\Tenancies\ToggleFeature;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\ContactDetailType;
use App\Enums\Feature;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Jobs\DeleteOrganization;
use App\Models\Address;
use App\Models\ApprovalRequest;
use App\Models\ContactDetail;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Notifications\OrganizationDeletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\FeatureManager;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->root = Organization::factory()->asRootTenant()->create(['name' => 'Root Org']);

    Notification::fake();
});

test('it deletes the subsidiary subtree and its data', function () {
    Storage::fake();
    Storage::disk()->put('logos/alpha.png', 'x');
    Storage::disk()->put('logos/beta.png', 'x');

    /** @var TestCase $this */
    $owner = actingAsMember($this->root, Role::Owner);

    $alpha = Organization::factory()->asTenantOf($this->root)->withAddress()->create([
        'name' => 'Alpha',
        'logo_url' => 'logos/alpha.png',
    ]);
    $this->root->subsidiaries()->attach($alpha, ['type' => OrganizationRelationshipType::Subsidiary]);

    $beta = Organization::factory()->asTenantOf($this->root)->withAddress()->create([
        'name' => 'Beta',
        'logo_url' => 'logos/beta.png',
    ]);
    $alpha->subsidiaries()->attach($beta, ['type' => OrganizationRelationshipType::Subsidiary]);

    $member = User::factory()->asMemberOf($alpha, Role::Member)->create();

    app(ToggleFeature::class)->activate($alpha, Feature::ContactGender);

    (new DeleteOrganization($alpha, $owner->getKey()))->handle(app(FileStorage::class));

    expect(Organization::withTrashed()->whereKey([$alpha->getKey(), $beta->getKey()])->exists())->toBeFalse()
        ->and(Organization::query()->whereKey($this->root->getKey())->exists())->toBeTrue()
        ->and(User::query()->whereKey($member->getKey())->exists())->toBeTrue()
        ->and(Person::query()->whereKey($member->person->getKey())->exists())->toBeTrue();

    expect(Address::query()
        ->whereIn('addressable_id', [$alpha->getKey(), $beta->getKey()])
        ->where('addressable_type', Organization::class)
        ->exists())->toBeFalse();

    expect(OrganizationRelationship::query()->forStakeholder($alpha)->exists())->toBeFalse()
        ->and(OrganizationRelationship::query()->forStakeholder($beta)->exists())->toBeFalse();

    expect(DB::table('features')
        ->where('scope', app(FeatureManager::class)->serializeScope($alpha))
        ->exists())->toBeFalse();

    /** @disregard */
    Storage::disk()->assertMissing('logos/alpha.png');
    /** @disregard */
    Storage::disk()->assertMissing('logos/beta.png');

    Notification::assertSentTo(
        $member,
        OrganizationDeletedNotification::class,
        fn (OrganizationDeletedNotification $notification): bool => $notification->organizationName === 'Alpha',
    );
    Notification::assertNotSentTo($owner, OrganizationDeletedNotification::class);
});

test('it tombstones an externally referenced organization', function () {
    Storage::fake();
    Storage::disk()->put('logos/customer.png', 'x');

    /** @var TestCase $this */
    actingAsMember($this->root, Role::Owner);

    $customerOwner = User::factory()->withProfile()->create();
    $customer = Organization::factory()->asTenantOf($this->root)->withAddress()->create([
        'name' => 'Customer Org',
        'logo_url' => 'logos/customer.png',
    ]);
    $customer->contactDetails()->create([
        'type' => ContactDetailType::Email,
        'value' => 'retain@acme.test',
    ]);
    $customer->members()->attach($customerOwner->person, [
        'type' => OrganizationRelationshipType::Member,
        'role' => Role::Owner,
        'is_primary' => true,
        'start_date' => now(),
    ]);

    $member = User::factory()->asMemberOf($customer, Role::Member)->create();

    $vendor = Organization::factory()->asTenantOf($this->root)->create(['name' => 'Vendor Org']);
    $vendor->organizationCounterparties(OrganizationRelationshipType::Customer)->attach($customer, [
        'type' => OrganizationRelationshipType::Customer,
        'start_date' => now(),
    ]);

    app(ToggleFeature::class)->activate($customer, Feature::ContactGender);

    (new DeleteOrganization($customer, $customerOwner->getKey()))->handle(app(FileStorage::class));

    $tombstone = Organization::withTrashed()->whereKey($customer->getKey())->firstOrFail();

    expect($tombstone->trashed())->toBeTrue()
        ->and($tombstone->name)->toBe('Customer Org')
        ->and($tombstone->getRawOriginal('logo_url'))->toBeNull();

    expect(OrganizationRelationship::query()
        ->forOrganization($vendor)
        ->forStakeholder($customer)
        ->exists())->toBeTrue();

    expect(OrganizationRelationship::query()->where('organization_id', $customer->getKey())->exists())->toBeFalse()
        ->and(Address::query()
            ->where('addressable_id', $customer->getKey())
            ->where('addressable_type', Organization::class)
            ->exists())->toBeFalse()
        ->and(ContactDetail::query()
            ->where('contactable_id', $customer->getKey())
            ->where('contactable_type', Organization::class)
            ->exists())->toBeTrue()
        ->and($customerOwner->fresh()->roleIn($customer))->toBeNull();

    /** @disregard */
    Storage::disk()->assertMissing('logos/customer.png');

    Notification::assertSentTo(
        $member,
        OrganizationDeletedNotification::class,
        fn (OrganizationDeletedNotification $notification): bool => $notification->organizationName === 'Customer Org',
    );
    Notification::assertNotSentTo($customerOwner, OrganizationDeletedNotification::class);
});

test('it cancels pending approval requests when a subsidiary is tombstoned', function () {
    /** @var TestCase $this */
    $owner = actingAsMember($this->root, Role::Owner);

    $subsidiary = Organization::factory()->asTenantOf($this->root)->create(['name' => 'Subsidiary']);
    $this->root->subsidiaries()->attach($subsidiary, ['type' => OrganizationRelationshipType::Subsidiary]);

    $recipient = User::factory()->asMemberOf($subsidiary, Role::Member)->create();

    $vendor = Organization::factory()->asTenantOf($this->root)->create(['name' => 'Vendor']);
    $vendor->organizationCounterparties(OrganizationRelationshipType::Customer)->attach($subsidiary, [
        'type' => OrganizationRelationshipType::Customer,
    ]);

    $transfer = ApprovalRequest::create([
        'organization_id' => $subsidiary->getKey(),
        'type' => ApprovalRequestType::OwnershipTransfer,
        'requester_person_id' => $owner->person->getKey(),
        'approver_person_id' => $recipient->person->getKey(),
        'status' => ApprovalRequestStatus::Pending,
        'expires_at' => now()->addDays(7),
    ]);

    (new DeleteOrganization($subsidiary, $owner->getKey()))->handle(app(FileStorage::class));

    expect($transfer->fresh()->status)->toBe(ApprovalRequestStatus::Canceled);
});

test('it refuses to delete a tenant outside the internal subsidiary tree', function () {
    /** @var TestCase $this */
    $owner = actingAsMember($this->root, Role::Owner);

    $customer = Organization::factory()->asTenantOf($this->root)->create(['name' => 'Customer Org']);
    $this->root->organizationCounterparties(OrganizationRelationshipType::Customer)->attach($customer, [
        'type' => OrganizationRelationshipType::Customer,
    ]);

    $child = Organization::factory()->asTenantOf($this->root)->create(['name' => 'Customer Child']);
    $customer->subsidiaries()->attach($child, ['type' => OrganizationRelationshipType::Subsidiary]);

    (new DeleteOrganization($child, $owner->getKey()))->handle(app(FileStorage::class));

    expect(Organization::query()->whereKey($child->getKey())->exists())->toBeTrue();
});

test('it refuses to delete when the actor is no longer a root owner', function () {
    /** @var TestCase $this */
    $member = actingAsMember($this->root, Role::Member);

    $alpha = Organization::factory()->asTenantOf($this->root)->create(['name' => 'Alpha']);
    $this->root->subsidiaries()->attach($alpha, ['type' => OrganizationRelationshipType::Subsidiary]);

    (new DeleteOrganization($alpha, $member->getKey()))->handle(app(FileStorage::class));

    expect(Organization::query()->whereKey($alpha->getKey())->exists())->toBeTrue();
});

test('it refuses to delete the root tenant', function () {
    /** @var TestCase $this */
    $owner = actingAsMember($this->root, Role::Owner);

    (new DeleteOrganization($this->root, $owner->getKey()))->handle(app(FileStorage::class));

    expect(Organization::query()->whereKey($this->root->getKey())->exists())->toBeTrue();
});
