<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\Files\StoreImage;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use Illuminate\Support\Facades\DB;

class DeleteContact
{
    /**
     * Delete a tenant contact.
     *
     * The relationship is always detached. The entity (and an organization's
     * contact person) is soft-deleted only when it is not shared with another
     * tenant. Store paths are deleted after the transaction commits.
     *
     * @return list<string>
     */
    public function handle(Organization $tenant, int $contactId, bool $cleanupFiles = true): array
    {
        $contact = app(FindContact::class)->byId($tenant, $contactId);
        $stakeholder = $contact->stakeholder;

        abort_if($stakeholder === null, 404);

        $paths = [];

        DB::transaction(function () use ($tenant, $contact, $stakeholder, &$paths): void {
            // Serialize with a concurrent reuse or credential change.
            $stakeholder = $stakeholder->newQuery()
                ->whereKey($stakeholder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($stakeholder instanceof Organization) {
                $contactPerson = $stakeholder->primaryContact()->lockForUpdate()->first();
                $stakeholder->setRelation('primaryContact', $contactPerson);
            }

            $contact->setRelation('stakeholder', $stakeholder);
            $removeOwned = ! $contact->isShared();

            if ($removeOwned) {
                $paths = $stakeholder instanceof Organization
                    ? [
                        $stakeholder->getRawOriginal('logo_url'),
                        $stakeholder->primaryContact?->getRawOriginal('photo_url'),
                    ]
                    : [$stakeholder->getRawOriginal('photo_url')];
            }

            OrganizationRelationship::query()
                ->forOrganization($tenant)
                ->external()
                ->whereKey($contact->getKey())
                ->delete();

            if (! $removeOwned) {
                return;
            }

            if ($stakeholder instanceof Organization) {
                $contactPerson = $stakeholder->primaryContact;

                $stakeholder->delete();
                $contactPerson?->delete();

                return;
            }

            $stakeholder->delete();
        });

        $paths = array_values(array_filter($paths));

        if ($cleanupFiles) {
            foreach ($paths as $path) {
                app(StoreImage::class)->delete($path);
            }
        }

        return $paths;
    }
}
