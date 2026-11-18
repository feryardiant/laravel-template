<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Enums\ContactDetailType;
use App\Models\Entity;
use Illuminate\Support\Str;

final class SaveContactDetails
{
    /**
     * Store the entity's email and phone records.
     *
     * @return list<string> The detail types that changed.
     */
    public function handle(Entity $entity, ?string $email, ?string $phone): array
    {
        return array_values(array_filter([
            $this->save($entity, ContactDetailType::Email, $email),
            $this->save($entity, ContactDetailType::Phone, $phone),
        ]));
    }

    /**
     * Upsert or delete a single detail type.
     */
    private function save(Entity $entity, ContactDetailType $type, ?string $value): ?string
    {
        $value = $this->normalize($type, $value);
        $existing = $entity->contactDetails()->where('type', $type->value)->first();

        if ($value === null) {
            $existing?->delete();

            return $existing !== null ? $type->value : null;
        }

        if ($existing !== null) {
            if ($existing->value === $value) {
                return null;
            }

            $existing->update(['value' => $value]);

            return $type->value;
        }

        $entity->contactDetails()->create(['type' => $type, 'value' => $value]);

        return $type->value;
    }

    private function normalize(ContactDetailType $type, ?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $type === ContactDetailType::Email ? Str::lower($value) : $value;
    }
}
