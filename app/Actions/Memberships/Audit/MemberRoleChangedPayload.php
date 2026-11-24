<?php

declare(strict_types=1);

namespace App\Actions\Memberships\Audit;

use App\Actions\AuditLogs\AuditPayload;
use App\Enums\Role;
use App\Models\Person;

/**
 * Audit payload for a member's role change.
 *
 * @property Person $subject
 */
final class MemberRoleChangedPayload extends AuditPayload
{
    public function __construct(public Role $from, public Role $to) {}

    public function __toString(): string
    {
        return __('Role of :member changed', ['member' => $this->subjectTitle()]);
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }

    /**
     * Rebuild the payload from its stored representation.
     */
    public static function fromArray(array $data): static
    {
        return new self(
            Role::from((int) $data['from']),
            Role::from((int) $data['to']),
        );
    }

    /**
     * Describe the previous and new role.
     */
    public function details(): array
    {
        return [$this->from->label().' → '.$this->to->label()];
    }
}
