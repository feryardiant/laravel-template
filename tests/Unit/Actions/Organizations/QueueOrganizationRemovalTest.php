<?php

declare(strict_types=1);

use App\Actions\Organizations\QueueOrganizationRemoval;
use App\Enums\Role;
use App\Jobs\DeleteOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('queues the organization deletion with the actor', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    $actor = User::factory()->asMemberOf($organization, Role::Owner)->create();

    app(QueueOrganizationRemoval::class)->handle($organization, $actor);

    Queue::assertPushed(
        DeleteOrganization::class,
        fn (DeleteOrganization $job): bool => $job->organization->is($organization)
            && $job->actorId === $actor->getKey(),
    );
});
