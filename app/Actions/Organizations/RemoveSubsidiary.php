<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Models\Organization;

final class RemoveSubsidiary
{
    public function __construct(
        private ListSubsidiaries $listSubsidiaries,
    ) {
        // .
    }

    /**
     * Resolve a subsidiary and the number of rows it would remove.
     *
     * @return array{organization: Organization, count: int}|null
     */
    public function preview(Organization $root, int $organizationId): ?array
    {
        if (! $root->isRootTenant()) {
            return null;
        }

        $rows = $this->listSubsidiaries->handle($root);
        $index = $rows->search(
            fn (array $row): bool => $row['organization']->getKey() === $organizationId,
        );

        if ($index === false) {
            return null;
        }

        $row = $rows->get($index);

        if ($row === null) {
            return null;
        }

        $count = 1;

        for ($position = $index + 1; $position < $rows->count(); $position++) {
            if ($rows[$position]['depth'] <= $row['depth']) {
                break;
            }

            $count++;
        }

        return ['organization' => $row['organization'], 'count' => $count];
    }
}
