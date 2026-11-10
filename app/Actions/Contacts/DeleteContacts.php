<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\Files\StoreImage;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class DeleteContacts
{
    /**
     * Delete several tenant contacts in one transaction.
     *
     * @param  array<int, int|string>  $contactIds
     */
    public function handle(Organization $tenant, array $contactIds): int
    {
        $ids = array_values(array_unique(array_map('intval', $contactIds)));

        if ($ids === []) {
            return 0;
        }

        $paths = [];
        $deleted = 0;

        DB::transaction(function () use ($tenant, $ids, &$paths, &$deleted): void {
            foreach ($ids as $id) {
                $paths = array_merge(
                    $paths,
                    app(DeleteContact::class)->handle($tenant, $id, cleanupFiles: false),
                );

                $deleted++;
            }
        });

        foreach ($paths as $path) {
            app(StoreImage::class)->delete($path);
        }

        return $deleted;
    }
}
