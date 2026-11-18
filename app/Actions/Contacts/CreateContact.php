<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\Addresses\SaveAddress;
use App\Actions\FileStorage;
use App\Actions\Relationships\RelationshipTimeline;
use App\Concerns\ToManageEntities;
use App\Enums\ContactDetailType;
use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipPeriodReason;
use App\Events\Contacts\ContactCreated;
use App\Events\Contacts\ContactRenewed;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateContact
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
     * Create a contact for the tenant.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $tenant, array $data, ?UploadedFile $photo = null, ?User $actor = null): Entity
    {
        $role = $this->ensureExternalRole($data['role'] ?? null);

        $reference = $data['reference'] ?? null;

        if (\is_string($reference) && $reference !== '') {
            return DB::transaction(function () use ($tenant, $reference, $role, $data, $actor): Entity {
                $stakeholder = $this->findContact->byReference($tenant, $reference);

                // Lock the shared entity so concurrent reuse cannot attach it twice.
                $stakeholder->newQuery()
                    ->whereKey($stakeholder->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = OrganizationRelationship::query()
                    ->forOrganization($tenant)
                    ->external()
                    ->whereStakeholder($stakeholder)
                    ->lockForUpdate()
                    ->first();

                // Active or scheduled rows still block reuse; an expired row is
                // renewed in place instead of creating a duplicate.
                if ($existing !== null) {
                    if ($existing->finish_date?->lt(today()) !== true) {
                        throw ValidationException::withMessages([
                            'name' => __('This contact is already in your list.'),
                        ]);
                    }

                    $this->timeline->close($existing, RelationshipPeriodReason::Renewed, $actor);

                    $existing->update([
                        'code' => $data['code'] ?? null,
                        'type' => $role,
                        'start_date' => $data['start_date'] ?? null,
                        'finish_date' => null,
                    ]);

                    $period = $this->timeline->open($existing->refresh());

                    event(new ContactRenewed($tenant, $actor, $stakeholder, $this->contactAuditChanges($stakeholder, $role, $data), $period));

                    return $stakeholder;
                }

                $period = $this->attach($tenant, $stakeholder, $role, $data);

                event(new ContactCreated($tenant, $actor, $stakeholder, $this->contactAuditChanges($stakeholder, $role, $data), $period));

                return $stakeholder;
            });
        }

        $photoPath = $photo instanceof UploadedFile
            ? $this->fileStorage->handle($photo, 'photos')
            : null;

        try {
            return DB::transaction(function () use ($tenant, $data, $role, $photoPath, $actor): Entity {
                $stakeholder = ($data['type'] ?? 'individual') === 'organization'
                    ? $this->createOrganization(
                        name: $data['name'],
                        user: $data['user'] ?? null,
                        contactName: $data['contact_name'],
                        contactGender: $data['contact_gender'] ?? null,
                        contactSummary: $data['contact_summary'] ?? null,
                        summary: $data['summary'] ?? null,
                        logoPath: $photoPath,
                        owner: $tenant,
                    )
                    : $this->createIndividual(
                        name: $data['name'],
                        user: $data['user'] ?? null,
                        gender: $data['gender'] ?? null,
                        summary: $data['summary'] ?? null,
                        photoPath: $photoPath,
                        owner: $tenant,
                    );

                $this->saveAddress->handle($stakeholder, $data['address'] ?? null);

                // Contact records only; never pass these to createIndividual(),
                // whose $email argument creates a login user.
                $this->saveContactDetails->handle($stakeholder, [
                    ContactDetailType::Email->value => $data['contact_email'] ?? null,
                    ContactDetailType::Phone->value => $data['contact_phone'] ?? null,
                ]);

                if ($stakeholder instanceof Organization && $stakeholder->primaryContact !== null) {
                    $this->saveContactDetails->handle($stakeholder->primaryContact, [
                        ContactDetailType::Email->value => $data['contact_person_email'] ?? null,
                        ContactDetailType::Phone->value => $data['contact_person_phone'] ?? null,
                    ]);
                }

                $period = $this->attach($tenant, $stakeholder, $role, $data);

                event(new ContactCreated($tenant, $actor, $stakeholder, $this->contactAuditChanges($stakeholder, $role, $data), $period));

                return $stakeholder;
            });
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
                $this->fileStorage->delete($photoPath);
            }

            throw $exception;
        }
    }

    /**
     * Build the safe audit payload for a contact event.
     *
     * The free-form code is recorded as a changed field, not as a value.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function contactAuditChanges(Organization|Person $stakeholder, OrganizationRelationshipType $type, array $data): array
    {
        $changes = [
            'kind' => $stakeholder instanceof Organization ? 'organization' : 'individual',
            'type' => $type->value,
        ];

        if (filled($data['code'] ?? null)) {
            $changes['fields'] = ['code'];
        }

        return $changes;
    }

    /**
     * Attach an existing or new stakeholder to the tenant.
     *
     * @param  array<string, mixed>  $data
     */
    private function attach(Organization $tenant, Organization|Person $stakeholder, OrganizationRelationshipType $role, array $data): OrganizationRelationshipPeriod
    {
        OrganizationRelationship::create([
            'organization_id' => $tenant->getKey(),
            'stakeholder_type' => $stakeholder->getMorphClass(),
            'stakeholder_id' => $stakeholder->getKey(),
            'code' => $data['code'] ?? null,
            'type' => $role,
            'start_date' => $data['start_date'] ?? null,
            'finish_date' => $data['finish_date'] ?? null,
        ]);

        $relationship = OrganizationRelationship::query()
            ->forOrganization($tenant)
            ->external()
            ->whereStakeholder($stakeholder)
            ->latest('id')
            ->firstOrFail();

        return $this->timeline->open($relationship);
    }
}
