<?php

declare(strict_types=1);

namespace App\Actions\Contacts\Audit;

use App\Actions\AuditLogs\AuditPayload;

/**
 * Audit payload describing a deleted contact and the fate of its entity.
 */
final class ContactDeletedPayload extends AuditPayload
{
    public function __construct(public bool $detachedOnly) {}

    public function toArray(): array
    {
        return ['detached_only' => $this->detachedOnly];
    }

    /**
     * Rebuild the payload from its stored representation.
     */
    public static function fromArray(array $data): static
    {
        return new self((bool) $data['detached_only']);
    }

    /**
     * Note whether the entity was kept after detaching.
     */
    public function details(): array
    {
        return $this->detachedOnly ? [__('Entity kept')] : [];
    }
}
