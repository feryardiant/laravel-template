<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\StoreImage;
use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class UpdateContact
{
    public function __construct(
        private StoreImage $storeImage,
        private FindContact $findContact,
    ) {
        // .
    }

    /**
     * Update a tenant contact.
     *
     * Per-tenant fields (code, role, dates) always change. Shared entity fields
     * are only written when the entity is not used by another tenant.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(
        Organization $tenant,
        int $contactId,
        array $data,
        ?UploadedFile $photo = null,
        bool $removePhoto = false,
        bool $validatedSharedFieldsLocked = false,
    ): void {
        $contact = $this->findContact->byId($tenant, $contactId);
        $stakeholder = $contact->stakeholder;

        abort_if($stakeholder === null, 404);

        $role = $this->role($data);
        $shared = $contact->isShared();
        $column = $stakeholder instanceof Organization ? 'logo_url' : 'photo_url';
        $previous = null;

        $newPhoto = ! $validatedSharedFieldsLocked && ! $shared && $photo instanceof UploadedFile
            ? $this->storeImage->handle($photo, 'photos')
            : null;

        try {
            DB::transaction(function () use ($tenant, $contact, $data, $role, &$shared, $stakeholder, $newPhoto, $removePhoto, $column, &$previous, $validatedSharedFieldsLocked): void {
                // Serialize with a concurrent reuse or credential change.
                $stakeholder = $stakeholder->newQuery()
                    ->whereKey($stakeholder->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $previousRaw = $stakeholder->getRawOriginal($column);
                $previous = \is_string($previousRaw) ? $previousRaw : null;

                if ($stakeholder instanceof Organization) {
                    $contactPerson = $stakeholder->primaryContact()->lockForUpdate()->first();
                    $stakeholder->setRelation('primaryContact', $contactPerson);
                }

                $contact->setRelation('stakeholder', $stakeholder);
                $shared = $contact->isShared();

                // The form skipped shared-field validation while the entity was
                // locked; if that changed meanwhile, reject instead of writing
                // values the request never validated.
                if ($validatedSharedFieldsLocked && ! $shared) {
                    throw ValidationException::withMessages([
                        'name' => __('The contact changed. Reopen it and try again.'),
                    ]);
                }

                OrganizationRelationship::query()
                    ->forOrganization($tenant)
                    ->external()
                    ->whereKey($contact->getKey())
                    ->update([
                        'code' => $data['code'] ?? null,
                        'type' => $role,
                        'start_date' => $data['start_date'] ?? null,
                        'finish_date' => $data['finish_date'] ?? null,
                    ]);

                if ($shared) {
                    return;
                }

                $attributes = [
                    'name' => $data['name'],
                    'summary' => $data['summary'] ?? null,
                ];

                if ($stakeholder instanceof Person) {
                    $attributes['gender'] = $data['gender'] ?? null;
                }

                if ($newPhoto !== null) {
                    $attributes[$column] = $newPhoto;
                } elseif ($removePhoto) {
                    $attributes[$column] = null;
                }

                $stakeholder->update($attributes);

                if ($stakeholder instanceof Organization) {
                    $stakeholder->primaryContact?->update([
                        'name' => $data['contact_name'],
                        'gender' => $data['contact_gender'] ?? null,
                        'summary' => $data['contact_summary'] ?? null,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            if ($newPhoto !== null) {
                $this->storeImage->delete($newPhoto);
            }

            throw $exception;
        }

        if ($shared) {
            if ($newPhoto !== null) {
                $this->storeImage->delete($newPhoto);
            }

            return;
        }

        if ($newPhoto !== null || $removePhoto) {
            $this->storeImage->delete($previous);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function role(array $data): OrganizationRelationshipType
    {
        $role = $data['role'] ?? null;
        $role = $role instanceof OrganizationRelationshipType
            ? $role
            : OrganizationRelationshipType::from((string) $role);

        if (! $role->isExternal()) {
            throw new InvalidArgumentException('Invalid contact role.');
        }

        return $role;
    }
}
