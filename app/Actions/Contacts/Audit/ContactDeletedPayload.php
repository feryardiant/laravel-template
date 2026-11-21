<?php

declare(strict_types=1);

namespace App\Actions\Contacts\Audit;

use App\Actions\Audit\AuditPayload;

final class ContactDeletedPayload extends AuditPayload
{
    public function __construct(public bool $detachedOnly) {}

    public function toArray(): array
    {
        return ['detached_only' => $this->detachedOnly];
    }

    public static function fromArray(array $data): static
    {
        return new self((bool) $data['detached_only']);
    }

    public function details(): array
    {
        return $this->detachedOnly ? [__('Entity kept')] : [];
    }
}
