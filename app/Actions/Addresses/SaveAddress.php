<?php

declare(strict_types=1);

namespace App\Actions\Addresses;

use App\Models\Organization;
use App\Models\Person;

final class SaveAddress
{
    /**
     * Replace the entity's managed address, or remove it when the payload is null.
     *
     * Organizations keep one address; people may have many, but only the first
     * row is managed here for now.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function handle(Organization|Person $entity, ?array $data): void
    {
        $address = $entity instanceof Organization
            ? $entity->address()->first()
            : $entity->addresses()->first();

        if ($data === null) {
            $address?->delete();

            return;
        }

        if ($address !== null) {
            $address->update($data);

            return;
        }

        if ($entity instanceof Organization) {
            $entity->address()->create($data);

            return;
        }

        $entity->addresses()->create($data);
    }
}
