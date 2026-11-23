<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Jobs\DeleteOrganization;
use App\Models\Organization;
use App\Models\User;

final class QueueOrganizationRemoval
{
    /**
     * Queue an organization for deletion.
     */
    public function handle(Organization $organization, User $actor): void
    {
        dispatch(new DeleteOrganization($organization, $actor->getKey()));
    }
}
