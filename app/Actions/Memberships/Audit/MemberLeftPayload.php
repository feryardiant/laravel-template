<?php

declare(strict_types=1);

namespace App\Actions\Memberships\Audit;

use App\Actions\Audit\AuditPayload;
use UnexpectedValueException;

final class MemberLeftPayload extends AuditPayload
{
    /**
     * @param  'self'|'approval'  $via
     */
    public function __construct(public string $via, public ?int $approvalId = null) {}

    public function toArray(): array
    {
        $data = ['via' => $this->via];

        if ($this->approvalId !== null) {
            $data['approval_id'] = $this->approvalId;
        }

        return $data;
    }

    public static function fromArray(array $data): static
    {
        $via = (string) ($data['via'] ?? '');
        $approvalId = $data['approval_id'] ?? null;

        return new self(
            match ($via) {
                'self', 'approval' => $via,
                default => throw new UnexpectedValueException('Unknown member leave route: '.$via),
            },
            $approvalId === null ? null : (int) $approvalId,
        );
    }

    public function details(): array
    {
        return [__('Via :via', ['via' => $this->via === 'self' ? __('Self') : __('Approval')])];
    }
}
