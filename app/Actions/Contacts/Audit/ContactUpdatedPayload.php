<?php

declare(strict_types=1);

namespace App\Actions\Contacts\Audit;

use App\Actions\AuditLogs\AuditPayload;
use App\Support\FieldLabel;

final class ContactUpdatedPayload extends AuditPayload
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(public array $fields) {}

    public function toArray(): array
    {
        return ['fields' => $this->fields];
    }

    public static function fromArray(array $data): static
    {
        $fields = $data['fields'] ?? [];

        return new self(
            \is_array($fields) ? array_values(array_map(fn (mixed $field): string => (string) $field, $fields)) : [],
        );
    }

    public function details(): array
    {
        return $this->fields === [] ? [] : [__('Fields').': '.implode(', ', array_map(
            fn (string $field): string => FieldLabel::label($field),
            $this->fields,
        ))];
    }
}
