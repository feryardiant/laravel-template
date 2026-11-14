<?php

declare(strict_types=1);

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\DeleteContact;
use App\Actions\Contacts\UpdateContact;
use App\Actions\Memberships\ChangeMemberRole;
use App\Actions\Memberships\CreateMember;
use App\Actions\Memberships\LeaveOrganization;
use App\Actions\Memberships\RemoveMember;
use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipPeriodReason;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function createIndividualContactForHistory(
    Organization $organization,
    array $overrides = [],
    ?string $reference = null,
    ?User $actor = null,
): OrganizationRelationship {
    app(CreateContact::class)->handle($organization, [
        'name' => 'History Person',
        'summary' => null,
        'code' => 'H-1',
        'role' => OrganizationRelationshipType::Customer,
        'type' => 'individual',
        'start_date' => '2026-01-01',
        'finish_date' => null,
        'contact_name' => null,
        'contact_gender' => null,
        'contact_summary' => null,
        'reference' => $reference,
        ...$overrides,
    ], actor: $actor);

    return OrganizationRelationship::query()->latest('id')->firstOrFail();
}

test('creating a contact opens a period with the same window', function () {
    $organization = Organization::factory()->create();
    actingAsMember($organization, Role::Admin);

    $relationship = createIndividualContactForHistory($organization);

    $period = OrganizationRelationshipPeriod::query()->where('organization_relationship_id', $relationship->getKey())->firstOrFail();

    expect($period->type)->toBe(OrganizationRelationshipType::Customer)
        ->and($period->code)->toBe('H-1')
        ->and($period->start_date->toDateString())->toBe('2026-01-01')
        ->and($period->finish_date)->toBeNull()
        ->and($period->closed_at)->toBeNull()
        ->and($period->isOpen())->toBeTrue();
});

test('renewing an expired contact closes the old period and opens a new one', function () {
    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Admin);

    $relationship = createIndividualContactForHistory($organization, [
        'start_date' => '2026-01-01',
        'finish_date' => '2026-01-31',
    ]);

    $person = $relationship->stakeholder;
    \assert($person instanceof Person);

    createIndividualContactForHistory(
        $organization,
        ['start_date' => '2026-03-01', 'finish_date' => null, 'code' => 'H-2'],
        reference: 'individual:'.$person->uuid,
        actor: $actor,
    );

    $periods = OrganizationRelationshipPeriod::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_id', $person->getKey())
        ->orderBy('id')
        ->get();

    expect($periods)->toHaveCount(2)
        ->and($periods[0]->reason)->toBe(RelationshipPeriodReason::Renewed)
        ->and($periods[0]->finish_date?->toDateString())->toBe('2026-01-31')
        ->and($periods[0]->closed_at)->not->toBeNull()
        ->and($periods[1]->code)->toBe('H-2')
        ->and($periods[1]->start_date->toDateString())->toBe('2026-03-01')
        ->and($periods[1]->isOpen())->toBeTrue();
});

test('editing the current dates updates the open period', function () {
    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Admin);

    $relationship = createIndividualContactForHistory($organization);

    app(UpdateContact::class)->handle($organization, $relationship->getKey(), [
        'name' => 'Updated Person',
        'summary' => null,
        'code' => 'H-9',
        'role' => OrganizationRelationshipType::Supplier,
        'start_date' => '2026-02-01',
        'finish_date' => '2026-12-31',
    ], actor: $actor);

    $period = OrganizationRelationshipPeriod::query()->where('organization_relationship_id', $relationship->getKey())->firstOrFail();

    expect($period->code)->toBe('H-9')
        ->and($period->type)->toBe(OrganizationRelationshipType::Supplier)
        ->and($period->start_date->toDateString())->toBe('2026-02-01')
        ->and($period->finish_date?->toDateString())->toBe('2026-12-31');
});

