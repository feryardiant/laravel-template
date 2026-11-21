<?php

declare(strict_types=1);

namespace App\Actions\Organizations\Audit;

use App\Actions\Audit\AuditPayload;
use App\Enums\SubsidiaryKind;

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

    public static function fromArray(array $data): static
    {
        $parentId = $data['parent_id'] ?? null;
        $kind = $data['kind'] ?? null;

        return new self(
            $parentId === null ? null : (int) $parentId,
            \is_string($kind) ? SubsidiaryKind::from($kind) : null,
        );
    }

    public function details(): array
    {
        return [];
    }
}
