<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\OrgSettings\General\TransferOwnership;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use Creasi\Nusa\Models\Village;
use Database\Factories\OrganizationFactory;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Pest\Browser\ServerManager;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Browser', 'Unit');

pest()->browser()->timeout(10_000);

/**
 * Count hidden form inputs matching one name and value in rendered HTML.
 *
 * The name and value assertions share this helper so a match cannot come from
 * two different elements.
 */
function hiddenFormInputCount(string $html, string $name, string $value): int
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $nodes = (new DOMXPath($document))->query(
        \sprintf('//input[@type="hidden"][@name="%s"][@value="%s"]', $name, $value),
    );

    return $nodes === false ? 0 : $nodes->length;
}

/**
 * Assert the browser test's last backend request did not throw.
 *
 * The browser plugin only mentions server exceptions when another assertion
 * already fails, so a 500 from a Livewire round-trip can otherwise go unseen.
 */
function assertNoBackendErrors(): void
{
    $throwable = ServerManager::instance()->http()->lastThrowable();

    expect($throwable)->toBeNull(
        'The last backend request threw: '.($throwable?->getMessage() ?? 'unknown'),
    );
}

/**
 * Sign in a user who is a member of the given organization.
 */
function actingAsMember(
    OrganizationFactory|Organization $organization,
    Role $role = Role::Member,
    ?true $isPrimary = null
): User {
    $user = User::factory()
        ->asMemberOf($organization, $role, $isPrimary)
        ->create();

    test()->actingAs($user);

    session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);

    return $user;
}

/**
 * Create an Organization contact for the given tenant.
 *
 * @param  array<string, mixed>  $attributes
 */
function contactOrganization(
    Organization $tenant,
    array $attributes = [],
    OrganizationRelationshipType $type = OrganizationRelationshipType::Customer,
): Organization {
    $contactPerson = Person::factory()->withoutCredential()->create([
        'owner_organization_id' => $tenant->getKey(),
    ]);

    $organization = Organization::factory()->create([
        'contact_id' => $contactPerson->getKey(),
        'owner_organization_id' => $tenant->getKey(),
        ...$attributes,
    ]);

    $tenant->organizationCounterparties($type)->attach($organization, [
        'type' => $type,
        'start_date' => now(),
    ]);

    return $organization;
}

/**
 * Create an Individual contact for the given tenant.
 *
 * @param  array<string, mixed>  $attributes
 */
function contactIndividual(
    Organization $tenant,
    array $attributes = [],
    OrganizationRelationshipType $type = OrganizationRelationshipType::Customer,
): Person {
    $person = Person::factory()->withoutCredential()->create([
        'owner_organization_id' => $tenant->getKey(),
        ...$attributes,
    ]);

    $tenant->personCounterparties($type)->attach($person, [
        'type' => $type,
        'start_date' => now(),
    ]);

    return $person;
}

/**
 * Resolve the relationship id for a contact of the tenant.
 */
function contactRelationshipId(Organization $organization, Organization|Person $stakeholder): int
{
    return (int) OrganizationRelationship::forOrganization($organization)
        ->whereStakeholder($stakeholder)
        ->value('id');
}

/**
 * A valid nusa region chain for address form tests.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, string>
 */
function validAddress(array $overrides = []): array
{
    $village = Village::query()->firstOrFail();

    return [
        'line' => 'Jl. Mawar 1',
        'rt' => '1',
        'rw' => '2',
        'postal_code' => (string) $village->postal_code,
        'province_code' => $village->province_code,
        'regency_code' => $village->regency_code,
        'district_code' => $village->district_code,
        'village_code' => $village->code,
        ...$overrides,
    ];
}

/**
 * Create a pending ownership transfer to the given member, as the signed-in owner.
 */
function requestOwnershipTransferForTest(Organization $organization, string $confirmation = 'Acme', ?Person $recipient = null): ApprovalRequest
{
    $recipientId = (string) ($recipient?->getKey() ?? $organization->members()
        ->whereKeyNot(Auth::user()?->person?->getKey())
        ->whereHas('user')
        ->firstOrFail()
        ->getKey());

    Livewire::test(TransferOwnership::class)
        ->call('confirmTransfer')
        ->set('recipientId', $recipientId)
        ->set('confirmation', $confirmation)
        ->call('transfer')
        ->assertHasNoErrors();

    return ApprovalRequest::query()->latest('id')->firstOrFail();
}

/**
 * Sign in the member who received an ownership transfer.
 */
function loginAsTransferRecipient(User $user, Organization $organization): void
{
    test()->actingAs($user);

    session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);
}

/**
 * Create a browser-test user who belongs to the test's shared organization.
 *
 * @param  array<string, mixed>  $attributes
 */
function browserMember(Role $role = Role::Member, array $attributes = [], bool $verified = true): User
{
    $organization = Organization::query()->first() ?? Organization::factory()->create();

    $factory = User::factory()->asMemberOf($organization, $role, $role->isOwner() ?: null);

    if (! $verified) {
        $factory = $factory->unverified();
    }

    return $factory->create($attributes);
}
