<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\FileStorage;
use App\Concerns\ToManageEntities;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class UpdateContact
{
    use ToManageEntities;

    public function __construct(
        private FileStorage $fileStorage,
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

        $role = $this->ensureExternalRole($data['role'] ?? null);
        $locked = $contact->profileLockedFor($tenant);
        $column = $stakeholder instanceof Organization ? 'logo_url' : 'photo_url';
        $previous = null;

        $newPhoto = ! $validatedSharedFieldsLocked && ! $locked && $photo instanceof UploadedFile
            ? $this->fileStorage->handle($photo, 'photos')
            : null;

        try {
            DB::transaction(function () use ($tenant, $contact, $data, $role, &$locked, $stakeholder, $newPhoto, $removePhoto, $column, &$previous, $validatedSharedFieldsLocked): void {
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
                $locked = $contact->profileLockedFor($tenant);

                // The form skipped shared-field validation while the entity was
                // locked; if that changed meanwhile, reject instead of writing
                // values the request never validated.
                if ($validatedSharedFieldsLocked && ! $locked) {
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

                if ($locked) {
                    return;
                }

                $attributes = [
                    'name' => $data['name'],
                    'summary' => $data['summary'] ?? null,
                ];

                if ($stakeholder instanceof Person && \array_key_exists('gender', $data)) {
                    $attributes['gender'] = $data['gender'];
                }

                if ($newPhoto !== null) {
                    $attributes[$column] = $newPhoto;
                } elseif ($removePhoto) {
                    $attributes[$column] = null;
                }

                $stakeholder->update($attributes);

                if ($stakeholder instanceof Organization) {
                    $contactAttributes = [
                        'name' => $data['contact_name'],
                        'summary' => $data['contact_summary'] ?? null,
                    ];

                    if (\array_key_exists('contact_gender', $data)) {
                        $contactAttributes['gender'] = $data['contact_gender'];
                    }

                    $stakeholder->primaryContact?->update($contactAttributes);
                }
            });
        } catch (Throwable $exception) {
            if ($newPhoto !== null) {
                $this->fileStorage->delete($newPhoto);
            }

            throw $exception;
        }

        if ($locked) {
            if ($newPhoto !== null) {
                $this->fileStorage->delete($newPhoto);
            }

            return;
        }

        if ($newPhoto !== null || $removePhoto) {
            $this->fileStorage->delete($previous);
        }
    }
}
