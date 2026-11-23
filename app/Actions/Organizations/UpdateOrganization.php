<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Addresses\SaveAddress;
use App\Actions\Contacts\SaveContactDetails;
use App\Actions\FileStorage;
use App\Events\Organizations\OrganizationUpdated;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateOrganization
{
    public function __construct(
        private SaveAddress $saveAddress,
        private SaveContactDetails $saveContactDetails,
        private FileStorage $fileStorage,
    ) {
        // .
    }

    /**
     * Update the organization profile, logo, address, and contact details.
     *
     * @param  array{
     *     name: string,
     *     summary: string,
     *     address: array<string, string|null>|null,
     *     details: array<string, string>,
     *     remove_logo: bool
     * }  $data
     * @param  array{
     *     name: string,
     *     summary: string,
     *     address: array<string, string|null>|null,
     *     details: array<string, string>,
     *     logo_url: ?string
     * }  $expected
     * @return array{organization: Organization, logo_url: ?string, logo_removal_failed: bool}
     */
    public function handle(
        Organization $organization,
        User $actor,
        array $data,
        array $expected,
        ?UploadedFile $logo = null,
    ): array {
        $previousLogo = $organization->getRawOriginal('logo_url');
        $newLogoPath = $logo instanceof UploadedFile ? $this->fileStorage->handle($logo, 'logos') : null;
        $removeLogo = $newLogoPath === null && $data['remove_logo'];

        try {
            DB::transaction(function () use ($organization, $actor, $data, $expected, $newLogoPath, $removeLogo, $previousLogo): void {
                if ($data['name'] !== $expected['name']) {
                    $organization->name = $data['name'];
                }

                if ($data['summary'] !== $expected['summary']) {
                    $organization->summary = $data['summary'] !== '' ? $data['summary'] : null;
                }

                if ($newLogoPath !== null) {
                    $organization->logo_url = $newLogoPath;
                } elseif ($removeLogo) {
                    $organization->logo_url = null;
                }

                $organization->save();

                if ($data['address'] !== $expected['address']) {
                    $this->saveAddress->handle($organization, $data['address']);
                }

                $details = $this->saveContactDetails->handle($organization, $data['details']);

                $fields = [
                    ...array_values(array_diff(array_keys($organization->getChanges()), ['updated_at'])),
                    ...$details,
                ];

                if ($fields !== []) {
                    event(new OrganizationUpdated($organization, $actor, $fields));
                }

                if ($newLogoPath !== null) {
                    // Replacement cleanup runs after commit; a failure is logged.
                    DB::afterCommit(fn () => $this->fileStorage->delete($previousLogo));
                }
            });
        } catch (Throwable $exception) {
            if ($newLogoPath !== null) {
                $storedLogo = Organization::withTrashed()
                    ->whereKey($organization->getKey())
                    ->value('logo_url');

                if ($storedLogo !== $newLogoPath) {
                    $this->fileStorage->delete($newLogoPath);
                }
            }

            $organization->refresh();

            throw $exception;
        }

        $organization->refresh();

        $logoUrl = $newLogoPath ?? ($removeLogo ? null : $previousLogo);
        $logoRemovalFailed = false;

        if ($removeLogo && ! $this->fileStorage->delete($previousLogo)) {
            $restored = Organization::query()
                ->whereKey($organization->getKey())
                ->whereNull('logo_url')
                ->update(['logo_url' => $previousLogo]);

            if ($restored === 0) {
                $organization->refresh();
                $logoUrl = $organization->getRawOriginal('logo_url');
            } else {
                $organization->refresh();
                $logoUrl = $previousLogo;

                event(new OrganizationUpdated($organization, $actor, ['logo_url'], true));
            }

            $logoRemovalFailed = true;
        }

        return [
            'organization' => $organization,
            'logo_url' => $logoUrl,
            'logo_removal_failed' => $logoRemovalFailed,
        ];
    }
}
