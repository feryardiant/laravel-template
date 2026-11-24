<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of approval an organization can request.
 */
enum ApprovalRequestType: string
{
    case OwnershipTransfer = 'ownership_transfer';

    case MembershipLeave = 'membership_leave';

    /**
     * The human-readable label for the request type.
     */
    public function label(): string
    {
        return match ($this) {
            self::OwnershipTransfer => __('Ownership transfer'),
            self::MembershipLeave => __('Membership leave'),
        };
    }
}
