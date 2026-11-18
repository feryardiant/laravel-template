<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle of an approval request.
 */
enum ApprovalRequestStatus: string
{
    case Pending = 'pending';

    case Accepted = 'accepted';

    case Declined = 'declined';

    case Canceled = 'canceled';

    /**
     * Get the badge color for the status.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Accepted => 'green',
            self::Declined => 'red',
            self::Canceled => 'zinc',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Accepted => __('Accepted'),
            self::Declined => __('Declined'),
            self::Canceled => __('Canceled'),
        };
    }

    public function isFinished(): bool
    {
        return $this !== self::Pending;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isAccepted(): bool
    {
        return $this === self::Accepted;
    }

    public function isDeclined(): bool
    {
        return $this === self::Declined;
    }

    public function isCanceled(): bool
    {
        return $this === self::Canceled;
    }
}
