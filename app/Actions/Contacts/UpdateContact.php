<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\Addresses\SaveAddress;
use App\Actions\FileStorage;
use App\Actions\Relationships\RelationshipTimeline;
use App\Concerns\ToManageEntities;
use App\Enums\ContactDetailType;
use App\Enums\OrganizationRelationshipType;
use App\Events\Contacts\ContactUpdated;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

final class UpdateContact
{
    use ToManageEntities;

    public function __construct(
        private SaveAddress $saveAddress,
        private FileStorage $fileStorage,
        private FindContact $findContact,
        private RelationshipTimeline $timeline,
        private SaveContactDetails $saveContactDetails,
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
        ?User $actor = null,
    ): void {
        $contact = $this->findContact->byId($tenant, $contactId);
        $stakeholder = $contact->stakeholder;

        abort_if($stakeholder === null, 404);

        $role = \array_key_exists('role', $data)
            ? $this->ensureExternalRole($data['role'])
            : ($contact->type instanceof OrganizationRelationshipType ? $contact->type : OrganizationRelationshipType::Customer);
        $locked = $contact->profileLockedFor($tenant);
        $column = $stakeholder instanceof Organization ? 'logo_url' : 'photo_url';
        $previous = null;

        $newPhoto = ! $validatedSharedFieldsLocked && ! $locked && $photo instanceof UploadedFile
            ? $this->fileStorage->handle($photo, 'photos')
            : null;

        try {
            DB::transaction(function () use ($tenant, $contact, $data, $role, &$locked, $stakeholder, $newPhoto, $removePhoto, $column, &$previous, $validatedSharedFieldsLocked, $actor): void {
                // Serialize with a concurrent reuse or credential change.
                $stakeholder = $stakeholder->newQuery()
                    ->whereKey($stakeholder->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $contact = $contact->newQuery()
                    ->whereKey($contact->getKey())
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

                $linkBefore = $contact->getRawOriginal();

                $startDate = \array_key_exists('start_date', $data)
                    ? $data['start_date']
                    : $contact->start_date?->toDateString();

                $finishDate = \array_key_exists('finish_date', $data)
                    ? $data['finish_date']
                    : $contact->finish_date?->toDateString();

                if ($finishDate !== null) {
                    Validator::make(
                        ['start_date' => $startDate, 'finish_date' => $finishDate],
                        [
                            'start_date' => ['required', 'date'],
                            'finish_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                        ],
                    )->validate();
                }

                $attributes = [];

                if (\array_key_exists('role', $data)) {
                    $attributes['type'] = $role;
                }

                foreach (['code', 'start_date', 'finish_date'] as $field) {
                    if (\array_key_exists($field, $data)) {
                        $attributes[$field] = $data[$field];
                    }
                }

                if ($attributes !== []) {
                    OrganizationRelationship::query()
                        ->forOrganization($tenant)
                        ->external()
                        ->whereKey($contact->getKey())
                        ->update($attributes);
                }

                $contact->refresh();
                $period = $this->timeline->sync($contact);

                $fields = [];

                foreach (['code', 'type', 'start_date', 'finish_date'] as $field) {
                    if (($linkBefore[$field] ?? null) !== $contact->getRawOriginal($field)) {
                        $fields[] = $field;
                    }
                }

                $details = $this->saveContactDetailsFor($stakeholder, $data);
                $fields = [...$fields, ...$details];

                if ($locked) {
                    $this->recordUpdate($tenant, $actor, $stakeholder, $fields, $period);

                    return;
                }

                $attributes = [];

                if (\array_key_exists('name', $data)) {
                    $attributes['name'] = $data['name'];
                }

                if (\array_key_exists('summary', $data)) {
                    $attributes['summary'] = $data['summary'];
                }

                if ($stakeholder instanceof Person && \array_key_exists('gender', $data)) {
                    $attributes['gender'] = $data['gender'];
                }

                if ($newPhoto !== null) {
                    $attributes[$column] = $newPhoto;
                } elseif ($removePhoto) {
                    $attributes[$column] = null;
                }

                if ($attributes !== []) {
                    $stakeholder->update($attributes);
                }

                if (\array_key_exists('address', $data)) {
                    $this->saveAddress->handle($stakeholder, $data['address']);
                }

                $fields = [
                    ...$fields,
                    ...array_keys(array_intersect_key(
                        $stakeholder->getChanges(),
                        array_flip(['name', 'summary', 'gender', 'photo_url', 'logo_url']),
                    )),
                ];

                if ($stakeholder instanceof Organization) {
                    $contactAttributes = [];

                    foreach (['contact_name' => 'name', 'contact_summary' => 'summary', 'contact_gender' => 'gender'] as $key => $attribute) {
                        if (\array_key_exists($key, $data)) {
                            $contactAttributes[$attribute] = $data[$key];
                        }
                    }

                    $contactPerson = $stakeholder->primaryContact;

                    if ($contactAttributes !== []) {
                        $contactPerson?->update($contactAttributes);
                    }

                    foreach (array_keys($contactPerson?->getChanges() ?? []) as $field) {
                        if (\in_array($field, ['name', 'summary', 'gender'], true)) {
                            $fields[] = 'contact_'.$field;
                        }
                    }
                }

                $this->recordUpdate($tenant, $actor, $stakeholder, $fields, $period);
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

    /**
     * Save the entity and contact person communication records.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function saveContactDetailsFor(Organization|Person $stakeholder, array $data): array
    {
        $fields = $this->saveDetails($stakeholder, $data, [
            'contact_email' => ContactDetailType::Email,
            'contact_phone' => ContactDetailType::Phone,
        ]);

        $contactPerson = $stakeholder instanceof Organization ? $stakeholder->primaryContact : null;

        if ($contactPerson === null) {
            return $fields;
        }

        return [
            ...$fields,
            ...array_map(
                fn (string $field): string => 'contact_'.$field,
                $this->saveDetails($contactPerson, $data, [
                    'contact_person_email' => ContactDetailType::Email,
                    'contact_person_phone' => ContactDetailType::Phone,
                ]),
            ),
        ];
    }

    /**
     * Write only the detail keys the form submitted.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, ContactDetailType>  $map
     * @return list<string>
     */
    private function saveDetails(Entity $entity, array $data, array $map): array
    {
        $details = [];

        foreach ($map as $key => $type) {
            if (\array_key_exists($key, $data)) {
                $details[$type->value] = $data[$key];
            }
        }

        return $this->saveContactDetails->handle($entity, $details);
    }

    /**
     * @param  list<string>  $fields
     */
    private function recordUpdate(
        Organization $tenant,
        ?User $actor,
        Entity $stakeholder,
        array $fields,
        OrganizationRelationshipPeriod $period,
    ): void {
        $fields = array_values(array_unique($fields));

        if ($fields === []) {
            return;
        }

        event(new ContactUpdated($tenant, $actor, $stakeholder, [
            'fields' => $fields,
        ], $period));
    }
}
