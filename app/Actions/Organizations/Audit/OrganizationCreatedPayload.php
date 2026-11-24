<?php

declare(strict_types=1);

namespace App\Actions\Organizations\Audit;

use App\Actions\AuditLogs\AuditPayload;
use App\Enums\SubsidiaryKind;

/**
 * Audit payload describing a created organization and its parent.
 */
final class OrganizationCreatedPayload extends AuditPayload
{
    public function __construct(public ?int $parentId, public ?SubsidiaryKind $kind) {}

    public function toArray(): array
    {
        return [
            'parent_id' => $this->parentId,
            'kind' => $this->kind?->value,
        ];
    }

    /**
     * Rebuild the payload from its stored representation.
     */
    public static function fromArray(array $data): static
    {
        $parentId = $data['parent_id'] ?? null;
        $kind = $data['kind'] ?? null;

        return new self(
            $parentId === null ? null : (int) $parentId,
            \is_string($kind) ? SubsidiaryKind::from($kind) : null,
        );
    }

    /**
     * Return no extra detail lines for a created organization.
     */
    public function details(): array
    {
        return [];
    }
}
