<?php

declare(strict_types=1);

namespace App\Actions\Memberships\Audit;

use App\Actions\AuditLogs\AuditPayload;
use App\Enums\Role;

/**
 * Audit payload describing a member added to an organization.
 */
final class MemberAddedPayload extends AuditPayload
{
    public function __construct(public Role $role) {}

    public function __toString(): string
    {
        return __(':role added', ['role' => $this->role->label()]);
    }

    public function toArray(): array
    {
        return ['role' => $this->role->value];
    }

    /**
     * Rebuild the payload from its stored representation.
     */
    public static function fromArray(array $data): static
    {
        return new self(Role::from((int) $data['role']));
    }

    /**
     * Describe the role the member received.
     */
    public function details(): array
    {
        return [$this->role->label()];
    }
}
