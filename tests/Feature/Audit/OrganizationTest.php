<?php

declare(strict_types=1);

use App\Actions\Audit\RecordAudit;
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

test('deleting an organization records the actor and subject', function () {
    Notification::fake();

    $root = Organization::factory()->asRootTenant()->create(['name' => 'Root']);
    $owner = actingAsMember($root, Role::Owner);

    $organization = Organization::factory()->asTenantOf($root)->create(['name' => 'Branch']);
    $root->subsidiaries()->attach($organization, ['type' => OrganizationRelationshipType::Subsidiary]);

    (new DeleteOrganization($organization, $owner->getKey()))
        ->handle(app(FileStorage::class), app(RecordAudit::class));

    $log = AuditLog::query()->where('event', AuditEvent::OrganizationDeleted)->sole();

    expect($log->actor_id)->toBe($owner->getKey())
        ->and($log->subject_id)->toBe($organization->getKey())
        ->and($log->subject_type)->toBe(Organization::class);
});
