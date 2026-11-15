<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Jobs\DeleteOrganization;
use App\Livewire\OrgSettings\General\RemoveOrganization;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('queues the organization removal from the danger zone', function () {
    Queue::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = actingAsMember($organization, Role::Owner);

    Livewire::test(RemoveOrganization::class)
        ->call('confirmRemove')
        ->assertSet('showModal', true)
        ->set('confirmation', 'Acme')
        ->call('remove')
        ->assertHasNoErrors()
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('Removal queued. :name will be deleted shortly.', ['name' => 'Acme']),
        )
        ->assertRedirect(route('organizations.index'));

    Queue::assertPushed(
        DeleteOrganization::class,
        fn (DeleteOrganization $job): bool => $job->organization->is($organization)
            && $job->actorId === $owner->getKey(),
    );

    expect(session(CurrentOrganization::SESSION_KEY))->toBeNull();
});

it('requires the exact organization name to remove it from the danger zone', function () {
    Queue::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);

    actingAsMember($organization, Role::Owner);

    Livewire::test(RemoveOrganization::class)
        ->call('confirmRemove')
        ->set('confirmation', 'acme')
        ->call('remove')
        ->assertHasErrors('confirmation');

    Queue::assertNothingPushed();
});

it('forbids admins from opening or removing the organization', function () {
    Queue::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(RemoveOrganization::class)
        ->call('confirmRemove')
        ->assertForbidden();

    Livewire::test(RemoveOrganization::class)
        ->call('remove')
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('forbids removing the root organization', function () {
    Queue::fake();

    $root = Organization::factory()->asRootTenant()->create();

    actingAsMember($root, Role::Owner);

    Livewire::test(RemoveOrganization::class)
        ->call('confirmRemove')
        ->assertForbidden();

    Livewire::test(RemoveOrganization::class)
        ->call('remove')
        ->assertForbidden();

    Queue::assertNothingPushed();
});
