<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Enums\OrganizationRelationshipType;
use InvalidArgumentException;

final class EnsureExternalRole
{
    /**
     * Resolve a relationship role and reject internal roles.
     */
    public function handle(OrganizationRelationshipType|string|null $role): OrganizationRelationshipType
    {
        $role = $role instanceof OrganizationRelationshipType
            ? $role
            : OrganizationRelationshipType::from((string) $role);

        if (! $role->isExternal()) {
            throw new InvalidArgumentException('Invalid contact role.');
        }

        return $role;
    }
}
