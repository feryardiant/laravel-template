<?php

declare(strict_types=1);

namespace App\Actions\Contacts\Audit;

use App\Actions\AuditLogs\AuditPayload;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Models\Entity;
use App\Support\FieldLabel;
use UnexpectedValueException;

/**
 * @property Entity $subject
 */
final class ContactRelationPayload extends AuditPayload
{
    /**
     * @param  'individual'|'organization'  $kind
     * @param  list<string>  $fields
     */
    public function __construct(
        public string $kind,
        public OrganizationRelationshipType $type,
        public array $fields = [],
    ) {}

    public function __toString(): string
    {
        return match ($this->event) {
            AuditEvent::ContactCreated => __(':type added', ['type' => $this->type->label()]),
            AuditEvent::ContactRenewed => __(':type renewed', ['type' => $this->type->label()]),
            default => parent::__toString(),
        };
    }

    public function toArray(): array
    {
        $data = [
            'kind' => $this->kind,
            'type' => $this->type->value,
        ];

        if ($this->fields !== []) {
            $data['fields'] = $this->fields;
        }

        return $data;
    }

    public static function fromArray(array $data): static
    {
        $kind = (string) ($data['kind'] ?? '');
        $fields = $data['fields'] ?? [];

        return new self(
            match ($kind) {
                'individual', 'organization' => $kind,
                default => throw new UnexpectedValueException('Unknown contact kind: '.$kind),
            },
            OrganizationRelationshipType::from((string) $data['type']),
            \is_array($fields) ? array_values(array_map(fn (mixed $field): string => (string) $field, $fields)) : [],
        );
    }

    public function details(): array
    {
        $details = [$this->type->label()];

        if ($this->fields !== []) {
            $details[] = __('Fields').': '.implode(', ', array_map(
                fn (string $field): string => FieldLabel::label($field),
                $this->fields,
            ));
        }

        return $details;
    }
}
