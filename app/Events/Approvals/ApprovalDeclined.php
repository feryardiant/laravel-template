<?php

declare(strict_types=1);

namespace App\Events\Approvals;

use App\Enums\AuditEvent;

/**
 * Raised when an approval request is declined.
 */
final class ApprovalDeclined extends ApprovableEvent
{
    /**
     * The audit event recorded for this change.
     */
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ApprovalDeclined;
    }
}
