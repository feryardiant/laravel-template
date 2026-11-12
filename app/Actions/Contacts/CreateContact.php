<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\FileStorage;
use App\Concerns\ToManageEntities;
use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateContact
{
    use ToManageEntities;

    public function __construct(
        private FileStorage $fileStorage,
        private FindContact $findContact,
    ) {
        // .
    }

    /**
     * Create a contact for the tenant.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $tenant, array $data, ?UploadedFile $photo = null): void
    {
        $role = $this->ensureExternalRole($data['role'] ?? null);

        $reference = $data['reference'] ?? null;

        if (\is_string($reference) && $reference !== '') {
            DB::transaction(function () use ($tenant, $reference, $role, $data): void {
                $stakeholder = $this->findContact->byReference($tenant, $reference);

                // Lock the shared entity so concurrent reuse cannot attach it twice.
                $stakeholder->newQuery()
                    ->whereKey($stakeholder->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertNotAlreadyContact($tenant, $stakeholder);
                $this->attach($tenant, $stakeholder, $role, $data);
            });

            return;
        }

        $photoPath = $photo instanceof UploadedFile
            ? $this->fileStorage->handle($photo, 'photos')
            : null;

        try {
            DB::transaction(function () use ($tenant, $data, $role, $photoPath): void {
                $stakeholder = ($data['type'] ?? 'individual') === 'organization'
                    ? $this->createOrganization(
                        name: $data['name'],
                        contactName: $data['contact_name'],
                        contactGender: $data['contact_gender'] ?? null,
                        contactSummary: $data['contact_summary'] ?? null,
                        summary: $data['summary'] ?? null,
                        logoPath: $photoPath,
                    )
                    : $this->createIndividual(
                        name: $data['name'],
                        gender: $data['gender'] ?? null,
                        summary: $data['summary'] ?? null,
                        photoPath: $photoPath,
                    );

                $this->attach($tenant, $stakeholder, $role, $data);
            });
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
                $this->fileStorage->delete($photoPath);
            }

            throw $exception;
        }
    }

    /**
     * Attach an existing or new stakeholder to the tenant.
     *
     * @param  array<string, mixed>  $data
     */
    private function attach(Organization $tenant, Organization|Person $stakeholder, OrganizationRelationshipType $role, array $data): void
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
    }

    private function assertNotAlreadyContact(Organization $tenant, Organization|Person $stakeholder): void
    {
        $exists = OrganizationRelationship::query()
            ->forOrganization($tenant)
            ->external()
            ->where('stakeholder_type', $stakeholder->getMorphClass())
            ->where('stakeholder_id', $stakeholder->getKey())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => __('This contact is already in your list.'),
            ]);
        }
    }
}
