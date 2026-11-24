<?php

declare(strict_types=1);

namespace App\Actions\Organizations\Audit;

use App\Actions\AuditLogs\AuditPayload;
use App\Support\FieldLabel;

/**
 * Audit payload describing the changed organization fields.
 */
final class OrganizationUpdatedPayload extends AuditPayload
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(public array $fields, public bool $restored = false) {}

    public function toArray(): array
    {
        $data = ['fields' => $this->fields];

        if ($this->restored) {
            $data['restored'] = true;
        }

        return $data;
    }

    /**
     * Rebuild the payload from its stored representation.
     */
    public static function fromArray(array $data): static
    {
        $fields = $data['fields'] ?? [];

        return new self(
            \is_array($fields) ? array_values(array_map(fn (mixed $field): string => (string) $field, $fields)) : [],
            (bool) ($data['restored'] ?? false),
        );
    }

    /**
     * Describe the changed fields and whether the profile was restored.
     */
    public function details(): array
    {
        $details = [];

        if ($this->fields !== []) {
            $details[] = __('Fields').': '.implode(', ', array_map(
                fn (string $field): string => FieldLabel::label($field),
                $this->fields,
            ));
        }

        if ($this->restored) {
            $details[] = __('Restored');
        }

        return $details;
    }
}
