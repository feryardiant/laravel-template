<?php

declare(strict_types=1);

namespace App\Actions\Memberships\Audit;

use App\Actions\Audit\AuditPayload;
use UnexpectedValueException;

final class MemberRemovedPayload extends AuditPayload
{
    /**
     * @param  'self'|'system'|'manager'  $via
     */
    public function __construct(public string $via, public bool $lastMembership) {}

    public function toArray(): array
    {
        return [
            'via' => $this->via,
            'last_membership' => $this->lastMembership,
        ];
    }

    public static function fromArray(array $data): static
    {
        $via = (string) ($data['via'] ?? '');

        return new self(
            match ($via) {
                'self', 'system', 'manager' => $via,
                default => throw new UnexpectedValueException('Unknown member removal route: '.$via),
            },
            (bool) $data['last_membership'],
        );
    }

    public function details(): array
    {
        $details = [__('Via :via', ['via' => match ($this->via) {
            'manager' => __('Manager'),
            'self' => __('Self'),
            default => $this->via,
        }])];

        if ($this->lastMembership) {
            $details[] = __('Last membership');
        }

        return $details;
    }
}
