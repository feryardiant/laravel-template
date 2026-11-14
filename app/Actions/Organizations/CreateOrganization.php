<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\FileStorage;
use App\Actions\Memberships\CreateMember;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class CreateOrganization
{
    public function __construct(
        private CreateMember $membership,
        private FileStorage $fileStorage,
    ) {
        // .
    }

    /**
     * Create an organization owned by the actor, optionally under a parent.
     */
    public function handle(
        User $actor,
        string $name,
        ?Organization $parent = null,
        ?UploadedFile $logo = null,
        ?SubsidiaryKind $kind = null,
    ): Organization {
        $person = $actor->person;

        if ($person === null) {
            throw new RuntimeException('The user has no person profile.');
        }

        $root = Organization::query()->rootTenant()->first();

        if ($root === null) {
            throw new RuntimeException('The root tenant has not been provisioned.');
        }

        $logoPath = $logo instanceof UploadedFile ? $this->fileStorage->handle($logo, 'logos') : null;

        try {
            return DB::transaction(function () use ($person, $name, $parent, $logoPath, $root, $kind): Organization {
                $organization = Organization::create([
                    'contact_id' => $person->getKey(),
                    'name' => $name,
                    'is_tenant' => true,
                    'root_organization_id' => $root->getKey(),
                    'logo_url' => $logoPath,
                ]);

                $organization->update(['owner_organization_id' => $organization->getKey()]);

                $this->membership->attach(
                    $organization,
                    $person,
                    startDate: now(),
                    role: Role::Owner,
                );

                if ($parent !== null) {
                    $parent->subsidiaries()->attach($organization, [
                        'type' => OrganizationRelationshipType::Subsidiary,
                        'kind' => $kind ?? SubsidiaryKind::Subsidiary,
                        'start_date' => now(),
                    ]);
                }

                return $organization;
            });
        } catch (Throwable $exception) {
            if ($logoPath !== null) {
                $this->fileStorage->delete($logoPath);
            }

            throw $exception;
        }
    }
}
