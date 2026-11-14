<?php

declare(strict_types=1);

use App\Actions\Organizations\ReconcileOwners;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function roleInOrganization(Organization $organization, Person $person): ?Role
{
    return OrganizationRelationship::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_id', $person->getKey())
        ->where('type', OrganizationRelationshipType::Member)
        ->first()
        ?->role;
}

function attachOwnerForReconcile(Organization $organization, Person $person, array $pivot = []): void
{
    $organization->members()->attach($person, [
        'type' => OrganizationRelationshipType::Member,
        'role' => Role::Owner,
        ...$pivot,
    ]);
}

beforeEach(function () {
    DB::statement('DROP INDEX IF EXISTS org_relationship_owner_unique');
});

it('keeps an active owner who can sign in', function () {
    $organization = Organization::factory()->create();

    $credentialless = Person::factory()->withoutCredential()->create();
    $expired = Person::factory()->create();
    $active = Person::factory()->create();

    attachOwnerForReconcile($organization, $credentialless);
    attachOwnerForReconcile($organization, $expired, ['finish_date' => now()->subDay()->toDateString()]);
    attachOwnerForReconcile($organization, $active);

    app(ReconcileOwners::class)->handle();

    expect(roleInOrganization($organization, $active))->toBe(Role::Owner)
        ->and(roleInOrganization($organization, $credentialless))->toBe(Role::Admin)
        ->and(roleInOrganization($organization, $expired))->toBe(Role::Admin);
});

it('falls back to the oldest owner when nobody can sign in', function () {
    $organization = Organization::factory()->create();

    $first = Person::factory()->withoutCredential()->create();
    $second = Person::factory()->withoutCredential()->create();

    attachOwnerForReconcile($organization, $first);
    attachOwnerForReconcile($organization, $second);

    app(ReconcileOwners::class)->handle();

    expect(roleInOrganization($organization, $first))->toBe(Role::Owner)
        ->and(roleInOrganization($organization, $second))->toBe(Role::Admin);
});
