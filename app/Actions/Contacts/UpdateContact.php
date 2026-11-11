<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\Files\StoreImage;
use App\Enums\Gender;
use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class UpdateContact
{
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
    ): void {
        $contact = app(FindContact::class)->byId($tenant, $contactId);
        $stakeholder = $contact->stakeholder;

        abort_if($stakeholder === null, 404);

        $role = $this->role($data);
        $shared = $contact->isShared();
        $column = $stakeholder instanceof Organization ? 'logo_url' : 'photo_url';
        $previous = null;

        $newPhoto = ! $shared && $photo instanceof UploadedFile
            ? app(StoreImage::class)->handle($photo, 'photos')
            : null;

        try {
            DB::transaction(function () use ($tenant, $contact, $data, $role, &$shared, $stakeholder, $newPhoto, $removePhoto, $column, &$previous): void {
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
                    $attributes['gender'] = $this->gender($data['gender'] ?? null);
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
                        'gender' => $this->gender($data['contact_gender'] ?? null),
                        'summary' => $data['contact_summary'] ?? null,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            if ($newPhoto !== null) {
                app(StoreImage::class)->delete($newPhoto);
            }

            throw $exception;
        }

        if ($shared) {
            if ($newPhoto !== null) {
                app(StoreImage::class)->delete($newPhoto);
            }

            return;
        }

        if ($newPhoto !== null || $removePhoto) {
            app(StoreImage::class)->delete($previous);
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

    private function gender(mixed $gender): ?Gender
    {
        if ($gender instanceof Gender) {
            return $gender;
        }

        return $gender === null || $gender === '' ? null : Gender::from((string) $gender);
    }
}
