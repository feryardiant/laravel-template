<?php

declare(strict_types=1);

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\DeleteContact;
use App\Actions\Contacts\UpdateContact;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactDataForAudit(array $overrides = []): array
{
    return [
        'name' => 'Audit Person',
        'summary' => null,
        'code' => 'A-1',
        'role' => OrganizationRelationshipType::Customer,
        'type' => 'individual',
        'start_date' => '2026-01-01',
        'finish_date' => null,
        'contact_name' => null,
        'contact_gender' => null,
        'contact_summary' => null,
        'reference' => null,
        ...$overrides,
    ];
}

function auditContactRelationship(Organization $organization, Entity $entity): OrganizationRelationship
{
    return OrganizationRelationship::query()
        ->forOrganization($organization)
        ->external()
        ->whereStakeholder($entity)
        ->firstOrFail();
}

test('creating a contact records the actor and period', function () {
    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Admin);

    $entity = app(CreateContact::class)->handle($organization, contactDataForAudit(), actor: $actor);

    $log = AuditLog::query()->where('event', AuditEvent::ContactCreated)->sole();

    expect($log->organization_id)->toBe($organization->getKey())
        ->and($log->actor_id)->toBe($actor->getKey())
        ->and($log->subject_id)->toBe($entity->getKey())
        ->and($log->changes)->toMatchArray([
            'kind' => 'individual',
            'role' => OrganizationRelationshipType::Customer->value,
            'code' => 'A-1',
        ])
        ->and($log->organization_relationship_period_id)->not->toBeNull();
});

test('renewing an expired contact records a renewal instead of a duplicate creation', function () {
    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Admin);

    $entity = app(CreateContact::class)->handle($organization, contactDataForAudit([
        'finish_date' => '2026-01-31',
    ]), actor: $actor);

    app(CreateContact::class)->handle($organization, contactDataForAudit([
        'code' => 'A-2',
        'start_date' => '2026-03-01',
        'reference' => 'individual:'.$entity->uuid,
    ]), actor: $actor);

    $log = AuditLog::query()->where('event', AuditEvent::ContactRenewed)->sole();

    expect($log->subject_id)->toBe($entity->getKey())
        ->and($log->changes)->toMatchArray([
            'kind' => 'individual',
            'role' => OrganizationRelationshipType::Customer->value,
            'code' => 'A-2',
        ])
        ->and($log->organization_relationship_period_id)->not->toBeNull()
        ->and(AuditLog::query()->where('event', AuditEvent::ContactCreated)->count())->toBe(1);
});

test('updating a contact records field names but no personal values', function () {
    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Admin);

    $entity = app(CreateContact::class)->handle($organization, contactDataForAudit([
        'name' => 'Original Name',
        'summary' => 'Original summary',
    ]), actor: $actor);

    $relationship = auditContactRelationship($organization, $entity);

    app(UpdateContact::class)->handle($organization, $relationship->getKey(), [
        'name' => 'Updated Name',
        'summary' => 'Updated summary',
        'code' => 'A-9',
        'role' => OrganizationRelationshipType::Supplier,
        'start_date' => '2026-02-01',
        'finish_date' => null,
    ], actor: $actor);

    $log = AuditLog::query()->where('event', AuditEvent::ContactUpdated)->sole();
    $changes = $log->changes ?? [];

    expect($changes['fields'] ?? [])->toContain('name', 'summary', 'code', 'type', 'start_date')
        ->and($log->organization_relationship_period_id)->not->toBeNull()
        ->and(json_encode($changes))->not->toContain('Updated Name')
        ->and(json_encode($changes))->not->toContain('Updated summary')
        ->and(json_encode($changes))->not->toContain('Original Name');
});

test('deleting a contact keeps the subject readable after the soft delete', function () {
    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Admin);

    $entity = app(CreateContact::class)->handle($organization, contactDataForAudit([
        'name' => 'Deleted Person',
    ]), actor: $actor);

    $relationship = auditContactRelationship($organization, $entity);

    app(DeleteContact::class)->handle($organization, $relationship->getKey(), actor: $actor);

    $log = AuditLog::query()->where('event', AuditEvent::ContactDeleted)->sole()->fresh();

    expect($log->actor_id)->toBe($actor->getKey())
        ->and($log->changes)->toMatchArray(['detached_only' => false])
        ->and($log->subjectLabel())->toBe('Deleted Person');
});

test('a bulk delete writes one entry per contact', function () {
    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Admin);

    $first = app(CreateContact::class)->handle($organization, contactDataForAudit([
        'name' => 'First Person',
        'code' => 'A-1',
    ]), actor: $actor);

    $second = app(CreateContact::class)->handle($organization, contactDataForAudit([
        'name' => 'Second Person',
        'code' => 'A-2',
    ]), actor: $actor);

    $ids = [
        auditContactRelationship($organization, $first)->getKey(),
        auditContactRelationship($organization, $second)->getKey(),
    ];

    app(DeleteContact::class)->handleBulk($organization, $ids, $actor);

    expect(AuditLog::query()->where('event', AuditEvent::ContactDeleted)->count())->toBe(2);
});
