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
}
