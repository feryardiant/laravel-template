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

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Accepted => __('Accepted'),
            self::Declined => __('Declined'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
