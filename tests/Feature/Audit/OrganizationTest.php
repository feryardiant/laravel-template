<?php

declare(strict_types=1);

use App\Actions\FileStorage;
use App\Actions\Organizations\CreateOrganization;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Jobs\DeleteOrganization;
use App\Livewire\OrgSettings\General;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('creating an organization records the parent and kind', function () {
    $root = Organization::factory()->asRootTenant()->create(['name' => 'Root']);
    $user = User::factory()->withProfile()->create();

    $organization = app(CreateOrganization::class)->handle(
        $user,
        'Acme',
        parent: $root,
        kind: SubsidiaryKind::Branch,
    );

    $log = AuditLog::query()->where('event', AuditEvent::OrganizationCreated)->sole();

    expect($log->organization_id)->toBe($organization->getKey())
        ->and($log->actor_id)->toBe($user->getKey())
        ->and($log->subject_id)->toBe($organization->getKey())
        ->and($log->changes)->toMatchArray([
            'parent_id' => $root->getKey(),
            'kind' => SubsidiaryKind::Branch->value,
        ]);

    $memberLog = AuditLog::query()->where('event', AuditEvent::MemberAdded)->sole();

    expect($memberLog->actor_id)->toBe($user->getKey())
        ->and($memberLog->subject_id)->toBe($user->person->getKey())
        ->and($memberLog->organization_relationship_period_id)->not->toBeNull();
});

test('updating an organization records the changed field names', function () {
    $organization = Organization::factory()->create(['name' => 'Old Name']);
    $owner = actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->set('name', 'New Name')
        ->call('update')
        ->assertHasNoErrors();

    $log = AuditLog::query()->where('event', AuditEvent::OrganizationUpdated)->sole();

    expect($log->actor_id)->toBe($owner->getKey())
        ->and($log->subject_id)->toBe($organization->getKey())
        ->and($log->changes['fields'] ?? [])->toContain('name');
});

test('an update with no changes writes no entry', function () {
    $organization = Organization::factory()->create(['name' => 'Same Name']);

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->call('update')
        ->assertHasNoErrors();

    expect(AuditLog::query()->where('event', AuditEvent::OrganizationUpdated)->count())->toBe(0);
});

test('deleting an organization prunes its audit log', function () {
    Notification::fake();

    $root = Organization::factory()->asRootTenant()->create(['name' => 'Root']);
    $owner = actingAsMember($root, Role::Owner);

    $organization = Organization::factory()->asTenantOf($root)->create(['name' => 'Branch']);
    $root->subsidiaries()->attach($organization, ['type' => OrganizationRelationshipType::Subsidiary]);

    AuditLog::create([
        'organization_id' => $organization->getKey(),
        'actor_id' => $owner->getKey(),
        'event' => AuditEvent::ContactCreated,
        'subject_type' => Organization::class,
        'subject_id' => $organization->getKey(),
        'changes' => ['type' => 'customer'],
    ]);

    (new DeleteOrganization($organization, $owner->getKey()))
        ->handle(app(FileStorage::class));

    expect(AuditLog::query()->where('organization_id', $organization->getKey())->count())->toBe(0)
        ->and(AuditLog::query()->where('event', AuditEvent::OrganizationDeleted)->count())->toBe(0);
});