test('removing a member closes the current period', function () {
    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Owner);
    $member = User::factory()->withProfile()->create();

    app(CreateMember::class)->attach($organization, $member->person, startDate: now(), role: Role::Member);

    app(RemoveMember::class)->handle($organization, $member->person, $actor);

    $period = OrganizationRelationshipPeriod::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_id', $member->person->getKey())
        ->latest('id')
        ->firstOrFail();

    expect($period->reason)->toBe(RelationshipPeriodReason::Removed)
        ->and($period->actor_id)->toBe($actor->getKey())
        ->and($period->closed_at)->not->toBeNull();
});

test('leaving closes the current period as self-leave', function () {
    $organization = Organization::factory()->create();
    $user = actingAsMember($organization, Role::Member);

    app(CreateMember::class)->attach($organization, $user->person, startDate: now(), role: Role::Member);

    expect(app(LeaveOrganization::class)->handle($user, $organization))->toBeTrue();

    $period = OrganizationRelationshipPeriod::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_id', $user->person->getKey())
        ->latest('id')
        ->firstOrFail();

    expect($period->reason)->toBe(RelationshipPeriodReason::Left)
        ->and($period->actor_id)->toBeNull();
});

test('a role change closes the old role period and opens a new one', function () {
    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Owner);
    $member = User::factory()->withProfile()->create();

    app(CreateMember::class)->attach($organization, $member->person, startDate: now(), role: Role::Member);
    app(ChangeMemberRole::class)->promote($organization, $member->person, $actor);

    $periods = OrganizationRelationshipPeriod::query()
        ->where('organization_id', $organization->getKey())
        ->where('stakeholder_id', $member->person->getKey())
        ->orderBy('id')
        ->get();

    expect($periods)->toHaveCount(2)
        ->and($periods[0]->role)->toBe(Role::Member)
        ->and($periods[0]->reason)->toBe(RelationshipPeriodReason::RoleChanged)
        ->and($periods[1]->role)->toBe(Role::Admin)
        ->and($periods[1]->isOpen())->toBeTrue();
});

test('deleting a contact closes the period and keeps its history', function () {
    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Admin);

    $relationship = createIndividualContactForHistory($organization);

    app(DeleteContact::class)->handle($organization, $relationship->getKey(), actor: $actor);

    $period = OrganizationRelationshipPeriod::query()->latest('id')->firstOrFail();

    expect($period->reason)->toBe(RelationshipPeriodReason::Deleted)
        ->and($period->organization_relationship_id)->toBeNull()
        ->and($period->stakeholder_id)->not->toBeNull()
        ->and($period->isOpen())->toBeFalse();
});

test('the contact view renders its history', function () {
    $organization = Organization::factory()->create();
    actingAsMember($organization, Role::Admin);

    $relationship = createIndividualContactForHistory($organization);
    $person = $relationship->stakeholder;

    \assert($person instanceof Person);

    get(route('contacts.view', $person->uuid))
        ->assertOk()
        ->assertSee(__('History'))
        ->assertSee('2026-01-01')
        ->assertSee(__('Present'));
});

test('the member view renders its history', function () {
    $organization = Organization::factory()->create();
    actingAsMember($organization, Role::Owner);
    $member = User::factory()->withProfile()->create();

    app(CreateMember::class)->attach($organization, $member->person, startDate: now(), role: Role::Member);

    get(route('members.show', $member->person))
        ->assertOk()
        ->assertSee(__('History'))
        ->assertSee(__('Member'))
        ->assertSee(__('Present'));
});

test('the timeline does not duplicate an open period', function () {
    $organization = Organization::factory()->create();
    actingAsMember($organization, Role::Admin);

    $relationship = createIndividualContactForHistory($organization);

    app(RelationshipTimeline::class)->open($relationship);
    app(RelationshipTimeline::class)->open($relationship);

    expect(OrganizationRelationshipPeriod::query()->where('organization_relationship_id', $relationship->getKey())->count())->toBe(1);
});
