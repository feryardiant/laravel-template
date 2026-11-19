<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Enums\ContactDetailType;
use App\Models\Entity;
use Illuminate\Support\Str;

final class SaveContactDetails
{
    /**
     * Store the changed detail records, leaving omitted types untouched.
     *
     * @param  array<string, ?string>  $details  Detail type => value; null or blank deletes.
     * @return list<string> The detail types that changed.
     */
    public function handle(Entity $entity, array $details): array
    {
        if ($details === []) {
            return [];
        }

        // Serialize with a concurrent save so a first insert cannot race the unique index.
        $entity->newQuery()->withTrashed()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();

        $changed = [];

        foreach ($details as $type => $value) {
            $field = $this->save($entity, ContactDetailType::from($type), $value);

            if ($field !== null) {
                $changed[] = $field;
            }
        }

        return $changed;
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
