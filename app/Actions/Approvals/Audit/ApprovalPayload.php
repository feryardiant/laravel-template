<?php

declare(strict_types=1);

namespace App\Actions\Approvals\Audit;

use App\Actions\Audit\AuditPayload;
use App\Enums\ApprovalRequestType;

final class ApprovalPayload extends AuditPayload
{
    public function __construct(public ApprovalRequestType $type, public ?string $reason = null) {}

    public function toArray(): array
    {
        $data = ['type' => $this->type->value];

        if ($this->reason !== null && $this->reason !== '') {
            $data['reason'] = $this->reason;
        }

        return $data;
    }

    public static function fromArray(array $data): static
    {
        $reason = $data['reason'] ?? null;

        return new self(
            ApprovalRequestType::from((string) $data['type']),
            \is_string($reason) ? $reason : null,
        );
    }

    public function details(): array
    {
        $details = [$this->type->label()];

        if ($this->reason !== null && $this->reason !== '') {
            $details[] = __('Reason').': '.self::reasonLabel($this->reason);
        }

        return $details;
    }

    private static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'role_changed' => __('Role changed'),
            'member_removed' => __('Member removed'),
            'member_left' => __('Member left'),
            'organization_deleted' => __('Organization deleted'),
            default => $reason,
        };
    }
}
