<?php

declare(strict_types=1);

namespace App\Actions\Memberships\Audit;

use App\Actions\Audit\AuditPayload;
use App\Enums\Role;

final class MemberAddedPayload extends AuditPayload
{
    public function __construct(public Role $role) {}

    public function toArray(): array
    {
        return ['role' => $this->role->value];
    }

    public static function fromArray(array $data): static
    {
        return new self(Role::from((int) $data['role']));
    }

    public function details(): array
    {
        return [$this->role->label()];
    }
}
