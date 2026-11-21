<?php

declare(strict_types=1);

namespace App\Actions\Memberships\Audit;

use App\Actions\Audit\AuditPayload;
use App\Enums\Role;
use App\Models\Person;

/**
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

    public static function fromArray(array $data): static
    {
        return new self(
            Role::from((int) $data['from']),
            Role::from((int) $data['to']),
        );
    }

    public function details(): array
    {
        return [$this->from->label().' → '.$this->to->label()];
    }
}
