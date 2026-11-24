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

    /**
     * The human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Accepted => __('Accepted'),
            self::Declined => __('Declined'),
            self::Canceled => __('Canceled'),
        };
    }

    /**
     * Determine whether the request has left the pending state.
     */
    public function isFinished(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * Determine whether the request is still pending.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Determine whether the request was accepted.
     */
    public function isAccepted(): bool
    {
        return $this === self::Accepted;
    }

    /**
     * Determine whether the request was declined.
     */
    public function isDeclined(): bool
    {
        return $this === self::Declined;
    }

    /**
     * Determine whether the request was canceled.
     */
    public function isCanceled(): bool
    {
        return $this === self::Canceled;
    }
}
